<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Livewire\Component;

class Projects extends Component
{
    public bool    $loading = true;
    public ?string $error   = null;

    public ?array $primary   = null;
    public array  $projects  = [];

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error   = null;

        $response = Api::get('/project/list/assigned');

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load projects.';
            $this->loading = false;
            return;
        }

        $this->primary  = $response['data']['primary_project']   ?? null;
        $this->projects = $response['data']['assigned_projects'] ?? [];

        $this->loading = false;
    }

    public static function statusTone(?string $status): string
    {
        return match ($status) {
            'active'    => 'success',
            'planning'  => 'blue',
            'on_hold'   => 'gold',
            'completed' => 'muted',
            'archived'  => 'muted',
            default     => 'muted',
        };
    }

    public static function statusLabel(?string $status): string
    {
        return match ($status) {
            'active'    => 'Active',
            'planning'  => 'Planning',
            'on_hold'   => 'On Hold',
            'completed' => 'Completed',
            'archived'  => 'Archived',
            default     => 'Assigned',
        };
    }

    public function render()
    {
        return view('livewire.employee.projects')
            ->layout('layouts.portal', ['title' => 'My Projects', 'breadcrumb' => 'Projects']);
    }
}