<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;

class Dashboard extends Component
{
    public bool $loading = true;
    public ?string $error = null;

    public array $summary = [];
    public array $leaveBalances = [];
    public array $recentActivity = [];
    public array $timesheetHistory = [];
    public array $myAssets = [];
    public array $upcomingMeetings = [];
    public array $pendingCounts = [];
    public ?array $currentTimesheet = null;

    public float $totalLogged = 0;
    public int $targetHours = 160;
    public int $progressPercent = 0;
    public int $workingDaysElapsed = 0;
    public int $workingDaysTotal = 0;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $user = auth()->user();
        $endpoint = $user->employee_type === 'ps'
            ? '/dashboard/employee/ps'
            : '/dashboard/employee/pr';

        $dashboard = Api::get($endpoint);

        if (! $dashboard['ok']) {
            $this->error = $dashboard['message'] ?? 'Unable to load dashboard.';
            $this->loading = false;
            return;
        }

        $widgets = $dashboard['data']['widgets'] ?? [];

        $this->summary = $widgets;
        $this->upcomingMeetings = $widgets['upcoming_meetings'] ?? [];
        $this->myAssets = $widgets['my_assets'] ?? [];
        $this->currentTimesheet = $widgets['current_pr_timesheet']
            ?? $widgets['current_ps_timesheet']
            ?? null;
        $this->timesheetHistory = $widgets['recent_pr_timesheets']
            ?? $widgets['recent_ps_timesheets']
            ?? [];

        $balance = Api::get('/leave/balance');
        if ($balance['ok']) {
            $this->leaveBalances = $balance['data']['balances'] ?? [];
        }

        $counts = Api::get('/dashboard/pending-counts');
        if ($counts['ok']) {
            $this->pendingCounts = $counts['data'] ?? [];
        }

        $activity = Api::get('/dashboard/recent-activity', ['limit' => 10]);
        if ($activity['ok']) {
            $this->recentActivity = $activity['data']['activity'] ?? [];
        }

        $this->calculateProgress();
        $this->loading = false;
    }

    protected function calculateProgress(): void
    {
        $regular = (float) ($this->currentTimesheet['regular_hours']
            ?? $this->currentTimesheet['total_hours']
            ?? 0);
        $overtime = (float) ($this->currentTimesheet['overtime_hours'] ?? 0);

        $this->totalLogged = $regular + $overtime;

        $monthStart = now()->startOfMonth();
        $daysInMonth = now()->daysInMonth;
        $workingDays = 0;
        $workingDaysPassed = 0;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = $monthStart->copy()->addDays($day - 1);

            if ($date->isWeekend()) continue;

            $workingDays++;

            if ($date->lte(now())) $workingDaysPassed++;
        }

        $this->workingDaysTotal = $workingDays;
        $this->workingDaysElapsed = $workingDaysPassed;
        $this->targetHours = $workingDays * 8;

        $this->progressPercent = $this->targetHours > 0
            ? min(100, (int) round(($this->totalLogged / $this->targetHours) * 100))
            : 0;
    }

    public function getStatusLabelProperty(): string
    {
        $status = $this->currentTimesheet['status'] ?? 'draft';

        return match ($status) {
            'approved', 'submitted_to_client' => 'Approved',
            'pending_l1', 'pending_l2', 'template_generated', 'external_pending' => 'In review',
            'rejected' => 'Rejected',
            'external_signed' => 'Signed',
            default => 'Draft',
        };
    }

    public function getStatusToneProperty(): string
    {
        $status = $this->currentTimesheet['status'] ?? 'draft';

        return match ($status) {
            'approved', 'submitted_to_client' => 'success',
            'pending_l1', 'pending_l2', 'template_generated', 'external_pending' => 'warning',
            'rejected' => 'danger',
            'external_signed' => 'info',
            default => 'muted',
        };
    }

    public function getTotalPendingProperty(): int
    {
        return array_sum(array_map(
            fn ($value) => is_numeric($value) ? (int) $value : 0,
            $this->pendingCounts
        ));
    }

    public function humanizeAction(string $action): string
    {
        return match (true) {
            str_contains($action, 'LEAVE_APPLIED') => 'applied for leave',
            str_contains($action, 'LEAVE_APPROVED') => 'approved leave',
            str_contains($action, 'LEAVE_REJECTED') => 'rejected leave',
            str_contains($action, 'LEAVE_CANCELLED') => 'cancelled leave',
            str_contains($action, 'TIMESHEET_SUBMITTED') => 'submitted a timesheet',
            str_contains($action, 'TIMESHEET_APPROVED') => 'approved a timesheet',
            str_contains($action, 'TIMESHEET_CREATED') => 'created a timesheet',
            str_contains($action, 'TIMESHEET_RECALLED') => 'recalled a timesheet',
            str_contains($action, 'ASSET_LOANED') => 'assigned an asset',
            str_contains($action, 'ASSET_RETURNED') => 'returned an asset',
            str_contains($action, 'ASSET_REGISTERED') => 'registered an asset',
            str_contains($action, 'DOCUMENT_UPLOADED') => 'uploaded a document',
            str_contains($action, 'CONTRACT_UPLOADED') => 'uploaded a contract',
            str_contains($action, 'CONTRACT_RENEWED') => 'renewed a contract',
            str_contains($action, 'EMPLOYEE_PROFILE_UPDATED') => 'updated a profile',
            str_contains($action, 'CANDIDATE') => 'updated a candidate',
            str_contains($action, 'OFFER') => 'updated an offer',
            default => strtolower(str_replace('_', ' ', $action)),
        };
    }

    public function actionTone(string $action): string
    {
        return match (true) {
            str_contains($action, 'DELETED'), str_contains($action, 'REJECTED') => 'danger',
            str_contains($action, 'APPROVED'), str_contains($action, 'CREATED'), str_contains($action, 'APPLIED') => 'success',
            str_contains($action, 'UPDATED'), str_contains($action, 'RENEWED'), str_contains($action, 'SUBMITTED') => 'info',
            default => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.employee.dashboard')
            ->layout('layouts.portal', ['title' => 'Dashboard', 'breadcrumb' => 'Dashboard']);
    }
}