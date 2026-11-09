<?php

namespace App\Livewire\Manager;

use App\Support\AuditActionVerb;
use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    public bool $loading = true;
    public ?string $error = null;

    public array $widgets = [];
    public array $counts = [];
    public array $teamHistory = [];
    public array $teamLeaveToday = [];
    public array $upcomingMeetings = [];
    public array $recentApprovals = [];
    public array $recentActivity = [];
    public array $delegations = [];

    public int $teamSize = 0;
    public int $pendingL1 = 0;
    public int $pendingLeave = 0;
    public int $overdueAssets = 0;

    public array $statusBuckets = [
        'approved' => 0,
        'pending' => 0,
        'rejected' => 0,
        'draft' => 0,
    ];

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $dashboard = Api::get('/dashboard/manager');

        if (! $dashboard['ok']) {
            $this->error = $dashboard['message'] ?? 'Unable to load dashboard.';
            $this->loading = false;
            return;
        }

        $this->widgets = $dashboard['data']['widgets'] ?? [];

        $this->teamSize = (int) ($this->widgets['team_size'] ?? 0);
        $this->pendingL1 = (int) ($this->widgets['pending_l1_approvals'] ?? 0);
        $this->pendingLeave = (int) ($this->widgets['pending_leave_requests'] ?? 0);
        $this->overdueAssets = (int) ($this->widgets['overdue_assets'] ?? 0);

        $this->teamHistory = $this->widgets['team_history'] ?? [];
        $this->teamLeaveToday = $this->widgets['team_leave_today'] ?? [];
        $this->upcomingMeetings = $this->widgets['upcoming_meetings'] ?? [];
        $this->recentApprovals = $this->widgets['recent_pending_approvals'] ?? [];
        $this->delegations = $this->widgets['active_delegations'] ?? [];

        $summary = $this->widgets['team_timesheet_summary'] ?? [];
        $this->statusBuckets = [
            'approved' => (int) ($summary['approved'] ?? 0),
            'pending' => (int) ($summary['pending_l1'] ?? 0) + (int) ($summary['pending_l2'] ?? 0),
            'rejected' => (int) ($summary['rejected'] ?? 0),
            'draft' => (int) ($summary['draft'] ?? 0),
        ];

        $counts = Api::get('/dashboard/pending-counts');
        if ($counts['ok']) {
            $this->counts = $counts['data'] ?? [];
        }

        $activity = Api::get('/dashboard/recent-activity', ['limit' => 10]);
        if ($activity['ok']) {
            $this->recentActivity = $activity['data']['activity'] ?? [];
        }

        $this->loading = false;
    }

    public function getTotalTimesheetsProperty(): int
    {
        return array_sum($this->statusBuckets);
    }

    public function getPeakHoursProperty(): float
    {
        if (count($this->teamHistory) === 0) return 1.0;

        return max(1.0, collect($this->teamHistory)->max('total_hours') ?? 1.0);
    }

    public function barHeight(float $hours): int
    {
        $peak = $this->peakHours;
        if ($peak <= 0) return 8;

        return max(8, (int) round(($hours / $peak) * 100));
    }

    public function donutDash(int $count): float
    {
        $total = max(1, $this->totalTimesheets);

        return 276.46 * ($count / $total);
    }

    public function humanizeAction(string $action): string
    {
        return AuditActionVerb::verb($action);
    }

    public function actionTone(string $action): string
    {
        return AuditActionVerb::tone($action);
    }

    public function render()
    {
        return view('livewire.manager.dashboard')
            ->layout('layouts.portal', ['title' => 'Team Dashboard', 'breadcrumb' => 'Dashboard']);
    }
}