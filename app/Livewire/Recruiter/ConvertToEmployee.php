<?php

namespace App\Livewire\Recruiter;

use App\Support\Api;
use Livewire\Component;

class ConvertToEmployee extends Component
{
    public bool $loading = true;
    public bool $submitting = false;
    public ?string $error = null;
    public ?string $flash = null;

    public int $offerId = 0;
    public array $acceptedOffers = [];
    public ?array $selectedOffer = null;
    public ?array $candidate = null;

    public array $form = [];

    public array $clients = [];
    public array $projects = [];
    public array $managers = [];

    public bool $showCredentials = false;
    public ?array $credentials = null;

    public function mount(): void
    {
        $this->offerId = (int) request()->query('offer_id', 0);
        $this->loadReferenceData();
        $this->loadAcceptedOffers();

        if ($this->offerId) {
            $this->loadSelectedOffer();
        }

        $this->loading = false;
    }

    protected function loadReferenceData(): void
    {
        $cl = Api::get('/client/list', ['is_active' => 1, 'per_page' => 200]);
        if ($cl['ok']) {
            $this->clients = $cl['data']['data'] ?? [];
        }

        $pr = Api::get('/project/list', ['per_page' => 200]);
        if ($pr['ok']) {
            $this->projects = $pr['data']['data'] ?? [];
        }

        $usr = Api::get('/employee/list', ['is_active' => 1, 'per_page' => 300]);
        if ($usr['ok']) {
            $all = $usr['data']['data'] ?? [];
            $this->managers = array_values(array_filter(
                $all,
                fn ($u) => in_array($u['role'] ?? '', ['manager', 'director', 'admin'], true)
            ));
        }
    }

    protected function loadAcceptedOffers(): void
    {
        $response = Api::get('/recruitment/offer/list', [
            'status' => 'accepted',
            'per_page' => 100,
        ]);

        if (!$response['ok']) {
            $this->acceptedOffers = [];
            return;
        }

        $rows = $response['data']['data'] ?? [];

        // hide any candidate already converted to an employee
        $this->acceptedOffers = array_values(array_filter(
            $rows,
            fn ($o) => ($o['candidate']['current_stage'] ?? '') !== 'hired'
        ));
    }

    protected function loadSelectedOffer(): void
    {
        $response = Api::get('/recruitment/offer/' . $this->offerId);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Offer not found.';
            $this->offerId = 0;
            return;
        }

        $offer = $response['data'] ?? null;

        if (!$offer) {
            $this->error = 'Offer not found.';
            $this->offerId = 0;
            return;
        }

        if (($offer['status'] ?? '') !== 'accepted') {
            $this->error = 'Only accepted offers can be converted to employees. Current status: ' . ($offer['status'] ?? 'unknown');
            $this->offerId = 0;
            return;
        }

        if (($offer['candidate']['current_stage'] ?? '') === 'hired') {
            $this->error = 'This candidate has already been converted to an employee.';
            $this->offerId = 0;
            return;
        }

        $this->selectedOffer = $offer;
        $this->candidate = $offer['candidate'] ?? null;
        $this->buildForm();
    }

    protected function buildForm(): void
    {
        $offer = $this->selectedOffer;
        $req = $offer['candidate']['requisition'] ?? [];

        $serviceType = match ($req['employment_type'] ?? 'full_time') {
            'full_time' => 'permanent',
            'part_time' => 'temporary',
            'contract' => 'contractor',
            default     => 'permanent',
        };

        $this->form = [
            'role' => 'employee',
            'employee_type' => $req['employee_type_target'] ?? 'pr',
            'service_type' => $serviceType,
            'client_id' => '',
            'project_id' => '',
            'manager_id' => $offer['manager_id'] ?? ($req['hiring_manager_id'] ?? ''),
            'notes' => '',
        ];
    }

    public function selectOffer(int $offerId): void
    {
        $this->offerId = $offerId;
        $this->error = null;
        $this->loadSelectedOffer();
    }

    public function backToList(): void
    {
        $this->offerId = 0;
        $this->selectedOffer = null;
        $this->candidate = null;
        $this->form = [];
        $this->error = null;
        $this->loadAcceptedOffers();
    }

    public function submit(): void
    {
        $this->error = null;
        $this->flash = null;

        if (!$this->selectedOffer) {
            $this->error = 'No offer selected.';
            return;
        }

        if (($this->form['employee_type'] ?? '') === 'ps' && empty($this->form['client_id'])) {
            $this->error = 'PS employees require a client assignment.';
            return;
        }
        if (($this->form['employee_type'] ?? '') === 'pr' && empty($this->form['project_id'])) {
            $this->error = 'PR employees require a project assignment.';
            return;
        }

        $this->submitting = true;

        $payload = [
            'offer_id' => $this->selectedOffer['id'],
            'role' => $this->form['role'],
            'employee_type' => $this->form['employee_type'],
            'service_type' => $this->form['service_type'],
        ];

        if (!empty($this->form['client_id']))  $payload['client_id']  = $this->form['client_id'];
        if (!empty($this->form['project_id'])) $payload['project_id'] = $this->form['project_id'];
        if (!empty($this->form['manager_id'])) $payload['manager_id'] = $this->form['manager_id'];
        if (!empty($this->form['notes']))      $payload['notes']      = $this->form['notes'];

        $response = Api::post('/recruitment/convert-to-employee', $payload);

        $this->submitting = false;

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to convert candidate.');
            return;
        }

        $data = $response['data'] ?? [];
        $employee = $data['employee'] ?? null;

        if (!$employee) {
            $this->error = 'Conversion response was incomplete. Check the employee list.';
            return;
        }

        $this->credentials = [
            'employee_number' => $employee['employee_number'] ?? '',
            'email' => $employee['email'] ?? '',
            'name' => $employee['full_name']
                ?? trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')),
            'temporary_password' => $data['temporary_password'] ?? '',
        ];

        $this->flash = 'Candidate converted to employee successfully.';
        $this->showCredentials = true;

        // refresh the list so the converted candidate no longer appears
        $this->backToList();
    }

    public function closeCredentials(): void
    {
        $this->showCredentials = false;
        $this->credentials = null;
    }

    public function render()
    {
        return view('livewire.recruiter.convert-to-employee')
            ->layout('layouts.portal', ['title' => 'Convert to Employee', 'breadcrumb' => 'Convert to Employee']);
    }
}