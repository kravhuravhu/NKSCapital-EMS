<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Projects extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $search = '';
    public string $statusFilter = '';
    public string $typeFilter = '';
    public string $clientFilter = '';
    public int $page = 1;

    public array $projects = [];
    public array $pagination = [];
    public array $clients = [];
    public array $managers = [];
    public array $assignableUsers = [];

    public array $stats = [
        'total' => 0,
        'active' => 0,
        'planning' => 0,
        'archived' => 0,
    ];

    public bool $showView = false;
    public ?array $viewProject = null;
    public bool $hoursLoading = false;
    public ?array $hoursData = null;

    public bool $showCreate = false;
    public array $createForm = [];

    public bool $showEdit = false;
    public ?int $editId = null;
    public array $editForm = [];

    public bool $showAssignManager = false;
    public ?int $assignManagerProjectId = null;
    public string $assignManagerId = '';

    public bool $showAssignEmployee = false;
    public ?int $assignEmpProjectId = null;
    public array $assignEmpForm = [];

    public bool $showRemoveEmployee = false;
    public ?int $removeProjectId = null;
    public ?int $removeUserId = null;
    public string $removeName = '';

    public bool $showArchive = false;
    public ?int $archiveId = null;
    public string $archiveName = '';
    public string $archivePassword = '';

    public function mount(): void
    {
        $this->resetCreateForm();
        $this->loadReferenceData();
        $this->load();
    }

    protected function loadReferenceData(): void
    {
        $cl = Api::get('/client/list', ['is_active' => 1, 'per_page' => 200]);
        if ($cl['ok']) {
            $this->clients = $cl['data']['data'] ?? [];
        }

        $usr = Api::get('/employee/list', ['is_active' => 1, 'per_page' => 300]);
        if ($usr['ok']) {
            $all = $usr['data']['data'] ?? [];

            $this->managers = array_values(array_filter(
                $all,
                fn ($u) => in_array($u['role'] ?? '', ['manager', 'director', 'operational_manager', 'admin'], true)
            ));

            $this->assignableUsers = array_values(array_filter(
                $all,
                fn ($u) => in_array($u['role'] ?? '', ['employee', 'manager', 'recruiter', 'director', 'operational_manager'], true)
            ));
        }
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['per_page' => 20];

        if ($this->search !== '')       $params['search'] = $this->search;
        if ($this->statusFilter !== '') $params['status'] = $this->statusFilter;
        if ($this->typeFilter !== '')   $params['project_type'] = $this->typeFilter;
        if ($this->clientFilter !== '') $params['client_id'] = $this->clientFilter;
        if ($this->page > 1)            $params['page'] = $this->page;

        $response = Api::get('/project/list', $params);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load projects.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $this->projects = $paginator['data'] ?? [];
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
        $stats = ['total' => 0, 'active' => 0, 'planning' => 0, 'archived' => 0];

        foreach ($this->projects as $p) {
            $stats['total']++;

            match ($p['status'] ?? '') {
                'active' => $stats['active']++,
                'planning' => $stats['planning']++,
                'archived' => $stats['archived']++,
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
        $this->typeFilter = '';
        $this->clientFilter = '';
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
        $this->hoursData = null;
        $this->hoursLoading = false;

        $response = Api::get('/project/' . $id);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load project.';
            return;
        }

        $this->viewProject = $response['data'] ?? null;
        $this->showView = true;
    }

    public function closeView(): void
    {
        $this->showView = false;
        $this->viewProject = null;
        $this->hoursData = null;
    }

    public function loadHours(): void
    {
        $projectId = $this->viewProject['project']['id'] ?? null;

        if (! $projectId) return;

        $this->hoursLoading = true;

        $response = Api::get('/project/hours/' . $projectId);

        if ($response['ok']) {
            $this->hoursData = $response['data'] ?? null;
        } else {
            $this->hoursData = ['error' => $response['message'] ?? 'Unable to load hours.'];
        }

        $this->hoursLoading = false;
    }

    public function resetCreateForm(): void
    {
        $this->createForm = [
            'client_id' => '',
            'project_code' => '',
            'name' => '',
            'description' => '',
            'project_type' => 'client',
            'budgeted_hours' => '',
            'hourly_rate' => '',
            'start_date' => now()->format('Y-m-d'),
            'end_date' => '',
            'project_manager_id' => '',
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

        foreach (['client_id', 'project_code', 'name', 'project_type'] as $field) {
            if (empty($this->createForm[$field])) {
                $this->error = 'Client, project code, name, and type are required.';
                return;
            }
        }

        $payload = array_filter($this->createForm, fn ($v) => $v !== '' && $v !== null);

        $response = Api::post('/project/create', $payload);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to create project.');
            return;
        }

        $this->flash = 'Project created.';
        $this->closeCreate();
        $this->load();
    }

    public function openEdit(int $id): void
    {
        $row = collect($this->projects)->firstWhere('id', $id);

        if (! $row) {
            $this->error = 'Project not found.';
            return;
        }

        $this->editId = $id;
        $this->editForm = [
            'client_id' => $row['client_id'] ?? '',
            'project_code' => $row['project_code'] ?? '',
            'name' => $row['name'] ?? '',
            'description' => $row['description'] ?? '',
            'project_type' => $row['project_type'] ?? 'client',
            'budgeted_hours' => $row['budgeted_hours'] ?? '',
            'hourly_rate' => $row['hourly_rate'] ?? '',
            'start_date' => $row['start_date'] ?? '',
            'end_date' => $row['end_date'] ?? '',
            'project_manager_id' => $row['project_manager_id'] ?? '',
            'status' => $row['status'] ?? 'planning',
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

        $payload = array_filter($this->editForm, fn ($v) => $v !== '' && $v !== null);

        $response = Api::put('/project/update/' . $this->editId, $payload);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to update project.');
            return;
        }

        $this->flash = 'Project updated.';
        $this->closeEdit();
        $this->load();
    }

    public function openAssignManager(int $projectId, ?int $currentManagerId = null): void
    {
        $this->assignManagerProjectId = $projectId;
        $this->assignManagerId = $currentManagerId ? (string) $currentManagerId : '';
        $this->error = null;
        $this->showAssignManager = true;
    }

    public function closeAssignManager(): void
    {
        $this->showAssignManager = false;
        $this->assignManagerProjectId = null;
        $this->assignManagerId = '';
    }

    public function submitAssignManager(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->assignManagerId)) {
            $this->error = 'Select a manager.';
            return;
        }

        $response = Api::post('/project/assign-manager', [
            'project_id' => $this->assignManagerProjectId,
            'project_manager_id' => $this->assignManagerId,
        ]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to assign manager.');
            return;
        }

        $this->flash = 'Project manager assigned.';
        $this->closeAssignManager();

        if ($this->showView) {
            $this->openView($this->viewProject['project']['id'] ?? 0);
        }

        $this->load();
    }

    public function openAssignEmployee(int $projectId): void
    {
        $this->assignEmpProjectId = $projectId;
        $this->assignEmpForm = [
            'user_id' => '',
            'role' => 'team_member',
            'allocation_percentage' => 100,
            'assigned_date' => now()->format('Y-m-d'),
            'end_date' => '',
        ];
        $this->error = null;
        $this->showAssignEmployee = true;
    }

    public function closeAssignEmployee(): void
    {
        $this->showAssignEmployee = false;
        $this->assignEmpProjectId = null;
        $this->assignEmpForm = [];
    }

    public function submitAssignEmployee(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->assignEmpForm['user_id'])) {
            $this->error = 'Select an employee.';
            return;
        }

        $payload = [
            'project_id' => $this->assignEmpProjectId,
            'user_id' => $this->assignEmpForm['user_id'],
            'role' => $this->assignEmpForm['role'],
            'allocation_percentage' => (int) $this->assignEmpForm['allocation_percentage'],
            'assigned_date' => $this->assignEmpForm['assigned_date'],
        ];

        if (! empty($this->assignEmpForm['end_date'])) {
            $payload['end_date'] = $this->assignEmpForm['end_date'];
        }

        $response = Api::post('/project/assign-employee', $payload);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to assign employee.');
            return;
        }

        $this->flash = 'Employee assigned.';
        $this->closeAssignEmployee();

        if ($this->showView) {
            $this->openView($this->viewProject['project']['id'] ?? 0);
        }

        $this->load();
    }

    public function openRemoveEmployee(int $projectId, int $userId, string $name): void
    {
        $this->removeProjectId = $projectId;
        $this->removeUserId = $userId;
        $this->removeName = $name;
        $this->error = null;
        $this->showRemoveEmployee = true;
    }

    public function closeRemoveEmployee(): void
    {
        $this->showRemoveEmployee = false;
        $this->removeProjectId = null;
        $this->removeUserId = null;
        $this->removeName = '';
    }

    public function submitRemoveEmployee(): void
    {
        $this->error = null;
        $this->flash = null;

        $response = Api::post('/project/remove-employee', [
            'project_id' => $this->removeProjectId,
            'user_id' => $this->removeUserId,
        ]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed to remove employee.';
            return;
        }

        $this->flash = 'Employee removed from project.';
        $this->closeRemoveEmployee();

        if ($this->showView) {
            $this->openView($this->viewProject['project']['id'] ?? 0);
        }

        $this->load();
    }

    public function openArchive(int $id): void
    {
        $row = collect($this->projects)->firstWhere('id', $id);

        if (! $row) {
            $this->error = 'Project not found.';
            return;
        }

        $this->archiveId = $id;
        $this->archiveName = $row['name'] ?? '';
        $this->archivePassword = '';
        $this->error = null;
        $this->showArchive = true;
        $this->closeView();
    }

    public function closeArchive(): void
    {
        $this->showArchive = false;
        $this->archiveId = null;
        $this->archiveName = '';
        $this->archivePassword = '';
    }

    public function submitArchive(): void
    {
        $this->error = null;

        if (empty($this->archivePassword)) {
            $this->error = 'Enter your admin password to confirm.';
            return;
        }

        if (! Auth::validate([
            'email' => auth()->user()->email,
            'password' => $this->archivePassword,
        ])) {
            $this->error = 'Password is incorrect.';
            return;
        }

        $response = Api::post('/project/archive/' . $this->archiveId);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed to archive project.';
            return;
        }

        $this->flash = $this->archiveName . ' archived.';
        $this->closeArchive();
        $this->load();
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'planning' => 'info',
            'on_hold' => 'warning',
            'completed' => 'muted',
            'archived' => 'muted',
            default => 'muted',
        };
    }

    public static function typeTone(string $type): string
    {
        return match ($type) {
            'client' => 'gold',
            'internal' => 'purple',
            'hybrid' => 'info',
            default => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.admin.projects')
            ->layout('layouts.portal', ['title' => 'Projects', 'breadcrumb' => 'Projects']);
    }
}