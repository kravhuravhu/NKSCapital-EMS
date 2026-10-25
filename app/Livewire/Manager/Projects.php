<?php

namespace App\Livewire\Manager;

use App\Support\Api;
use Livewire\Component;

class Projects extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $tab = 'my';
    public string $search = '';
    public string $statusFilter = '';

    public array $projects = [];
    public array $pagination = [];

    public bool $showDetail = false;
    public ?array $detail = null;

    public bool $showAssign = false;
    public array $employees = [];
    public array $assignForm = [];

    public bool $showRemove = false;
    public ?int $removeUserId = null;
    public string $removeName = '';

    public bool $allEmployeesAssigned = false;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['per_page' => 12];

        if ($this->search) {
            $params['search'] = $this->search;
        }

        if ($this->statusFilter) {
            $params['status'] = $this->statusFilter;
        }

        // 'my' = projects this manager runs; 'all' = browse every project
        if ($this->tab === 'my') {
            $params['project_manager_id'] = auth()->id();
        }

        $response = Api::get('/project/list', $params);

        if (!$response['ok']) {
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

        $this->loading = false;
    }

    public function selectTab(string $tab): void
    {
        if (!in_array($tab, ['my', 'all'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->load();
    }

    public function updatedSearch(): void
    {
        $this->load();
    }

    public function updatedStatusFilter(): void
    {
        $this->load();
    }

    public function openDetail(int $id): void
    {
        $this->error = null;

        $response = Api::get('/project/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load project.';
            return;
        }

        $this->detail = $response['data'] ?? null;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->detail = null;
    }

    public function openAssign(): void
    {
        // only role=employee users are eligible for project assignment
        $response = Api::get('/employee/list', [
            'per_page' => 100,
            'is_active' => 1,
            'role' => 'employee',
        ]);

        $raw = $response['ok'] ? ($response['data']['data'] ?? []) : [];

        // drop anyone already assigned to this project so the picker only offers valid choices
        $assignedIds = collect($this->detail['assigned_employees'] ?? [])
            ->pluck('user.id')
            ->filter()
            ->toArray();

        $this->employees = array_values(array_filter(
            $raw,
            fn ($emp) => !in_array($emp['id'], $assignedIds, true)
        ));

        $this->allEmployeesAssigned = count($raw) > 0 && count($this->employees) === 0;

        $this->assignForm = [
            'user_id' => '',
            'role' => 'team_member',
            'allocation_percentage' => 100,
            'assigned_date' => now()->format('Y-m-d'),
            'end_date' => '',
        ];

        $this->error = null;
        $this->showAssign = true;
    }

    public function closeAssign(): void
    {
        $this->showAssign = false;
        $this->assignForm = [];
    }

    public function submitAssign(): void
    {
        $this->error = null;
        $this->flash = null;

        $projectId = $this->detail['project']['id'] ?? null;

        if (!$projectId) {
            $this->error = 'Project is not loaded.';
            return;
        }

        if (empty($this->assignForm['user_id'])) {
            $this->error = 'Please select an employee.';
            return;
        }

        $payload = [
            'project_id' => $projectId,
            'user_id' => $this->assignForm['user_id'],
            'role' => $this->assignForm['role'],
            'allocation_percentage' => (int) $this->assignForm['allocation_percentage'],
            'assigned_date' => $this->assignForm['assigned_date'],
        ];

        if (!empty($this->assignForm['end_date'])) {
            $payload['end_date'] = $this->assignForm['end_date'];
        }

        $response = Api::post('/project/assign-employee', $payload);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to assign employee.');
            return;
        }

        $this->flash = 'Employee assigned to project.';
        $this->closeAssign();
        $this->reloadDetail($projectId);
    }

    public function openRemove(int $userId, string $name): void
    {
        $this->removeUserId = $userId;
        $this->removeName = $name;
        $this->showRemove = true;
    }

    public function closeRemove(): void
    {
        $this->showRemove = false;
        $this->removeUserId = null;
        $this->removeName = '';
    }

    public function submitRemove(): void
    {
        $this->error = null;
        $this->flash = null;

        $projectId = $this->detail['project']['id'] ?? null;

        if (!$projectId || !$this->removeUserId) {
            $this->error = 'Project or employee not specified.';
            return;
        }

        $response = Api::post('/project/remove-employee', [
            'project_id' => $projectId,
            'user_id' => $this->removeUserId,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to remove employee.';
            return;
        }

        $this->flash = 'Employee removed from project.';
        $this->closeRemove();
        $this->reloadDetail($projectId);
    }

    protected function reloadDetail(int $id): void
    {
        $response = Api::get('/project/' . $id);

        if ($response['ok']) {
            $this->detail = $response['data'] ?? null;
        }

        $this->load();
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'planning' => 'blue',
            'on_hold' => 'gold',
            'completed' => 'muted',
            'archived' => 'muted',
            default => 'muted',
        };
    }

    public static function projectTypeTone(string $type): string
    {
        return match ($type) {
            'client' => 'gold',
            'internal' => 'purple',
            'hybrid' => 'blue',
            default => 'muted',
        };
    }

    public static function allocationTone(float $percentage): string
    {
        if ($percentage >= 80) return 'success';
        if ($percentage >= 50) return 'gold';
        return 'muted';
    }

    public function render()
    {
        return view('livewire.manager.projects')
            ->layout('layouts.portal', ['title' => 'Projects', 'breadcrumb' => 'Projects']);
    }
}