<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

class Profile extends Component
{
    use WithFileUploads;

    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public ?array $profile = null;
    public array $contract = [];

    public $photo = null;
    public bool $showEdit = false;
    public array $editForm = [];

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $user = auth()->user();
        $response = Api::get('/employee/profile/' . $user->id);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load profile.';
            $this->loading = false;
            return;
        }

        $this->profile = $response['data'] ?? null;

        $contracts = $user->contracts()->orderByDesc('effective_date')->first();

        $this->contract = $contracts ? [
            'type' => ucfirst(str_replace('_', ' ', $contracts->contract_type ?? '—')),
            'start' => $contracts->effective_date?->format('d M Y') ?? '—',
            'end' => $contracts->expiry_date?->format('d M Y') ?? '—',
            'notice' => $contracts->notice_period_days ? $contracts->notice_period_days . ' days' : '—',
            'salary' => $contracts->salary_annual
                ? 'R ' . number_format((float) $contracts->salary_annual, 2)
                : '—',
            'status' => $contracts->status ?? 'not issued',
        ] : [];

        $this->loading = false;
    }

    public function uploadPhoto(): void
    {
        if (! $this->photo) {
            return;
        }

        $this->error = null;

        $response = Api::upload('/employee/upload-photo', [], ['photo' => $this->photo]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Photo upload failed.');
            return;
        }

        $this->flash = 'Photo updated.';
        $this->photo = null;
        $this->load();
    }

    public function openEdit(): void
    {
        $this->editForm = [
            'phone' => $this->profile['phone'] ?? '',
            'address' => $this->profile['address'] ?? '',
            'emergency_contact_name' => $this->profile['emergency_contact_name'] ?? '',
            'emergency_contact_phone' => $this->profile['emergency_contact_phone'] ?? '',
        ];
        $this->showEdit = true;
    }

    public function closeEdit(): void
    {
        $this->showEdit = false;
        $this->editForm = [];
    }

    public function saveEdit(): void
    {
        $this->error = null;

        $response = Api::put('/employee/update', $this->editForm);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Update failed.');
            return;
        }

        $this->flash = 'Profile updated.';
        $this->closeEdit();
        $this->load();
    }

    public function tenure(): string
    {
        if (empty($this->profile['hire_date'])) {
            return '—';
        }

        $years = Carbon::parse($this->profile['hire_date'])->diffInYears(now());
        $months = Carbon::parse($this->profile['hire_date'])->diffInMonths(now()) % 12;

        return trim("{$years}y {$months}m");
    }

    public function render()
    {
        return view('livewire.employee.profile')
            ->layout('layouts.portal', ['title' => 'My Profile', 'breadcrumb' => 'Profile']);
    }
}