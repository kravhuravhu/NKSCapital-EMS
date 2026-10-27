<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Livewire\Component;

class EmployeeTypes extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $tab = 'employee_types';

    public array $employeeTypes = [];
    public array $serviceTypes = [];

    // create employee type modal
    public bool $showCreate = false;
    public array $createForm = [];

    // workflow tester
    public bool $showWorkflowTest = false;
    public array $testEmployees = [];
    public string $testEmployeeId = '';
    public ?array $workflowResult = null;
    public bool $workflowTesting = false;

    // edit/delete modals
    public bool $showEdit = false;
    public ?int $editId = null;
    public array $editForm = [];

    // delete modal
    public bool $showDelete = false;
    public ?int $deleteId = null;
    public string $deleteName = '';

    public function mount(): void
    {
        $this->resetCreateForm();
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $types = Api::get('/admin/employee-type/list');
        if ($types['ok']) {
            $this->employeeTypes = $types['data'] ?? [];
        }

        $svc = Api::get('/admin/service-type/list');
        if ($svc['ok']) {
            $this->serviceTypes = $svc['data'] ?? [];
        }

        $this->loading = false;
    }

    public function selectTab(string $tab): void
    {
        if (in_array($tab, ['employee_types', 'service_types'], true)) {
            $this->tab = $tab;
        }
    }

    public function resetCreateForm(): void
    {
        $this->createForm = [
            'type_code' => '',
            'type_name' => '',
            'workflow_type' => 'PR_INTERNAL',
            'has_external_approval' => false,
            'has_internal_approval' => true,
            'description' => '',
        ];
    }

    public function openCreate(): void
    {
        // backend restricts type_code to PS/PR, and both already exist by default
        if (count($this->employeeTypes) >= 2) {
            $this->error = 'Both standard employee types (PS and PR) already exist. The backend does not currently support adding new types.';
            return;
        }

        $this->resetCreateForm();
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

        if (empty($this->createForm['type_code']) || empty($this->createForm['type_name'])) {
            $this->error = 'Type code and type name are required.';
            return;
        }

        $payload = array_filter(
            $this->createForm,
            fn ($v) => $v !== '' && $v !== null
        );

        $response = Api::post('/admin/employee-type/create', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to create employee type.');
            return;
        }

        $this->flash = 'Employee type created.';
        $this->closeCreate();
        $this->load();
    }

    public function openEdit(int $id): void
    {
        $type = collect($this->employeeTypes)->firstWhere('id', $id);
        if (!$type) {
            $this->error = 'Employee type not found.';
            return;
        }

        $this->editId = $id;
        $this->editForm = [
            'type_name' => $type['type_name'] ?? '',
            'workflow_type' => $type['workflow_type'] ?? 'PR_INTERNAL',
            'has_external_approval' => (bool) ($type['has_external_approval'] ?? false),
            'has_internal_approval' => (bool) ($type['has_internal_approval'] ?? true),
            'description' => $type['description'] ?? '',
        ];
        $this->error = null;
        $this->showEdit = true;
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
        if (!$this->editId) return;

        $payload = array_filter(
            $this->editForm,
            fn ($v) => $v !== '' && $v !== null
        );
        $payload['has_external_approval'] = (bool) ($this->editForm['has_external_approval'] ?? false);
        $payload['has_internal_approval'] = (bool) ($this->editForm['has_internal_approval'] ?? false);

        $response = Api::put('/admin/employee-type/update/' . $this->editId, $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to update employee type.');
            return;
        }

        $this->flash = 'Employee type updated.';
        $this->closeEdit();
        $this->load();
    }

    public function openDelete(int $id, string $name): void
    {
        $this->deleteId = $id;
        $this->deleteName = $name;
        $this->showDelete = true;
    }

    public function closeDelete(): void
    {
        $this->showDelete = false;
        $this->deleteId = null;
        $this->deleteName = '';
    }

    public function submitDelete(): void
    {
        $this->error = null;
        if (!$this->deleteId) return;

        $response = Api::delete('/admin/employee-type/delete/' . $this->deleteId);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to delete employee type.';
            $this->closeDelete();
            return;
        }

        $this->flash = 'Employee type deleted.';
        $this->closeDelete();
        $this->load();
    }

    // ============ workflow tester ============

    public function openWorkflowTest(): void
    {
        $this->error = null;
        $this->workflowResult = null;
        $this->testEmployeeId = '';

        $response = Api::get('/employee/list', [
            'per_page' => 200,
            'is_active' => 1,
        ]);

        if ($response['ok']) {
            $this->testEmployees = $response['data']['data'] ?? [];
        } else {
            $this->testEmployees = [];
        }

        $this->showWorkflowTest = true;
    }

    public function closeWorkflowTest(): void
    {
        $this->showWorkflowTest = false;
        $this->workflowResult = null;
        $this->testEmployeeId = '';
    }

    public function runWorkflowTest(): void
    {
        $this->error = null;
        $this->workflowResult = null;

        if (!$this->testEmployeeId) {
            $this->error = 'Select an employee to test.';
            return;
        }

        $this->workflowTesting = true;

        $response = Api::post('/admin/workflow/test/' . $this->testEmployeeId);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to test workflow.';
            $this->workflowTesting = false;
            return;
        }

        $this->workflowResult = $response['data'] ?? null;
        $this->workflowTesting = false;
    }

    public static function workflowTone(string $type): string
    {
        return $type === 'PS_EXTERNAL' ? 'gold' : 'blue';
    }

    public static function serviceIcon(string $code): string
    {
        return match (strtolower($code)) {
            'permanent'  => 'fa-solid fa-user-tie',
            'contractor' => 'fa-solid fa-file-signature',
            'temporary'  => 'fa-solid fa-hourglass-half',
            'intern'     => 'fa-solid fa-graduation-cap',
            default      => 'fa-solid fa-user',
        };
    }

    public function render()
    {
        return view('livewire.admin.employee-types')
            ->layout('layouts.portal', ['title' => 'Employee Types', 'breadcrumb' => 'Employee Types']);
    }
}