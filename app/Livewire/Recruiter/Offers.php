<?php

namespace App\Livewire\Recruiter;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

class Offers extends Component
{
    use WithFileUploads;

    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $search = '';
    public string $statusFilter = '';
    public string $requisitionFilter = '';
    public int $page = 1;

    public array $offers = [];
    public array $pagination = [];
    public array $requisitions = [];
    public array $candidates = [];

    public bool $showView = false;
    public ?array $viewData = null;

    public bool $showGenerate = false;
    public array $generateForm = [];

    public bool $showAccept = false;
    public ?int $acceptId = null;
    public string $acceptName = '';
    public $signedCopy = null;

    public bool $showDecline = false;
    public ?int $declineId = null;
    public string $declineName = '';
    public string $declineReason = '';

    public bool $showWithdraw = false;
    public ?int $withdrawId = null;
    public string $withdrawName = '';
    public string $withdrawReason = '';

    public bool $showSendEmail = false;
    public ?int $sendEmailId = null;
    public string $sendEmailName = '';
    public string $sendEmailMessage = '';

    public function mount(): void
    {
        $this->resetGenerateForm();
        $this->loadReferenceData();
        $this->load();
    }

    protected function loadReferenceData(): void
    {
        $rq = Api::get('/recruitment/requisition/list', ['per_page' => 200]);
        if ($rq['ok']) {
            $this->requisitions = $rq['data']['data'] ?? [];
        }

        $cd = Api::get('/recruitment/candidate/list', ['per_page' => 300]);
        if ($cd['ok']) {
            $all = $cd['data']['data'] ?? [];
            // any candidate not in a terminal stage can receive an offer
            $this->candidates = array_values(array_filter(
                $all,
                fn ($c) => !in_array($c['current_stage'] ?? '', ['hired', 'rejected', 'withdrawn'], true)
            ));
        }
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['per_page' => 20];

        if ($this->search !== '')            $params['search'] = $this->search;
        if ($this->statusFilter !== '')      $params['status'] = $this->statusFilter;
        if ($this->requisitionFilter !== '') $params['requisition_id'] = $this->requisitionFilter;
        if ($this->page > 1)                 $params['page'] = $this->page;

        $response = Api::get('/recruitment/offer/list', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load offers.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $this->offers = $paginator['data'] ?? [];
        $this->pagination = [
            'current_page' => $paginator['current_page'] ?? 1,
            'last_page' => $paginator['last_page'] ?? 1,
            'total' => $paginator['total'] ?? 0,
        ];

        $this->loading = false;
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        $this->load();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->requisitionFilter = '';
        $this->page = 1;
        $this->load();
    }

    public function selectStatus(string $status): void
    {
        $this->statusFilter = $status;
        $this->page = 1;
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    // ============ generate ============

    public function resetGenerateForm(): void
    {
        $this->generateForm = [
            'candidate_id' => '',
            'salary_offered' => '',
            'position' => '',
            'department' => '',
            'contract_type' => 'permanent',
            'manager_id' => '',
            'notice_period_days' => 30,
            'benefits' => '',
            'start_date' => now()->addDays(30)->format('Y-m-d'),
            'expiry_date' => now()->addDays(7)->format('Y-m-d'),
        ];
    }

    public function openGenerate(?int $candidateId = null): void
    {
        $this->resetGenerateForm();
        if ($candidateId) {
            $this->generateForm['candidate_id'] = (string) $candidateId;
        }
        $this->error = null;
        $this->showGenerate = true;
    }

    public function closeGenerate(): void
    {
        $this->showGenerate = false;
        $this->resetGenerateForm();
    }

    public function submitGenerate(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->generateForm['candidate_id'])) {
            $this->error = 'Select a candidate.';
            return;
        }
        if (empty($this->generateForm['salary_offered'])) {
            $this->error = 'Salary offered is required.';
            return;
        }
        if (empty($this->generateForm['start_date']) || empty($this->generateForm['expiry_date'])) {
            $this->error = 'Start date and offer expiry date are required.';
            return;
        }

        $benefits = [];
        if (!empty($this->generateForm['benefits'])) {
            $benefits = array_values(array_filter(array_map(
                'trim',
                preg_split('/[\r\n,]+/', $this->generateForm['benefits'])
            )));
        }

        $payload = [
            'candidate_id' => $this->generateForm['candidate_id'],
            'salary_offered' => (float) $this->generateForm['salary_offered'],
            'start_date' => $this->generateForm['start_date'],
            'expiry_date' => $this->generateForm['expiry_date'],
            'notice_period_days' => (int) $this->generateForm['notice_period_days'],
            'benefits' => $benefits,
        ];

        $optional = ['position', 'department', 'contract_type', 'manager_id'];
        foreach ($optional as $field) {
            if (!empty($this->generateForm[$field])) {
                $payload[$field] = $this->generateForm[$field];
            }
        }

        $response = Api::post('/recruitment/offer/generate', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to generate offer.');
            return;
        }

        $this->flash = 'Offer generated. Review the PDF and send to the candidate when ready.';
        $this->closeGenerate();
        $this->loadReferenceData();
        $this->load();
    }

    // ============ accept ============

    public function openAccept(int $id): void
    {
        $row = collect($this->offers)->firstWhere('id', $id);
        if (!$row) {
            $this->error = 'Offer not found.';
            return;
        }

        $this->acceptId = $id;
        $this->acceptName = trim(
            ($row['candidate']['first_name'] ?? '') . ' ' . ($row['candidate']['last_name'] ?? '')
        );
        $this->signedCopy = null;
        $this->error = null;
        $this->showAccept = true;
        $this->closeView();
    }

