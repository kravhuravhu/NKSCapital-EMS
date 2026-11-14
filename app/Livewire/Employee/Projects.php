<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Livewire\Component;

class Projects extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public ?array $primary = null;
    public array $projects = [];
    public array $requests = [];

    public string $activeTab = 'projects';

    // request modal
    public bool $showRequest = false;
    public string $requestType = 'swap';
    public ?int $requestCurrentProjectId = null;
    public string $requestCurrentProjectName = '';
    public array $requestForm = [];
    public array $availableProjects = [];

    public function mount(): void
    {
        // load() fires from wire:init.
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $response = Api::get('/project/list/assigned');

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load projects.';
            $this->loading = false;
            return;
        }

        $this->primary = $response['data']['primary_project'] ?? null;
        $this->projects = $response['data']['assigned_projects'] ?? [];

        $req = Api::get('/project/requests');
        if ($req['ok']) {
            $this->requests = $req['data'] ?? [];
        }

        $this->loading = false;
    }

    public function switchTab(string $tab): void
    {
        if (in_array($tab, ['projects', 'requests'], true)) {
            $this->activeTab = $tab;
        }
    }

    public function openRequest(string $type, ?int $projectId = null): void
    {
        $this->error = null;
        $this->flash = null;

        if (! in_array($type, ['swap', 'change', 'concern', 'additional', 'meeting'], true)) {
            return;
        }

        $this->requestType = $type;
        $this->requestCurrentProjectId = $projectId;

        if ($projectId) {
            $match = collect($this->projects)->firstWhere('id', $projectId);
            $this->requestCurrentProjectName = $match['name'] ?? '';
        } else {
            $this->requestCurrentProjectName = '';
        }

        $this->requestForm = [
            'reason' => '',
            'target_project_id' => '',
            'requested_role' => '',
            'requested_allocation' => '',
            'priority' => 'medium',
        ];

        if (in_array($type, ['swap', 'additional'], true)) {
            $this->loadAvailableProjects();
        }

        $this->showRequest = true;
    }

    public function closeRequest(): void
    {
        $this->showRequest = false;
        $this->requestType = 'swap';
        $this->requestCurrentProjectId = null;
        $this->requestCurrentProjectName = '';
        $this->requestForm = [];
        $this->availableProjects = [];
    }

    protected function loadAvailableProjects(): void
    {
        $this->availableProjects = [];

        $response = Api::get('/project/list', ['per_page' => 100]);
        if (! $response['ok']) {
            return;
        }

        $all = $response['data']['data'] ?? [];
        $assignedIds = collect($this->projects)->pluck('id')->toArray();

        $this->availableProjects = array_values(array_filter(
            $all,
            fn ($p) => ! in_array($p['id'] ?? 0, $assignedIds, true)
                && in_array($p['status'] ?? '', ['planning', 'active'], true)
        ));
    }

    public function submitRequest(): void
    {
        $this->error = null;
        $this->flash = null;

        if (strlen(trim($this->requestForm['reason'] ?? '')) < 20) {
            $this->error = 'Please describe the reason in at least 20 characters.';
            return;
        }

        if ($this->requestType === 'swap' && empty($this->requestForm['target_project_id'])) {
            $this->error = 'Select the project you would like to move to.';
            return;
        }

        $payload = [
            'type' => $this->requestType,
            'reason' => $this->requestForm['reason'],
            'priority' => $this->requestForm['priority'] ?: 'medium',
        ];

        if ($this->requestCurrentProjectId) {
            $payload['current_project_id'] = $this->requestCurrentProjectId;
        }
        if (! empty($this->requestForm['target_project_id'])) {
            $payload['target_project_id'] = $this->requestForm['target_project_id'];
        }
        if (! empty($this->requestForm['requested_role'])) {
            $payload['requested_role'] = $this->requestForm['requested_role'];
        }
        if (! empty($this->requestForm['requested_allocation'])) {
            $payload['requested_allocation'] = $this->requestForm['requested_allocation'];
        }

        $response = Api::post('/project/request-change', $payload);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Failed to submit request.');
            return;
        }

        $this->flash = 'Request submitted. Your project manager has been notified.';
        $this->closeRequest();
        $this->load();
    }

    public function getRequestTypeLabelProperty(): string
    {
        return match ($this->requestType) {
            'swap' => 'Request project swap',
            'change' => 'Request role or allocation change',
            'concern' => 'Report a project concern',
            'additional' => 'Request additional project',
            'meeting' => 'Request meeting with project manager',
            default => 'Submit request',
        };
    }

    public function getPendingRequestCountProperty(): int
    {
        return count(array_filter(
            $this->requests,
            fn ($r) => in_array($r['status'] ?? '', ['pending', 'acknowledged'], true)
        ));
    }

    public static function statusTone(?string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'planning' => 'info',
            'on_hold' => 'warning',
            'completed', 'archived' => 'muted',
            default => 'muted',
        };
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'Active',
            'planning' => 'Planning',
            'on_hold' => 'On hold',
            'completed' => 'Completed',
            'archived' => 'Archived',
            default => 'Assigned',
        };
    }

    public static function requestStatusTone(?string $status): string
    {
        return match ($status) {
            'pending' => 'gold',
            'acknowledged' => 'blue',
            'approved' => 'success',
            'declined' => 'red',
            'cancelled' => 'muted',
            default => 'muted',
        };
    }

    public function daysUntilEnd(array $project): ?int
    {
        if (empty($project['end_date'])) return null;

        return (int) now()->startOfDay()->diffInDays(
            \Carbon\Carbon::parse($project['end_date'])->startOfDay(),
            false
        );
    }

    public function render()
    {
        return view('livewire.employee.projects')
            ->layout('layouts.portal', ['title' => 'My Projects', 'breadcrumb' => 'Projects']);
    }
}