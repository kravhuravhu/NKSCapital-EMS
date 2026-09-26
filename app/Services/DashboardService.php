<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\DashboardCache;
use App\Models\Interview;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\Notification;
use App\Models\Offer;
use App\Models\PRTimesheet;
use App\Models\PSTimesheet;
use App\Models\Requisition;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Cache TTLs (seconds).
     */
    protected int $shortTtl = 60;
    protected int $mediumTtl = 300;
    protected int $longTtl = 1800;

    // ============================================================
    // EMPLOYEE — PS
    // ============================================================
    public function employeePSDashboard(User $user): array
    {
        return DashboardCache::remember($user, 'dashboard.employee.ps', $this->shortTtl, function () use ($user) {
            $month = now()->startOfMonth()->format('Y-m-d');

            $currentTimesheet = PSTimesheet::where('user_id', $user->id)
                ->where('month_year', $month)
                ->with('details')
                ->first();

            return [
                'widgets' => [
                    'leave_balances' => $user->getAllLeaveBalances(),
                    'current_ps_timesheet' => [
                        'exists' => (bool) $currentTimesheet,
                        'status' => $currentTimesheet?->status,
                        'total_hours' => $currentTimesheet?->total_hours ?? 0,
                        'template_generated' => (bool) $currentTimesheet?->template_generated_at,
                        'client_emailed' => (bool) $currentTimesheet?->client_emailed_at,
                    ],
                    'my_client' => $user->client ? [
                        'id' => $user->client->id,
                        'company_name' => $user->client->company_name,
                        'primary_contact' => $user->client->primary_contact_name,
                    ] : null,
                    'recent_ps_timesheets' => PSTimesheet::where('user_id', $user->id)
                        ->orderBy('month_year', 'desc')->limit(6)
                        ->get(['id', 'month_year', 'status', 'template_generated_at', 'client_emailed_at']),
                    'upcoming_meetings' => Meeting::upcoming()->limit(5)->get(['id', 'title', 'start_time', 'location']),
                    'my_assets' => Asset::where('current_assignee_id', $user->id)
                        ->where('status', 'loaned')
                        ->get(['id', 'asset_tag', 'model', 'condition']),
                    'unread_notifications' => Notification::where('user_id', $user->id)->unread()->count(),
                    'pending_leave_requests' => LeaveRequest::where('user_id', $user->id)
                        ->whereIn('status', ['pending', 'hold'])->count(),
                ],
            ];
        });
    }

    // ============================================================
    // EMPLOYEE — PR
    // ============================================================
    public function employeePRDashboard(User $user): array
    {
        return DashboardCache::remember($user, 'dashboard.employee.pr', $this->shortTtl, function () use ($user) {
            $month = now()->startOfMonth()->format('Y-m-d');

            $currentTimesheet = PRTimesheet::where('user_id', $user->id)
                ->where('month_year', $month)
                ->with('details')
                ->first();

            return [
                'widgets' => [
                    'leave_balances' => $user->getAllLeaveBalances(),
                    'current_pr_timesheet' => [
                        'exists' => (bool) $currentTimesheet,
                        'status' => $currentTimesheet?->status,
                        'regular_hours' => $currentTimesheet?->total_regular_hours ?? 0,
                        'overtime_hours' => $currentTimesheet?->total_overtime_hours ?? 0,
                        'l1_approved' => (bool) $currentTimesheet?->level1_approved_at,
                        'l2_approved' => (bool) $currentTimesheet?->level2_approved_at,
                    ],
                    'my_project' => $user->project ? [
                        'id' => $user->project->id,
                        'name' => $user->project->name,
                        'code' => $user->project->project_code,
                        'manager' => $user->project->projectManager?->full_name,
                    ] : null,
                    'recent_pr_timesheets' => PRTimesheet::where('user_id', $user->id)
                        ->orderBy('month_year', 'desc')->limit(6)
                        ->get(['id', 'month_year', 'status', 'total_regular_hours', 'total_overtime_hours']),
                    'upcoming_meetings' => Meeting::upcoming()->limit(5)->get(['id', 'title', 'start_time', 'location']),
                    'my_assets' => Asset::where('current_assignee_id', $user->id)
                        ->where('status', 'loaned')
                        ->get(['id', 'asset_tag', 'model', 'condition']),
                    'unread_notifications' => Notification::where('user_id', $user->id)->unread()->count(),
                    'pending_leave_requests' => LeaveRequest::where('user_id', $user->id)
                        ->whereIn('status', ['pending', 'hold'])->count(),
                ],
            ];
        });
    }

    // ============================================================
    // MANAGER
    // ============================================================
    public function managerDashboard(User $manager): array
    {
        return DashboardCache::remember($manager, 'dashboard.manager', $this->shortTtl, function () use ($manager) {
            $teamIds = User::where('manager_id', $manager->id)->where('is_active', true)->pluck('id');

            $pendingL1 = PRTimesheet::whereIn('user_id', $teamIds)
                ->where('status', 'pending_l1')->count();

            $pendingLeave = LeaveRequest::whereIn('user_id', $teamIds)
                ->whereIn('status', ['pending', 'hold'])->count();

            $teamToday = Meeting::today()->where('department', $manager->department)->count();

            $overdueAssets = Asset::whereIn('current_assignee_id', $teamIds)
                ->loaned()
                ->whereHas('activeLoan', fn ($q) => $q->whereDate('expected_return_date', '<', now()))
                ->count();

            return [
                'widgets' => [
                    'team_size' => $teamIds->count(),
                    'pending_l1_approvals' => $pendingL1,
                    'pending_leave_requests' => $pendingLeave,
                    'meetings_today' => $teamToday,
                    'overdue_assets' => $overdueAssets,
                    'team_timesheet_summary' => [
                        'draft' => PRTimesheet::whereIn('user_id', $teamIds)->where('status', 'draft')->count(),
                        'pending_l1' => $pendingL1,
                        'pending_l2' => PRTimesheet::whereIn('user_id', $teamIds)->where('status', 'pending_l2')->count(),
                        'approved' => PRTimesheet::whereIn('user_id', $teamIds)->where('status', 'approved')->count(),
                        'rejected' => PRTimesheet::whereIn('user_id', $teamIds)->where('status', 'rejected')->count(),
                    ],
                    'team_leave_today' => LeaveRequest::whereIn('user_id', $teamIds)
                        ->where('status', 'approved')
                        ->where('start_date', '<=', now()->toDateString())
                        ->where('end_date', '>=', now()->toDateString())
                        ->with('user:id,first_name,last_name')
                        ->get(['id', 'user_id', 'leave_type', 'start_date', 'end_date']),
                    'upcoming_meetings' => Meeting::upcoming()
                        ->where('department', $manager->department)->limit(5)
                        ->get(['id', 'title', 'start_time', 'location']),
                    'recent_pending_approvals' => PRTimesheet::whereIn('user_id', $teamIds)
                        ->where('status', 'pending_l1')
                        ->with('user:id,first_name,last_name')
                        ->orderBy('created_at')
                        ->limit(5)
                        ->get(['id', 'user_id', 'month_year', 'total_regular_hours']),
                    'unread_notifications' => Notification::where('user_id', $manager->id)->unread()->count(),
                    'active_delegations' => $manager->activeDelegationsGiven()->with('delegate:id,first_name,last_name')->get(),
                ],
            ];
        });
    }

    // ============================================================
    // DIRECTOR
    // ============================================================
    public function directorDashboard(User $director): array
    {
        return DashboardCache::remember($director, 'dashboard.director', $this->mediumTtl, function () {
            $month = now()->startOfMonth()->format('Y-m-d');

            $pendingL2 = PRTimesheet::where('status', 'pending_l2')->count();

            $totalEmployees = User::where('is_active', true)->count();
            $psCount = User::where('is_active', true)->employee_type('ps')->count();
            $prCount = User::where('is_active', true)->employee_type('pr')->count();

            $monthlyApproved = PRTimesheet::where('status', 'approved')
                ->where('month_year', $month)->count();

            $monthlyPayrollValue = PRTimesheet::where('status', 'approved')
                ->where('month_year', $month)
                ->sum(DB::raw('total_regular_hours * 1.0')) * 500; // rough estimate

            return [
                'widgets' => [
                    'total_employees' => $totalEmployees,
                    'ps_employees' => $psCount,
                    'pr_employees' => $prCount,
                    'pending_l2_approvals' => $pendingL2,
                    'approved_this_month' => $monthlyApproved,
                    'overdue_assets_company' => Asset::overdue()->count(),
                    'active_delegations' => \App\Models\ApprovalDelegation::active()->count(),
                    'expiring_contracts_30' => Contract::expiringSoon(30)->count(),
                    'open_requisitions' => Requisition::where('status', 'open')->count(),
                    'active_offers' => Offer::where('status', 'extended')->count(),
                    'timesheet_compliance' => [
                        'ps_submitted' => PSTimesheet::where('month_year', $month)->count(),
                        'pr_submitted' => PRTimesheet::where('month_year', $month)->count(),
                        'target' => $psCount + $prCount,
                    ],
                    'recent_critical_audit' => \App\Models\AuditLog::where('severity', 'critical')
                        ->orderBy('timestamp', 'desc')->limit(5)
                        ->get(['id', 'timestamp', 'action', 'error_message']),
                    'unread_notifications' => Notification::where('user_id', auth()->id())->unread()->count(),
                ],
            ];
        });
    }

    // ============================================================
    // ADMIN
    // ============================================================
    public function adminDashboard(User $admin): array
    {
        return DashboardCache::remember($admin, 'dashboard.admin', $this->shortTtl, function () {
            $activeUsers = User::where('is_active', true)->count();
            $inactiveUsers = User::where('is_active', false)->count();

            return [
                'widgets' => [
                    'users' => [
                        'total' => User::count(),
                        'active' => $activeUsers,
                        'inactive' => $inactiveUsers,
                        'with_2fa' => User::where('two_factor_enabled', true)->count(),
                    ],
                    'system_health' => [
                        'queue_pending_jobs' => DB::table('jobs')->count(),
                        'failed_jobs' => DB::table('failed_jobs')->count(),
                        'cache_size_mb' => round(DB::table('cache')->count() * 0.01, 2),
                        'last_audit' => \App\Models\AuditLog::max('timestamp'),
                    ],
                    'audit_stats' => [
                        'errors_today' => \App\Models\AuditLog::where('log_type', 'error')
                            ->whereDate('timestamp', now()->toDateString())->count(),
                        'warnings_today' => \App\Models\AuditLog::where('log_type', 'warning')
                            ->whereDate('timestamp', now()->toDateString())->count(),
                        'slow_requests_today' => \App\Models\AuditLog::where('is_slow', true)
                            ->whereDate('timestamp', now()->toDateString())->count(),
                    ],
                    'recent_users' => User::orderBy('created_at', 'desc')
                        ->limit(5)
                        ->get(['id', 'first_name', 'last_name', 'email', 'role', 'created_at']),
                    'unread_notifications' => Notification::where('user_id', auth()->id())->unread()->count(),
                ],
            ];
        });
    }

    // ============================================================
    // RECRUITER
    // ============================================================
    public function recruiterDashboard(User $recruiter): array
    {
        return DashboardCache::remember($recruiter, 'dashboard.recruiter', $this->shortTtl, function () {
            $openReqs = Requisition::where('status', 'open')->count();
            $activeCandidates = Candidate::whereNotIn('current_stage', ['hired', 'rejected', 'withdrawn'])->count();

            return [
                'widgets' => [
                    'open_requisitions' => $openReqs,
                    'total_candidates' => Candidate::count(),
                    'active_candidates' => $activeCandidates,
                    'pipeline' => [
                        'applied' => Candidate::where('current_stage', 'applied')->count(),
                        'screening' => Candidate::where('current_stage', 'screening')->count(),
                        'interview' => Candidate::where('current_stage', 'interview')->count(),
                        'offer' => Candidate::where('current_stage', 'offer')->count(),
                        'hired' => Candidate::where('current_stage', 'hired')->count(),
                    ],
                    'interviews_today' => Interview::whereDate('scheduled_at', now()->toDateString())
                        ->whereIn('status', ['scheduled', 'rescheduled'])->count(),
                    'interviews_this_week' => Interview::whereBetween('scheduled_at', [
                        now()->startOfWeek(), now()->endOfWeek()
                    ])->whereIn('status', ['scheduled', 'rescheduled'])->count(),
                    'pending_offers' => Offer::where('status', 'extended')->count(),
                    'recent_candidates' => Candidate::with('requisition:id,requisition_code,title')
                        ->orderBy('applied_date', 'desc')
                        ->limit(5)
                        ->get(['id', 'first_name', 'last_name', 'email', 'current_stage', 'requisition_id', 'applied_date']),
                    'unread_notifications' => Notification::where('user_id', auth()->id())->unread()->count(),
                ],
            ];
        });
    }

    // ============================================================
    // RECENT ACTIVITY (audit-based)
    // ============================================================
    public function recentActivity(User $user, int $limit = 20): array
    {
        $query = \App\Models\AuditLog::with('user:id,first_name,last_name')
            ->orderBy('timestamp', 'desc');

        // Non-privileged users see only their own activity
        if (!in_array($user->role, ['admin', 'super_admin', 'director'])) {
            $query->where('user_id', $user->id);
        }

        return $query->limit($limit)->get([
            'id', 'timestamp', 'action', 'log_type', 'severity',
            'user_id', 'table_name', 'record_id', 'http_status', 'duration_ms',
        ])->toArray();
    }

    // ============================================================
    // PENDING COUNTS
    // ============================================================
    public function pendingCounts(User $user): array
    {
        $counts = [];

        if ($user->isPS() || $user->isPR()) {
            $counts['leave_pending'] = LeaveRequest::where('user_id', $user->id)
                ->whereIn('status', ['pending', 'hold'])->count();
        }

        if ($user->role === 'manager') {
            $teamIds = User::where('manager_id', $user->id)->pluck('id');
            $counts['l1_approvals'] = PRTimesheet::whereIn('user_id', $teamIds)
                ->where('status', 'pending_l1')->count();
            $counts['team_leave_requests'] = LeaveRequest::whereIn('user_id', $teamIds)
                ->where('status', 'pending')->count();
        }

        if (in_array($user->role, ['director', 'admin', 'super_admin'])) {
            $counts['l2_approvals'] = PRTimesheet::where('status', 'pending_l2')->count();
            $counts['contract_expiring'] = Contract::expiringSoon(30)->count();
            $counts['overdue_assets'] = Asset::overdue()->count();
        }

        if (in_array($user->role, ['recruiter', 'admin', 'super_admin'])) {
            $counts['requisitions_pending_approval'] = Requisition::where('status', 'pending_approval')->count();
            $counts['pending_offers'] = Offer::where('status', 'extended')->count();
        }

        $counts['unread_notifications'] = Notification::where('user_id', $user->id)->unread()->count();

        return $counts;
    }
}