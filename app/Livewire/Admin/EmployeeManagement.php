<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EmployeeManagement extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $search = '';
    public string $roleFilter = '';
    public string $employeeTypeFilter = '';
    public string $serviceTypeFilter = '';
    public string $activeFilter = '';
    public int $page = 1;

    public array $employees = [];
    public array $pagination = [];

    // reference data for pickers
    public array $managers = [];
    public array $clients = [];
    public array $projects = [];
    public array $leaveConfigs = [];

    // view modal
    public bool $showView = false;
    public ?array $viewProfile = null;
    public ?array $viewHistory = null;
    public string $viewTab = 'profile';

    // create modal
    public bool $showCreate = false;
    public array $createForm = [];
    public bool $creating = false;

    // post-create credentials modal
    public bool $showCredentials = false;
    public ?array $createdUser = null;
    public string $createdPassword = '';
    public bool $resetEmailSent = false;

    // edit modal
    public bool $showEdit = false;
    public ?int $editId = null;
    public array $editForm = [];

    // reset password modal
    public bool $showResetPassword = false;
    public ?int $resetId = null;
    public string $resetEmail = '';
    public bool $resetSent = false;

    // deactivate modal
    public bool $showDeactivate = false;
    public ?int $deactivateId = null;
    public string $deactivateName = '';
    public string $deactivatePassword = '';

    public function mount(): void
    {
        $this->resetCreateForm();
        $this->loadReferenceData();
        $this->load();
    }

    public function loadReferenceData(): void
    {
        // managers: any manager/director/admin
        $mgrs = Api::get('/employee/list', ['per_page' => 200, 'is_active' => 1]);
        if ($mgrs['ok']) {
            $all = $mgrs['data']['data'] ?? [];
            $this->managers = array_values(array_filter(
                $all,
                fn ($u) => in_array($u['role'] ?? '', ['manager', 'director', 'admin'], true)
            ));
        }

        // leave configs: used to seed new employee balances
        $cfg = Api::get('/admin/leave-config');
        if ($cfg['ok']) {
            $rows = $cfg['data'] ?? [];
            $this->leaveConfigs = collect($rows)->keyBy('leave_type')->toArray();
        }

        // clients and projects for PS/PR assignment pickers
        $cl = Api::get('/client/list', ['per_page' => 100, 'is_active' => 1]);
        if ($cl['ok']) {
            $this->clients = $cl['data']['data'] ?? [];
        }

        $pr = Api::get('/project/list', ['per_page' => 100]);
        if ($pr['ok']) {
            $this->projects = $pr['data']['data'] ?? [];
        }
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['per_page' => 20];

        if ($this->search !== '')             $params['search'] = $this->search;
        if ($this->roleFilter !== '')         $params['role'] = $this->roleFilter;
        if ($this->employeeTypeFilter !== '') $params['employee_type'] = $this->employeeTypeFilter;
        if ($this->serviceTypeFilter !== '')  $params['service_type'] = $this->serviceTypeFilter;
        if ($this->activeFilter !== '')       $params['is_active'] = $this->activeFilter;
        if ($this->page > 1)                  $params['page'] = $this->page;

        $response = Api::get('/employee/list', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load employees.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $this->employees = $paginator['data'] ?? [];
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
        $this->roleFilter = '';
        $this->employeeTypeFilter = '';
        $this->serviceTypeFilter = '';
        $this->activeFilter = '';
        $this->page = 1;
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    // ============ VIEW ============

    public function openView(int $id): void
    {
        $this->error = null;
        $this->viewProfile = null;
        $this->viewHistory = null;
        $this->viewTab = 'profile';
        $this->showView = true;

        $p = Api::get('/employee/profile/' . $id);
        if ($p['ok']) {
            $this->viewProfile = $p['data'] ?? null;
        }

        $h = Api::get('/employee/history/' . $id);
        if ($h['ok']) {
            $this->viewHistory = $h['data'] ?? null;
        }
    }

    public function closeView(): void
    {
        $this->showView = false;
        $this->viewProfile = null;
        $this->viewHistory = null;
        $this->viewTab = 'profile';
    }

    public function switchViewTab(string $tab): void
    {
        if (in_array($tab, ['profile', 'leave', 'contracts', 'activity'], true)) {
            $this->viewTab = $tab;
        }
    }

    // ============ CREATE ============

    public function resetCreateForm(): void
    {
        $this->createForm = [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'id_number' => '',
            'phone' => '',
            'employee_type' => 'pr',
            'service_type' => 'permanent',
            'role' => 'employee',
            'employee_number' => '',
            'department' => '',
            'position' => '',
            'manager_id' => '',
            'client_id' => '',
            'project_id' => '',
            'hire_date' => now()->format('Y-m-d'),
            'address' => '',
            'emergency_contact_name' => '',
            'emergency_contact_phone' => '',
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

    public function submitCreate(): void
    {
        $this->error = null;
        $this->flash = null;
        $this->creating = true;

        if (empty($this->createForm['first_name']) || empty($this->createForm['last_name'])) {
            $this->error = 'First and last name are required.';
            $this->creating = false;
            return;
        }
        if (empty($this->createForm['email'])) {
            $this->error = 'Email is required.';
            $this->creating = false;
            return;
        }
        if ($this->createForm['employee_type'] === 'ps' && empty($this->createForm['client_id'])) {
            $this->error = 'PS employees require a client assignment.';
            $this->creating = false;
            return;
        }
        if ($this->createForm['employee_type'] === 'pr' && empty($this->createForm['project_id'])) {
            $this->error = 'PR employees require a project assignment.';
            $this->creating = false;
            return;
        }

        $payload = array_filter(
            $this->createForm,
            fn ($v) => $v !== '' && $v !== null
        );

        $response = Api::post('/employee/create', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to create employee.');
            $this->creating = false;
            return;
        }

        $data = $response['data'] ?? [];
        $user = $data['user'] ?? null;
        $tempPassword = $data['temporary_password'] ?? '';

        if (!$user || empty($user['id'])) {
            $this->error = 'Employee was created but the response was incomplete.';
            $this->creating = false;
            return;
        }

        // seed leave balances from leave_configs — backend does not do this yet
        $this->initLeaveBalances((int) $user['id']);

        // open credentials modal
        $this->createdUser = $user;
        $this->createdPassword = $tempPassword;
        $this->resetEmailSent = false;
        $this->showCredentials = true;

        $this->creating = false;
        $this->closeCreate();
        $this->load();
    }

    protected function initLeaveBalances(int $userId): void
    {
        $annual = (float) ($this->leaveConfigs['annual']['default_entitlement'] ?? 0);
        $sick = (float) ($this->leaveConfigs['sick']['default_entitlement'] ?? 0);
        $family = (float) ($this->leaveConfigs['family']['default_entitlement'] ?? 0);

        if ($annual <= 0 && $sick <= 0 && $family <= 0) {
            return;
        }

        // reset-employee accepts only annual, sick, family — the other four
        // default to 0 and are adjusted manually when needed
        Api::put('/admin/leave-config/reset-employee/' . $userId, [
            'annual_balance' => $annual,
            'sick_balance' => $sick,
            'family_balance' => $family,
            'reason' => 'Initial balance set on employee creation',
        ]);
    }

    public function closeCredentials(): void
    {
        $this->showCredentials = false;
        $this->createdUser = null;
        $this->createdPassword = '';
        $this->resetEmailSent = false;
    }

    public function sendResetEmailFromCredentials(): void
    {
        if (!$this->createdUser || empty($this->createdUser['email'])) {
            return;
        }

        $response = Api::post('/auth/password/forgot', [
            'email' => $this->createdUser['email'],
        ]);

        if ($response['ok']) {
            $this->resetEmailSent = true;
            $this->flash = 'Password reset email sent to ' . $this->createdUser['email'];
        } else {
            $this->error = $response['message'] ?? 'Failed to send reset email.';
        }
    }

    // ============ EDIT ============

    public function openEdit(int $id): void
    {
        $row = collect($this->employees)->firstWhere('id', $id);

        if (!$row) {
            $this->error = 'Employee not found in the current view.';
            return;
        }

        $this->editId = $id;
        $this->editForm = [
            'first_name' => $row['first_name'] ?? '',
            'last_name' => $row['last_name'] ?? '',
            'email' => $row['email'] ?? '',
            'id_number' => $row['id_number'] ?? '',
            'phone' => $row['phone'] ?? '',
            'employee_type' => $row['employee_type'] ?? '',
            'service_type' => $row['service_type'] ?? '',
            'role' => $row['role'] ?? 'employee',
            'department' => $row['department'] ?? '',
            'position' => $row['position'] ?? '',
            'manager_id' => $row['manager_id'] ?? '',
            'client_id' => $row['client_id'] ?? '',
            'project_id' => $row['project_id'] ?? '',
            'hire_date' => $row['hire_date'] ?? '',
            'address' => $row['address'] ?? '',
            'emergency_contact_name' => $row['emergency_contact_name'] ?? '',
            'emergency_contact_phone' => $row['emergency_contact_phone'] ?? '',
            'is_active' => (bool) ($row['is_active'] ?? true),
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

        if (!$this->editId) {
            return;
        }

        $payload = array_filter(
            $this->editForm,
            fn ($v) => $v !== '' && $v !== null
        );

        // is_active is boolean — keep as true/false even when false
        if (isset($this->editForm['is_active'])) {
            $payload['is_active'] = (bool) $this->editForm['is_active'];
        }

        $response = Api::put('/employee/update-full/' . $this->editId, $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to update employee.');
            return;
        }

        $this->flash = 'Employee updated.';
        $this->closeEdit();
        $this->load();
    }

    // ============ RESET PASSWORD ============

    public function openResetPassword(int $id): void
    {
        $row = collect($this->employees)->firstWhere('id', $id);
        if (!$row) {
            $this->error = 'Employee not found.';
            return;
        }

        $this->resetId = $id;
        $this->resetEmail = $row['email'] ?? '';
        $this->resetSent = false;
        $this->error = null;
        $this->showResetPassword = true;
    }

    public function closeResetPassword(): void
    {
        $this->showResetPassword = false;
        $this->resetId = null;
        $this->resetEmail = '';
        $this->resetSent = false;
    }

    public function submitResetPassword(): void
    {
        $this->error = null;

        if (!$this->resetEmail) {
            $this->error = 'No email address on file.';
            return;
        }

        $response = Api::post('/auth/password/forgot', [
            'email' => $this->resetEmail,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to send reset email.';
            return;
        }

        $this->resetSent = true;
        $this->flash = 'Password reset email sent to ' . $this->resetEmail;
    }

    // ============ DEACTIVATE ============

    public function openDeactivate(int $id): void
    {
        $row = collect($this->employees)->firstWhere('id', $id);
        if (!$row) {
            $this->error = 'Employee not found.';
            return;
        }

        $this->deactivateId = $id;
        $this->deactivateName = trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
        $this->deactivatePassword = '';
        $this->error = null;
        $this->showDeactivate = true;
        $this->closeView();
    }

    public function closeDeactivate(): void
    {
        $this->showDeactivate = false;
        $this->deactivateId = null;
        $this->deactivateName = '';
        $this->deactivatePassword = '';
    }

    public function submitDeactivate(): void
    {
        $this->error = null;

        if (empty($this->deactivatePassword)) {
            $this->error = 'Enter your admin password to confirm.';
            return;
        }

        // verify the acting admin's own password before the destructive action
        $valid = Auth::validate([
            'email' => auth()->user()->email,
            'password' => $this->deactivatePassword,
        ]);

        if (!$valid) {
            $this->error = 'Password is incorrect.';
            return;
        }

        $response = Api::post('/employee/deactivate/' . $this->deactivateId);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to deactivate employee.';
            return;
        }

        $this->flash = $this->deactivateName . ' deactivated.';
        $this->closeDeactivate();
        $this->load();
    }

    public static function roleTone(string $role): string
    {
        return match ($role) {
            'admin'     => 'red',
            'director'  => 'gold',
            'manager'   => 'blue',
            'recruiter' => 'purple',
            default     => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.admin.employee-management')
            ->layout('layouts.portal', ['title' => 'Employees', 'breadcrumb' => 'Employees']);
    }
}