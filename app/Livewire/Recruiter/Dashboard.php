<?php

namespace App\Livewire\Recruiter;

use App\Support\Api;
use Livewire\Component;
use App\Livewire\Concerns\HasFlashMessages;

class Dashboard extends Component
{
    use HasFlashMessages;
    
    public bool  $loading = true;
    public ?string $error = null;

    public array $data = [];

    public array $recentActivity = [];

    public function mount(): void
    {
        // load() fires from wire:init.
    }

    public function load(bool $silent = false): void
    {
        if (! $silent) {
            $this->loading = true;
        }
        $this->error = null;

        $dashboard = Api::get('/dashboard/recruiter');

        if (!$dashboard['ok']) {
            $this->error = $dashboard['message'] ?? 'Unable to load dashboard.';
            $this->loading = false;
            return;
        }

        $this->data = $dashboard['data']['widgets'] ?? [];

        $activity = Api::get('/dashboard/recent-activity', ['limit' => 8]);
        if ($activity['ok']) {
            $this->recentActivity = $activity['data']['activity'] ?? [];
        }

        $this->loading = false;
    }

    public function poll(): void
    {
        $this->load(silent: true);
    }

    public function render()
    {
        return view('livewire.recruiter.dashboard')
            ->layout('layouts.portal', ['title' => 'Recruiter Dashboard', 'breadcrumb' => 'Dashboard']);
    }
}