    public function closeAccept(): void
    {
        $this->showAccept = false;
        $this->acceptId = null;
        $this->acceptName = '';
        $this->signedCopy = null;
    }

    public function submitAccept(): void
    {
        $this->error = null;
        $this->flash = null;

        $payload = ['offer_id' => $this->acceptId];
        $files = $this->signedCopy ? ['signed_copy' => $this->signedCopy] : [];

        $response = $files
            ? Api::upload('/recruitment/offer/accept', $payload, $files)
            : Api::post('/recruitment/offer/accept', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to accept offer.');
            return;
        }

        $this->flash = $this->acceptName . ' accepted the offer. Ready to convert to employee.';
        $this->closeAccept();
        $this->loadReferenceData();
        $this->load();
    }

    // ============ decline ============

    public function openDecline(int $id): void
    {
        $row = collect($this->offers)->firstWhere('id', $id);
        if (!$row) {
            $this->error = 'Offer not found.';
            return;
        }

        $this->declineId = $id;
        $this->declineName = trim(
            ($row['candidate']['first_name'] ?? '') . ' ' . ($row['candidate']['last_name'] ?? '')
        );
        $this->declineReason = '';
        $this->error = null;
        $this->showDecline = true;
        $this->closeView();
    }

    public function closeDecline(): void
    {
        $this->showDecline = false;
        $this->declineId = null;
        $this->declineName = '';
        $this->declineReason = '';
    }

    public function submitDecline(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->declineReason)) {
            $this->error = 'Decline reason is required.';
            return;
        }

        $response = Api::post('/recruitment/offer/decline', [
            'offer_id' => $this->declineId,
            'reason' => $this->declineReason,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to decline offer.');
            return;
        }

        $this->flash = $this->declineName . ' declined the offer.';
        $this->closeDecline();
        $this->load();
    }

    // ============ withdraw ============

    public function openWithdraw(int $id): void
    {
        $row = collect($this->offers)->firstWhere('id', $id);
        if (!$row) {
            $this->error = 'Offer not found.';
            return;
        }

        $this->withdrawId = $id;
        $this->withdrawName = trim(
            ($row['candidate']['first_name'] ?? '') . ' ' . ($row['candidate']['last_name'] ?? '')
        );
        $this->withdrawReason = '';
        $this->error = null;
        $this->showWithdraw = true;
        $this->closeView();
    }

    public function closeWithdraw(): void
    {
        $this->showWithdraw = false;
        $this->withdrawId = null;
        $this->withdrawName = '';
        $this->withdrawReason = '';
    }

    public function submitWithdraw(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->withdrawReason)) {
            $this->error = 'Withdraw reason is required.';
            return;
        }

        $response = Api::post('/recruitment/offer/withdraw', [
            'offer_id' => $this->withdrawId,
            'reason' => $this->withdrawReason,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to withdraw offer.');
            return;
        }

        $this->flash = 'Offer withdrawn.';
        $this->closeWithdraw();
        $this->load();
    }

    // ============ send email ============

    public function openSendEmail(int $id): void
    {
        $row = collect($this->offers)->firstWhere('id', $id);
        if (!$row) {
            $this->error = 'Offer not found.';
            return;
        }

        $this->sendEmailId = $id;
        $this->sendEmailName = trim(
            ($row['candidate']['first_name'] ?? '') . ' ' . ($row['candidate']['last_name'] ?? '')
        );
        $this->sendEmailMessage = '';
        $this->error = null;
        $this->showSendEmail = true;
        $this->closeView();
    }

    public function closeSendEmail(): void
    {
        $this->showSendEmail = false;
        $this->sendEmailId = null;
        $this->sendEmailName = '';
        $this->sendEmailMessage = '';
    }

    public function submitSendEmail(): void
    {
        $this->error = null;
        $this->flash = null;

        $payload = ['offer_id' => $this->sendEmailId];
        if (!empty($this->sendEmailMessage)) {
            $payload['custom_message'] = $this->sendEmailMessage;
        }

        $response = Api::post('/recruitment/offer/send-email', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to send offer email.');
            return;
        }

        $this->flash = 'Offer email sent to ' . $this->sendEmailName . '.';
        $this->closeSendEmail();
        $this->load();
    }

    // ============ view ============

    public function openView(int $id): void
    {
        $this->error = null;

        $response = Api::get('/recruitment/offer/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load offer.';
            return;
        }

        $this->viewData = $response['data'] ?? null;
        $this->showView = true;
    }

    public function closeView(): void
    {
        $this->showView = false;
        $this->viewData = null;
    }

    // ============ helpers ============

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'extended'  => 'gold',
            'accepted'  => 'success',
            'declined'  => 'red',
            'withdrawn' => 'muted',
            'expired'   => 'red',
            'countered' => 'purple',
            default     => 'muted',
        };
    }

    public function canAccept(array $row): bool
    {
        if (($row['status'] ?? '') !== 'extended') return false;
        if (!empty($row['is_expired'])) return false;

        return true;
    }

    public function canDecline(array $row): bool
    {
        return ($row['status'] ?? '') === 'extended';
    }

    public function canWithdraw(array $row): bool
    {
        return ($row['status'] ?? '') === 'extended';
    }

    public function canSendEmail(array $row): bool
    {
        return ($row['status'] ?? '') === 'extended';
    }

    public function canConvert(array $row): bool
    {
        return ($row['status'] ?? '') === 'accepted';
    }

    public function render()
    {
        return view('livewire.recruiter.offers')
            ->layout('layouts.portal', ['title' => 'Offers', 'breadcrumb' => 'Offers']);
    }
}