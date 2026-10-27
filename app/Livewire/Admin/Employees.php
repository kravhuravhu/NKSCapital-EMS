<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Livewire\Component;

class Employees extends Component
{
    public bool $loading = true;
    public bool $saving = false;
    public ?string $error = null;
    public ?string $flash = null;

    public string $search = '';
    public string $roleFilter = '';
    public string $typeFilter = '';
    public string $statusFilter = '';
    public int $page = 1;

    public array $employees = [];
    public array $pagination = [];

    public array $managers = [];
    public array $clients = [];
    public array $projects = [];

    public bool $showForm = false;
    public bool $editing = false;
    public ?int $editingId = null;
    public array $form = [];

    public bool $showDeactivate = false;
    public ?int $deactivateId = null;
    public string $deactivateName = '';

    public bool $showSuccess = false;
    public array $successData = [];

    public function mount(): void
    {
        $this->resetForm();
        $this->loadRefData();
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['page' => $this->page, 'per_page' => 15];

        if ($this->search !== '')      $params['search'] = $this->search;
        if ($this->roleFilter !== '')  $params['role'] = $this->roleFilter;
        if ($this->typeFilter !== '')  $params['employee_type'] = $this->typeFilter;
        if ($this->statusFilter !== '') $params['is_active'] = $this->statusFilter;

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

    protected function loadRefData(): void
    {
        // managers = users with manager/director/admin role
        $u = Api::get('/employee/list', ['per_page' => 100, 'is_active' => 1]);
        $users = $u['ok'] ? ($u['data']['data'] ?? []) : [];
        $this->managers = array_values(array_filter(
            $users,
            fn ($usr) => in_array($usr['role'] ?? '', ['manager', 'director', 'admin'], true)
        ));

        $c = Api::get('/client/list', ['per_page' => 100, 'is_active' => 1]);
        $this->clients = $c['ok'] ? ($c['data']['data'] ?? []) : [];

        $p = Api::get('/project/list', ['per_page' => 100]);
        $this->projects = $p['ok'] ? ($p['data']['data'] ?? []) : [];
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->load();
    }

    public function updatedRoleFilter(): void
    {
        $this->page = 1;
        $this->load();
    }

    public function updatedTypeFilter(): void
    {
        $this->page = 1;
        $this->load();
    }

    public function updatedStatusFilter(): void
    {
        $this->page = 1;
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    protected function resetForm(): void
    {
        $this->form = [
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'id_number' => '',
            'department' => '',
            'position' => '',
            'role' => 'employee',
            'employee_type' => 'pr',
            'service_type' => 'permanent',
            'manager_id' => '',
            'client_id' => '',
            'project_id' => '',
            'hire_date' => now()->format('Y-m-d'),
            'address' => '',
            'emergency_contact_name' => '',
            'emergency_contact_phone' => '',
            'is_active' => true,
        ];
        $this->editingId = null;
        $this->editing = false;
    }

    public function openCreate(): void
    {
        $this->resetForm();
        $this->error = null;
        $this->showForm = true;
    }

    public function openEdit(int $id): void
    {
        $this->error = null;

        $response = Api::get('/employee/show/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load employee.';
            return;
        }

        $e = $response['data'] ?? [];

        $this->form = [
            'first_name' => $e['first_name'] ?? '',
            'last_name' => $e['last_name'] ?? '',
            'email' => $e['email'] ?? '',
            'phone' => $e['phone'] ?? '',
            'id_number' => $e['id_number'] ?? '',
            'department' => $e['department'] ?? '',
            'position' => $e['position'] ?? '',
            'role' => $e['role'] ?? 'employee',
            'employee_type' => $e['employee_type'] ?? 'pr',
            'service_type' => $e['service_type'] ?? 'permanent',
            'manager_id' => (string) ($e['manager_id'] ?? ''),
            'client_id' => (string) ($e['client_id'] ?? ''),
            'project_id' => (string) ($e['project_id'] ?? ''),
            'hire_date' => $e['hire_date'] ?? '',
            'address' => $e['address'] ?? '',
            'emergency_contact_name' => $e['emergency_contact_name'] ?? '',
            'emergency_contact_phone' => $e['emergency_contact_phone'] ?? '',
            'is_active' => (bool) ($e['is_active'] ?? true),
        ];

        $this->editingId = $id;
        $this->editing = true;
        $this->showForm = true;
    }

    public function closeForm(): void
    {
        $this->showForm = false;
        $this->resetForm();
    }

    public function save(): void
    {
        $this->error = null;
        $this->flash = null;

        // client-side prerequisite check so we don't hit the API needlessly
        if ($this->form['employee_type'] === 'ps' && empty($this->form['client_id'])) {
            $this->error = 'PS employees must have a client assigned.';
            return;
        }
        if ($this->form['employee_type'] === 'pr' && empty($this->form['project_id'])) {
            $this->error = 'PR employees must have a project assigned.';
            return;
        }

        $this->saving = true;

        if ($this->editing) {
            $payload = $this->form;
            unset($payload['is_active']); // is_active is handled separately

            // convert empty strings to nulls so `nullable|exists` passes
            foreach (['manager_id', 'client_id', 'project_id', 'hire_date', 'phone', 'id_number'] as $key) {
                if ($payload[$key] === '') {
                    $payload[$key] = null;
                }
            }

            $response = Api::put('/employee/update-full/' . $this->editingId, $payload);
        } else {
            $payload = $this->form;

            foreach (['manager_id', 'client_id', 'project_id', 'hire_date', 'phone', 'id_number'] as $key) {
                if ($payload[$key] === '') {
                    $payload[$key] = null;
                }
            }

            $response = Api::post('/employee/create', $payload);
        }

        $this->saving = false;

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to save employee.');
            return;
        }

        $data = $response['data'] ?? [];

        if (!$this->editing) {
            // show the temporary password the backend generated
            $this->successData = [
                'name' => trim(($data['user']['first_name'] ?? '') . ' ' . ($data['user']['last_name'] ?? '')),
                'employee_number' => $data['user']['employee_number'] ?? '',
                'email' => $data['user']['email'] ?? '',
                'temporary_password' => $data['temporary_password'] ?? '',
            ];
            $this->showSuccess = true;
            $this->flash = 'Employee created.';
        } else {
            $this->flash = 'Employee updated.';
        }

        $this->closeForm();
        $this->load();
        $this->loadRefData();
    }

    public function openDeactivate(int $id, string $name): void
    {
        $this->deactivateId = $id;
        $this->deactivateName = $name;
        $this->showDeactivate = true;
    }

    public function closeDeactivate(): void
    {
        $this->showDeactivate = false;
        $this->deactivateId = null;
        $this->deactivateName = '';
    }

    public function submitDeactivate(): void
    {
        $this->error = null;
        $this->flash = null;

        $response = Api::post('/employee/deactivate/' . $this->deactivateId);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to deactivate employee.';
            return;
        }

        $this->flash = 'Employee deactivated.';
        $this->closeDeactivate();
        $this->load();
    }

    public function reactivate(int $id): void
    {
        $this->error = null;
        $this->flash = null;

        $response = Api::post('/employee/reactivate/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to reactivate employee.';
            return;
        }

        $this->flash = 'Employee reactivated.';
        $this->load();
    }

    public function closeSuccess(): void
    {
        $this->showSuccess = false;
        $this->successData = [];
    }

    public static function roleTone(string $role): string
    {
        return match ($role) {
            'admin', 'super_admin' => 'red',
            'director' => 'gold',
            'manager'  => 'blue',
            'recruiter'=> 'purple',
            'employee' => 'muted',
            default    => 'muted',
        };
    }

    public static function typeTone(string $type): string
    {
        return match ($type) {
            'ps' => 'gold',
            'pr' => 'blue',
            default => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.admin.employees')
            ->layout('layouts.portal', ['title' => 'Employees', 'breadcrumb' => 'Employees']);
    }
}