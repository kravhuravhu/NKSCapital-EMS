<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Livewire\Component;

class Dashboard extends Component
{
    public bool  $loading = true;
    public ?string $error = null;

    public array $summary       = [];
    public array $leaveBalances = [];
    public array $recentActivity = [];
    public array $assets         = [];

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error   = null;

        $user = auth()->user();
        $endpoint = $user->employee_type === 'ps'
            ? '/dashboard/employee/ps'
            : '/dashboard/employee/pr';

        $dashboard = Api::get($endpoint);

        if (!$dashboard['ok']) {
            $this->error   = $dashboard['message'] ?? 'Unable to load dashboard.';
            $this->loading = false;
            return;
        }

        $this->summary        = $dashboard['data']['widgets'] ?? [];
        $this->leaveBalances  = $dashboard['data']['widgets']['leave_balances'] ?? [];
        $this->recentActivity = $dashboard['data']['widgets']['recent_activity'] ?? [];

        $assets = Api::get('/dashboard/recent-activity', ['limit' => 5]);
        if ($assets['ok']) {
            $this->recentActivity = $assets['data']['activity'] ?? $this->recentActivity;
        }

        $this->loading = false;
    }

    public function render()
    {
        return view('livewire.employee.dashboard')
            ->layout('layouts.portal', ['title' => 'Dashboard', 'breadcrumb' => 'Dashboard']);
    }
}