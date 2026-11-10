<?php

namespace App\Livewire\Recruiter;

use App\Support\Api;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class Requisitions extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $search = '';
    public string $statusFilter = '';
    public string $priorityFilter = '';
    public string $employeeTypeFilter = '';
    public int $page = 1;

    public array $requisitions = [];
    public array $pagination = [];
    public array $managers = [];

    public array $stats = [
        'total' => 0,
        'open' => 0,
        'pending' => 0,
        'closed' => 0,
    ];

    public bool $showView = false;
    public ?array $viewData = null;

    public bool $showCreate = false;
    public array $createForm = [];

    public bool $showEdit = false;
    public ?int $editId = null;
    public array $editForm = [];

    public bool $showApprove = false;
    public ?int $approveId = null;
    public string $approvePassword = '';

    public bool $showReject = false;
    public ?int $rejectId = null;
    public string $rejectPassword = '';
    public string $rejectReason = '';

    public bool $showClose = false;
    public ?int $closeId = null;
    public string $closePassword = '';
    public string $closeReason = '';
    public string $closeStatus = 'closed';

    public function mount(): void
    {
        $this->resetCreateForm();
        $this->loadReferenceData();
        $this->load();
    }

    protected function loadReferenceData(): void
    {
        $response = Api::get('/employee/list', ['per_page' => 300, 'is_active' => 1]);

        if ($response['ok']) {
            $all = $response['data']['data'] ?? [];
            $this->managers = array_values(array_filter(
                $all,
                fn ($u) => in_array($u['role'] ?? '', ['manager', 'director', 'operational_manager', 'admin'], true)
            ));
        }
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['per_page' => 20];

        if ($this->search !== '')             $params['search'] = $this->search;
        if ($this->statusFilter !== '')       $params['status'] = $this->statusFilter;
        if ($this->priorityFilter !== '')     $params['priority'] = $this->priorityFilter;
        if ($this->employeeTypeFilter !== '') $params['employee_type_target'] = $this->employeeTypeFilter;
        if ($this->page > 1)                  $params['page'] = $this->page;

        $response = Api::get('/recruitment/requisition/list', $params);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load requisitions.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $this->requisitions = $paginator['data'] ?? [];
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
        $stats = ['total' => 0, 'open' => 0, 'pending' => 0, 'closed' => 0];

        foreach ($this->requisitions as $r) {
            $stats['total']++;

            match ($r['status'] ?? '') {
                'open' => $stats['open']++,
                'pending_approval' => $stats['pending']++,
                'closed', 'cancelled' => $stats['closed']++,
                default => null,
            };
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
        $this->statusFilter = '';
        $this->priorityFilter = '';
        $this->employeeTypeFilter = '';
        $this->page = 1;
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    public function openView(int $id): void
    {
        $this->error = null;

        $response = Api::get('/recruitment/requisition/' . $id);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load requisition.';
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

    public function resetCreateForm(): void
    {
        $this->createForm = [
            'title' => '',
            'department' => '',
            'employment_type' => 'full_time',
            'employee_type_target' => '',
            'experience_level' => '',
            'priority' => 'medium',
            'required_skills' => '',
            'salary_range_min' => '',
            'salary_range_max' => '',
            'salary_currency' => 'ZAR',
            'reason_for_hire' => '',
            'hiring_manager_id' => '',
            'target_start_date' => '',
            'requisition_code' => '',
        ];
    }

    public function openCreate(): void
    {
        $this->resetCreateForm();
        $this->error = null;
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
    }

    public function submitCreate(string $status = 'pending_approval'): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->createForm['title'])) {
            $this->error = 'Title is required.';
            return;
        }
        if (empty($this->createForm['department'])) {
            $this->error = 'Department is required.';
            return;
        }

        $payload = $this->buildPayload($this->createForm);
        $payload['status'] = $status;

        $response = Api::post('/recruitment/requisition/create', $payload);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to create requisition.');
            return;
        }

        $this->flash = $status === 'draft'
            ? 'Requisition saved as draft.'
            : 'Requisition submitted for approval.';
        $this->closeCreate();
        $this->load();
    }

    public function openEdit(int $id): void
    {
        $row = collect($this->requisitions)->firstWhere('id', $id);

        if (! $row) {
            $this->error = 'Requisition not found.';
            return;
        }

        $this->editId = $id;
        $this->editForm = [
            'title' => $row['title'] ?? '',
            'department' => $row['department'] ?? '',
            'employment_type' => $row['employment_type'] ?? 'full_time',
            'employee_type_target' => $row['employee_type_target'] ?? '',
            'experience_level' => $row['experience_level'] ?? '',
            'priority' => $row['priority'] ?? 'medium',
            'required_skills' => is_array($row['required_skills'] ?? null) ? implode(', ', $row['required_skills']) : '',
            'salary_range_min' => $row['salary_range_min'] ?? '',
            'salary_range_max' => $row['salary_range_max'] ?? '',
            'salary_currency' => $row['salary_currency'] ?? 'ZAR',
            'reason_for_hire' => $row['reason_for_hire'] ?? '',
            'hiring_manager_id' => $row['hiring_manager_id'] ?? '',
            'target_start_date' => $row['target_start_date'] ?? '',
        ];

        $this->error = null;
        $this->showEdit = true;
        $this->closeView();
    }

    public function closeEdit(): void
    {
        $this->showEdit = false;
        $this->editId = null;
        $this->editForm = [];
    }

    public function submitEdit(): void
    {
        $this->error = null;
        if (! $this->editId) return;

        $payload = $this->buildPayload($this->editForm);

        $response = Api::put('/recruitment/requisition/update/' . $this->editId, $payload);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to update requisition.');
            return;
        }

        $this->flash = 'Requisition updated.';
        $this->closeEdit();
        $this->load();
    }

    public function openApprove(int $id): void
    {
        $this->approveId = $id;
        $this->approvePassword = '';
        $this->error = null;
        $this->showApprove = true;
        $this->closeView();
    }

    public function closeApprove(): void
    {
        $this->showApprove = false;
        $this->approveId = null;
        $this->approvePassword = '';
    }

    public function submitApprove(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->approvePassword)) {
            $this->error = 'Password is required.';
            return;
        }

        $user = Auth::user();

        if (! $user || ! Hash::check($this->approvePassword, $user->password)) {
            $this->error = 'Password is incorrect.';
            return;
        }

        $response = Api::post('/recruitment/requisition/approve', [
            'requisition_id' => $this->approveId,
        ]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to approve requisition.');
            return;
        }

        $this->flash = 'Requisition approved and now open for applications.';
        $this->closeApprove();
        $this->load();
    }

    public function openReject(int $id): void
    {
        $this->rejectId = $id;
        $this->rejectPassword = '';
        $this->rejectReason = '';
        $this->error = null;
        $this->showReject = true;
        $this->closeView();
    }

    public function closeReject(): void
    {
        $this->showReject = false;
        $this->rejectId = null;
        $this->rejectPassword = '';
        $this->rejectReason = '';
    }

    public function submitReject(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->rejectPassword)) {
            $this->error = 'Password is required.';
            return;
        }
        if (empty($this->rejectReason)) {
            $this->error = 'Rejection reason is required.';
            return;
        }

        if (! Auth::validate([
            'email' => auth()->user()->email,
            'password' => $this->rejectPassword,
        ])) {
            $this->error = 'Password is incorrect.';
            return;
        }

        $response = Api::put('/recruitment/requisition/update/' . $this->rejectId, [
            'status' => 'cancelled',
            'rejection_reason' => $this->rejectReason,
        ]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to reject requisition.');
            return;
        }

        $this->flash = 'Requisition rejected.';
        $this->closeReject();
        $this->load();
    }

    public function openClose(int $id, string $status = 'closed'): void
    {
        $this->closeId = $id;
        $this->closeStatus = $status;
        $this->closePassword = '';
        $this->closeReason = '';
        $this->error = null;
        $this->showClose = true;
        $this->closeView();
    }

    public function closeClose(): void
    {
        $this->showClose = false;
        $this->closeId = null;
        $this->closePassword = '';
        $this->closeReason = '';
    }

    public function submitClose(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->closePassword)) {
            $this->error = 'Password is required.';
            return;
        }

        if (! Auth::validate([
            'email' => auth()->user()->email,
            'password' => $this->closePassword,
        ])) {
            $this->error = 'Password is incorrect.';
            return;
        }

        $payload = ['status' => $this->closeStatus];

        if (! empty($this->closeReason)) {
            $payload['rejection_reason'] = $this->closeReason;
        }

        $response = Api::put('/recruitment/requisition/update/' . $this->closeId, $payload);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to update requisition.');
            return;
        }

        $this->flash = 'Requisition ' . ($this->closeStatus === 'cancelled' ? 'cancelled' : 'closed') . '.';
        $this->closeClose();
        $this->load();
    }

    protected function buildPayload(array $form): array
    {
        $skills = [];
        if (! empty($form['required_skills'])) {
            $skills = array_values(array_filter(array_map('trim', explode(',', $form['required_skills']))));
        }

        $payload = [
            'title' => $form['title'],
            'department' => $form['department'],
            'employment_type' => $form['employment_type'],
            'priority' => $form['priority'] ?: 'medium',
            'required_skills' => $skills,
        ];

        foreach (['employee_type_target', 'experience_level', 'salary_range_min', 'salary_range_max', 'salary_currency', 'reason_for_hire', 'hiring_manager_id', 'target_start_date', 'requisition_code'] as $field) {
            if (! empty($form[$field])) {
                $payload[$field] = $form[$field];
            }
        }

        return $payload;
    }

    public function canApprove(?array $row): bool
    {
        if (! $row) return false;

        if (! in_array(auth()->user()->role, ['admin', 'super_admin', 'director', 'operational_manager'], true)) {
            return false;
        }

        if (($row['status'] ?? '') !== 'pending_approval') {
            return false;
        }

        if ((int) ($row['created_by'] ?? 0) === (int) auth()->id()) {
            return false;
        }

        return true;
    }

    public function canUpdate(?array $row): bool
    {
        if (! $row) return false;

        return in_array($row['status'] ?? '', ['draft', 'pending_approval', 'approved', 'open'], true);
    }

    public function canClose(?array $row): bool
    {
        if (! $row) return false;

        return in_array($row['status'] ?? '', ['open', 'approved'], true);
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'draft' => 'muted',
            'pending_approval' => 'warning',
            'approved' => 'info',
            'open' => 'success',
            'closed' => 'muted',
            'cancelled' => 'danger',
            default => 'muted',
        };
    }

    public static function priorityTone(string $priority): string
    {
        return match ($priority) {
            'low' => 'muted',
            'medium' => 'info',
            'high' => 'warning',
            'critical' => 'danger',
            default => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.recruiter.requisitions')
            ->layout('layouts.portal', ['title' => 'Requisitions', 'breadcrumb' => 'Requisitions']);
    }
}