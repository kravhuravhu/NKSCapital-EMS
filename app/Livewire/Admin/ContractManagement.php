<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class ContractManagement extends Component
{
    use WithFileUploads;

    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $search = '';
    public string $statusFilter = 'active';
    public string $typeFilter = '';
    public string $expiringFilter = '';
    public int $page = 1;

    public array $contracts = [];
    public array $pagination = [];
    public array $employees = [];

    public array $stats = [
        'total' => 0,
        'active' => 0,
        'expiring' => 0,
        'pending_signature' => 0,
    ];

    public bool $showUpload = false;
    public array $uploadForm = [];
    public $uploadFile = null;

    public bool $showRenew = false;
    public ?int $renewId = null;
    public string $renewLabel = '';
    public array $renewForm = [];

    public bool $showStatus = false;
    public ?int $statusId = null;
    public string $statusLabel = '';
    public array $statusForm = [];

    public bool $showDelete = false;
    public ?int $deleteId = null;
    public string $deleteLabel = '';
    public string $deletePassword = '';

    public bool $showView = false;
    public ?array $viewData = null;

    public function mount(): void
    {
        $this->resetUploadForm();
        $this->loadEmployees();
        $this->load();
    }

    protected function loadEmployees(): void
    {
        $response = Api::get('/employee/list', ['per_page' => 300, 'is_active' => 1]);

        if ($response['ok']) {
            $this->employees = $response['data']['data'] ?? [];
        }
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['per_page' => 20];

        if ($this->search !== '')         $params['search'] = $this->search;
        if ($this->statusFilter !== '')   $params['status'] = $this->statusFilter;
        if ($this->typeFilter !== '')     $params['contract_type'] = $this->typeFilter;
        if ($this->expiringFilter !== '') $params['expiring_days'] = $this->expiringFilter;
        if ($this->page > 1)              $params['page'] = $this->page;

        $response = Api::get('/contract/list', $params);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load contracts.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $this->contracts = $paginator['data'] ?? [];
        $this->pagination = [
            'current_page' => $paginator['current_page'] ?? 1,
            'last_page' => $paginator['last_page'] ?? 1,
            'total' => $paginator['total'] ?? 0,
        ];

        $this->computeStats();
        $this->loading = false;
    }

    protected function computeStats(): void
    {
        $stats = ['total' => 0, 'active' => 0, 'expiring' => 0, 'pending_signature' => 0];

        foreach ($this->contracts as $c) {
            $stats['total']++;

            match ($c['status'] ?? '') {
                'active' => $stats['active']++,
                'pending_signature' => $stats['pending_signature']++,
                default => null,
            };

            if (! empty($c['days_until_expiry']) && $c['days_until_expiry'] >= 0 && $c['days_until_expiry'] <= 30) {
                $stats['expiring']++;
            }
        }

        $this->stats = $stats;
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        $this->load();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'active';
        $this->typeFilter = '';
        $this->expiringFilter = '';
        $this->page = 1;
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    public function selectStatus(string $status): void
    {
        $this->statusFilter = $status;
        $this->applyFilters();
    }

    // ============ upload ============

    public function resetUploadForm(): void
    {
        $this->uploadForm = [
            'user_id' => '',
            'effective_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'probation_end_date' => '',
            'salary_annual' => '',
            'notice_period_days' => 30,
            'position' => '',
            'contract_type' => 'permanent',
            'status' => 'pending_signature',
            'parent_contract_id' => '',
        ];
        $this->uploadFile = null;
    }

    public function openUpload(): void
    {
        $this->resetUploadForm();
        $this->error = null;
        $this->showUpload = true;
    }

    public function closeUpload(): void
    {
        $this->showUpload = false;
    }

    public function submitUpload(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->uploadForm['user_id'])) {
            $this->error = 'Select an employee.';
            return;
        }
        if (! $this->uploadFile) {
            $this->error = 'Select a PDF file to upload.';
            return;
        }
        if (empty($this->uploadForm['effective_date'])) {
            $this->error = 'Effective date is required.';
            return;
        }

        $payload = array_filter($this->uploadForm, fn ($v) => $v !== '' && $v !== null);

        $response = Api::upload('/contract/upload', $payload, ['file' => $this->uploadFile]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to upload contract.');
            return;
        }

        $this->flash = 'Contract uploaded.';
        $this->closeUpload();
        $this->load();
    }

    // ============ renew ============

    public function openRenew(int $id): void
    {
        $row = collect($this->contracts)->firstWhere('id', $id);

        if (! $row) {
            $this->error = 'Contract not found.';
            return;
        }

        $this->renewId = $id;
        $this->renewLabel = 'v' . ($row['version'] ?? '?') . ' &middot; ' .
            trim(($row['user']['first_name'] ?? '') . ' ' . ($row['user']['last_name'] ?? ''));

        $this->renewForm = [
            'effective_date' => now()->format('Y-m-d'),
            'expiry_date' => now()->addYear()->format('Y-m-d'),
            'salary_annual' => $row['salary_annual'] ?? '',
            'notice_period_days' => $row['notice_period_days'] ?? 30,
            'position' => $row['position'] ?? '',
            'contract_type' => $row['contract_type'] ?? 'permanent',
        ];

        $this->error = null;
        $this->showRenew = true;
        $this->closeView();
    }

    public function closeRenew(): void
    {
        $this->showRenew = false;
        $this->renewId = null;
        $this->renewLabel = '';
        $this->renewForm = [];
    }

    public function submitRenew(): void
    {
        $this->error = null;
        $this->flash = null;

        $payload = array_filter($this->renewForm, fn ($v) => $v !== '' && $v !== null);
        $payload['contract_id'] = $this->renewId;

        $response = Api::post('/contract/renew', $payload);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to renew contract.');
            return;
        }

        $this->flash = 'Contract renewed. Upload the signed file to activate the new version.';
        $this->closeRenew();
        $this->load();
    }

    // ============ status ============

    public function openStatus(int $id): void
    {
        $row = collect($this->contracts)->firstWhere('id', $id);

        if (! $row) {
            $this->error = 'Contract not found.';
            return;
        }

        $this->statusId = $id;
        $this->statusLabel = 'v' . ($row['version'] ?? '?') . ' &middot; ' .
            trim(($row['user']['first_name'] ?? '') . ' ' . ($row['user']['last_name'] ?? ''));

        $this->statusForm = [
            'status' => $row['status'] ?? 'active',
            'reason' => '',
            'effective_date' => now()->format('Y-m-d'),
        ];

        $this->error = null;
        $this->showStatus = true;
        $this->closeView();
    }

    public function closeStatus(): void
    {
        $this->showStatus = false;
        $this->statusId = null;
        $this->statusLabel = '';
        $this->statusForm = [];
    }

    public function submitStatus(): void
    {
        $this->error = null;
        $this->flash = null;

        if (($this->statusForm['status'] ?? '') === 'terminated' && empty($this->statusForm['reason'])) {
            $this->error = 'A termination reason is required.';
            return;
        }

        $payload = [
            'contract_id' => $this->statusId,
            'status' => $this->statusForm['status'],
        ];

        if (! empty($this->statusForm['reason'])) {
            $payload['reason'] = $this->statusForm['reason'];
        }
        if (! empty($this->statusForm['effective_date'])) {
            $payload['effective_date'] = $this->statusForm['effective_date'];
        }

        $response = Api::post('/contract/status/update', $payload);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to update status.');
            return;
        }

        $this->flash = 'Contract status updated.';
        $this->closeStatus();
        $this->load();
    }

    // ============ delete ============

    public function openDelete(int $id): void
    {
        $row = collect($this->contracts)->firstWhere('id', $id);

        if (! $row) {
            $this->error = 'Contract not found.';
            return;
        }

        if (! in_array($row['status'] ?? '', ['draft', 'pending_signature'], true)) {
            $this->error = 'Only draft or pending-signature contracts can be deleted. Use status update to terminate active contracts.';
            return;
        }

        $this->deleteId = $id;
        $this->deleteLabel = 'v' . ($row['version'] ?? '?') . ' &middot; ' .
            trim(($row['user']['first_name'] ?? '') . ' ' . ($row['user']['last_name'] ?? ''));
        $this->deletePassword = '';
        $this->error = null;
        $this->showDelete = true;
        $this->closeView();
    }

    public function closeDelete(): void
    {
        $this->showDelete = false;
        $this->deleteId = null;
        $this->deleteLabel = '';
        $this->deletePassword = '';
    }

    public function submitDelete(): void
    {
        $this->error = null;

        if (empty($this->deletePassword)) {
            $this->error = 'Enter your admin password to confirm.';
            return;
        }

        if (! Auth::validate([
            'email' => auth()->user()->email,
            'password' => $this->deletePassword,
        ])) {
            $this->error = 'Password is incorrect.';
            return;
        }

        $response = Api::delete('/contract/delete/' . $this->deleteId);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed to delete contract.';
            return;
        }

        $this->flash = 'Contract deleted.';
        $this->closeDelete();
        $this->load();
    }

    // ============ view ============

    public function openView(int $id): void
    {
        $this->error = null;

        $response = Api::get('/contract/view/' . $id);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load contract.';
            return;
        }

        $data = $response['data'] ?? null;

        if ($data && ! empty($data['file_path'])) {
            $data['download_url'] = asset('storage/' . $data['file_path']);
        }
        if ($data && ! empty($data['signed_file_path'])) {
            $data['signed_download_url'] = asset('storage/' . $data['signed_file_path']);
        }

        $this->viewData = $data;
        $this->showView = true;
    }

    public function closeView(): void
    {
        $this->showView = false;
        $this->viewData = null;
    }

    public function daysUntilExpiry(array $row): ?int
    {
        if (empty($row['expiry_date'])) return null;

        return (int) now()->startOfDay()->diffInDays(
            Carbon::parse($row['expiry_date'])->startOfDay(),
            false
        );
    }

    public function expiryTone(array $row): string
    {
        $days = $this->daysUntilExpiry($row);

        if ($days === null) return 'muted';
        if ($days < 0) return 'danger';
        if ($days <= 14) return 'danger';
        if ($days <= 30) return 'warning';
        if ($days <= 60) return 'gold';

        return 'success';
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'draft' => 'muted',
            'pending_signature' => 'warning',
            'expired', 'terminated' => 'danger',
            'renewed' => 'info',
            'superseded' => 'muted',
            default => 'muted',
        };
    }

    public static function typeTone(string $type): string
    {
        return match ($type) {
            'permanent' => 'success',
            'fixed_term' => 'info',
            'consulting' => 'purple',
            'internship' => 'gold',
            default => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.admin.contract-management')
            ->layout('layouts.portal', ['title' => 'Contracts', 'breadcrumb' => 'Contracts']);
    }
}