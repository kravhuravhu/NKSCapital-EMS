<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\AuditLog;
use App\Models\Candidate;
use App\Models\Contract;
use App\Models\Interview;
use App\Models\LeaveRequest;
use App\Models\Offer;
use App\Models\PRTimesheet;
use App\Models\PSTimesheet;
use App\Models\Requisition;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    // ============================================================
    // EMPLOYEE TIMESHEET REPORT
    // ============================================================
    public function employeeTimesheetReport(User $user, Carbon $from, Carbon $to): array
    {
        if ($user->isPS()) {
            $timesheets = PSTimesheet::with('details.project')
                ->where('user_id', $user->id)
                ->whereBetween('month_year', [$from->format('Y-m-d'), $to->format('Y-m-d')])
                ->get();

            return [
                'type' => 'ps',
                'employee' => $user->only(['id', 'first_name', 'last_name', 'employee_number', 'position']),
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'summary' => [
                    'total_timesheets' => $timesheets->count(),
                    'total_hours' => $timesheets->sum(fn ($t) => $t->total_hours),
                    'completed' => $timesheets->where('status', 'submitted_to_client')->count(),
                    'in_progress' => $timesheets->whereIn('status', ['draft', 'template_generated', 'external_pending', 'external_signed'])->count(),
                ],
                'records' => $timesheets->map(fn ($t) => [
                    'id' => $t->id,
                    'month' => $t->month_year->format('Y-m'),
                    'status' => $t->status,
                    'total_hours' => $t->total_hours,
                    'client_emailed_at' => $t->client_emailed_at,
                ]),
            ];
        }

        // PR
        $timesheets = PRTimesheet::with('details.project')
            ->where('user_id', $user->id)
            ->whereBetween('month_year', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->get();

        return [
            'type' => 'pr',
            'employee' => $user->only(['id', 'first_name', 'last_name', 'employee_number', 'position']),
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'total_timesheets' => $timesheets->count(),
                'total_regular_hours' => $timesheets->sum('total_regular_hours'),
                'total_overtime_hours' => $timesheets->sum('total_overtime_hours'),
                'approved' => $timesheets->where('status', 'approved')->count(),
                'rejected' => $timesheets->where('status', 'rejected')->count(),
                'pending' => $timesheets->whereIn('status', ['pending_l1', 'pending_l2'])->count(),
            ],
            'records' => $timesheets->map(fn ($t) => [
                'id' => $t->id,
                'month' => $t->month_year->format('Y-m'),
                'status' => $t->status,
                'regular_hours' => $t->total_regular_hours,
                'overtime_hours' => $t->total_overtime_hours,
                'approved_at' => $t->level2_approved_at,
            ]),
        ];
    }

    // ============================================================
    // MANAGER — TEAM PERFORMANCE
    // ============================================================
    public function teamPerformanceReport(User $manager, Carbon $from, Carbon $to): array
    {
        $teamIds = User::where('manager_id', $manager->id)->pluck('id');

        $timesheets = PRTimesheet::whereIn('user_id', $teamIds)
            ->whereBetween('month_year', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->with('user:id,first_name,last_name,employee_number,position')
            ->get();

        $leaves = LeaveRequest::whereIn('user_id', $teamIds)
            ->whereBetween('start_date', [$from, $to])
            ->get();

        $byEmployee = $timesheets->groupBy('user_id')->map(function ($group) {
            $user = $group->first()->user;
            return [
                'employee' => $user->only(['id', 'first_name', 'last_name', 'employee_number', 'position']),
                'timesheets_submitted' => $group->count(),
                'approved' => $group->where('status', 'approved')->count(),
                'rejected' => $group->where('status', 'rejected')->count(),
                'pending' => $group->whereIn('status', ['pending_l1', 'pending_l2', 'draft'])->count(),
                'total_regular_hours' => round($group->sum('total_regular_hours'), 2),
                'total_overtime_hours' => round($group->sum('total_overtime_hours'), 2),
                'approval_rate' => $group->count() > 0
                    ? round(($group->where('status', 'approved')->count() / $group->count()) * 100, 2) : 0,
            ];
        })->values();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'manager' => $manager->only(['id', 'first_name', 'last_name']),
            'team_size' => $teamIds->count(),
            'summary' => [
                'total_timesheets' => $timesheets->count(),
                'total_approved' => $timesheets->where('status', 'approved')->count(),
                'total_rejected' => $timesheets->where('status', 'rejected')->count(),
                'total_regular_hours' => round($timesheets->sum('total_regular_hours'), 2),
                'total_overtime_hours' => round($timesheets->sum('total_overtime_hours'), 2),
                'total_leave_requests' => $leaves->count(),
                'total_leave_days' => round($leaves->sum('days_taken'), 2),
            ],
            'by_employee' => $byEmployee,
            'leave_breakdown' => [
                'annual' => round($leaves->where('leave_type', 'annual')->sum('days_taken'), 2),
                'sick' => round($leaves->where('leave_type', 'sick')->sum('days_taken'), 2),
                'family' => round($leaves->where('leave_type', 'family')->sum('days_taken'), 2),
                'unpaid' => round($leaves->where('leave_type', 'unpaid')->sum('days_taken'), 2),
            ],
        ];
    }

    // ============================================================
    // DIRECTOR — COMPANY KPI
    // ============================================================
    public function companyKpiReport(Carbon $from, Carbon $to): array
    {
        $users = User::where('is_active', true)->get();
        $psUsers = $users->where('employee_type', 'ps');
        $prUsers = $users->where('employee_type', 'pr');

        $prTimesheets = PRTimesheet::whereBetween('month_year', [$from->format('Y-m-d'), $to->format('Y-m-d')])->get();
        $psTimesheets = PSTimesheet::whereBetween('month_year', [$from->format('Y-m-d'), $to->format('Y-m-d')])->get();

        $leaves = LeaveRequest::whereBetween('start_date', [$from, $to])->get();

        $contracts = Contract::with('user:id,first_name,last_name,position')
            ->whereBetween('effective_date', [$from, $to])->get();

        // Monthly trend (last 6 months)
        $trend = [];
        for ($i = 5; $i >= 0; $i--) {
            $m = now()->subMonths($i)->startOfMonth();
            $trend[] = [
                'month' => $m->format('M Y'),
                'pr_timesheets' => PRTimesheet::whereYear('month_year', $m->year)
                    ->whereMonth('month_year', $m->month)->count(),
                'ps_timesheets' => PSTimesheet::whereYear('month_year', $m->year)
                    ->whereMonth('month_year', $m->month)->count(),
                'leave_requests' => LeaveRequest::whereYear('start_date', $m->year)
                    ->whereMonth('start_date', $m->month)->count(),
            ];
        }

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'workforce' => [
                'total_employees' => $users->count(),
                'ps_employees' => $psUsers->count(),
                'pr_employees' => $prUsers->count(),
                'by_department' => $users->groupBy('department')->map->count(),
                'by_service_type' => $users->groupBy('service_type')->map->count(),
            ],
            'timesheets' => [
                'pr_total' => $prTimesheets->count(),
                'pr_approved' => $prTimesheets->where('status', 'approved')->count(),
                'pr_pending' => $prTimesheets->whereIn('status', ['pending_l1', 'pending_l2'])->count(),
                'ps_total' => $psTimesheets->count(),
                'ps_completed' => $psTimesheets->where('status', 'submitted_to_client')->count(),
                'total_regular_hours' => round($prTimesheets->sum('total_regular_hours'), 2),
                'total_overtime_hours' => round($prTimesheets->sum('total_overtime_hours'), 2),
            ],
            'leave' => [
                'total_requests' => $leaves->count(),
                'approved' => $leaves->whereIn('status', ['approved', 'auto_approved'])->count(),
                'rejected' => $leaves->where('status', 'rejected')->count(),
                'total_days' => round($leaves->sum('days_taken'), 2),
                'by_type' => [
                    'annual' => round($leaves->where('leave_type', 'annual')->sum('days_taken'), 2),
                    'sick' => round($leaves->where('leave_type', 'sick')->sum('days_taken'), 2),
                    'family' => round($leaves->where('leave_type', 'family')->sum('days_taken'), 2),
                    'unpaid' => round($leaves->where('leave_type', 'unpaid')->sum('days_taken'), 2),
                ],
            ],
            'contracts' => [
                'active' => Contract::where('status', 'active')->count(),
                'expiring_30' => Contract::expiringSoon(30)->count(),
                'expiring_60' => Contract::expiringSoon(60)->count(),
                'new_in_period' => $contracts->count(),
            ],
            'assets' => [
                'total' => Asset::count(),
                'loaned' => Asset::where('status', 'loaned')->count(),
                'overdue' => Asset::overdue()->count(),
                'in_repair' => Asset::whereIn('status', ['repair_requested', 'maintenance'])->count(),
            ],
            'recruitment' => [
                'open_requisitions' => Requisition::where('status', 'open')->count(),
                'active_candidates' => Candidate::whereNotIn('current_stage', ['hired', 'rejected', 'withdrawn'])->count(),
                'hired_in_period' => Candidate::where('current_stage', 'hired')
                    ->whereBetween('hire_date', [$from, $to])->count(),
                'active_offers' => Offer::where('status', 'extended')->count(),
            ],
            'monthly_trend' => $trend,
        ];
    }

    // ============================================================
    // DIRECTOR — FINANCIAL
    // ============================================================
    public function financialReport(Carbon $from, Carbon $to): array
    {
        $prTimesheets = PRTimesheet::where('status', 'approved')
            ->whereBetween('month_year', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->with('user:id,first_name,last_name,employee_number,position')
            ->get();

        $psTimesheets = PSTimesheet::where('status', 'submitted_to_client')
            ->whereBetween('month_year', [$from->format('Y-m-d'), $to->format('Y-m-d')])
            ->with('user:id,first_name,last_name,employee_number')
            ->get();

        // Hourly rate assumption — can be refined with contract rates
        $defaultRate = 500.00;
        $overtimeMultiplier = 1.5;

        $prCost = $prTimesheets->sum(fn ($t) =>
            ($t->total_regular_hours * $defaultRate) +
            ($t->total_overtime_hours * $defaultRate * $overtimeMultiplier)
        );

        $psBilling = $psTimesheets->sum(fn ($t) => $t->total_hours * ($defaultRate * 1.6));

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'pr_timesheets_approved' => $prTimesheets->count(),
                'pr_cost_estimate' => round($prCost, 2),
                'ps_timesheets_billed' => $psTimesheets->count(),
                'ps_billing_estimate' => round($psBilling, 2),
                'gross_margin' => round($psBilling - $prCost, 2),
            ],
            'default_hourly_rate' => $defaultRate,
            'by_employee' => $prTimesheets->groupBy('user_id')->map(function ($group) use ($defaultRate, $overtimeMultiplier) {
                $user = $group->first()->user;
                $reg = $group->sum('total_regular_hours');
                $ot = $group->sum('total_overtime_hours');
                return [
                    'employee' => $user->only(['id', 'first_name', 'last_name', 'employee_number', 'position']),
                    'regular_hours' => round($reg, 2),
                    'overtime_hours' => round($ot, 2),
                    'estimated_cost' => round(($reg * $defaultRate) + ($ot * $defaultRate * $overtimeMultiplier), 2),
                ];
            })->values(),
            'ps_by_client' => $psTimesheets->groupBy(fn ($t) => $t->user->client?->company_name ?? 'Unassigned')
                ->map(fn ($group, $client) => [
                    'client' => $client,
                    'total_hours' => round($group->sum(fn ($t) => $t->total_hours), 2),
                    'estimated_billing' => round($group->sum(fn ($t) => $t->total_hours) * ($defaultRate * 1.6), 2),
                ])->values(),
        ];
    }

    // ============================================================
    // ADMIN — SYSTEM USAGE
    // ============================================================
    public function systemUsageReport(Carbon $from, Carbon $to): array
    {
        $base = AuditLog::whereBetween('timestamp', [$from, $to]);

        $totalRequests = (clone $base)->count();
        $successRequests = (clone $base)->where('log_type', 'success')->count();
        $errorRequests = (clone $base)->where('log_type', 'error')->count();
        $warningRequests = (clone $base)->where('log_type', 'warning')->count();
        $slowRequests = (clone $base)->where('is_slow', true)->count();

        $avgDuration = (clone $base)->whereNotNull('duration_ms')->avg('duration_ms');

        $topEndpoints = (clone $base)->selectRaw('request_path, COUNT(*) as count')
            ->whereNotNull('request_path')
            ->groupBy('request_path')
            ->orderByDesc('count')
            ->limit(20)
            ->get();

        $topUsers = (clone $base)->selectRaw('user_id, COUNT(*) as count')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->orderByDesc('count')
            ->limit(20)
            ->with('user:id,first_name,last_name,email,role')
            ->get();

        $statusBreakdown = (clone $base)->selectRaw('http_status, COUNT(*) as count')
            ->whereNotNull('http_status')
            ->groupBy('http_status')
            ->orderByDesc('count')
            ->get();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'total_requests' => $totalRequests,
                'success_requests' => $successRequests,
                'warning_requests' => $warningRequests,
                'error_requests' => $errorRequests,
                'slow_requests' => $slowRequests,
                'avg_duration_ms' => round((float) $avgDuration, 2),
                'error_rate_percent' => $totalRequests > 0 ? round(($errorRequests / $totalRequests) * 100, 2) : 0,
                'slow_rate_percent' => $totalRequests > 0 ? round(($slowRequests / $totalRequests) * 100, 2) : 0,
            ],
            'top_endpoints' => $topEndpoints,
            'top_users' => $topUsers->map(fn ($r) => [
                'user_id' => $r->user_id,
                'name' => $r->user?->full_name,
                'email' => $r->user?->email,
                'role' => $r->user?->role,
                'count' => $r->count,
            ]),
            'status_breakdown' => $statusBreakdown,
        ];
    }

    // ============================================================
    // RECRUITMENT — PIPELINE
    // ============================================================
    public function recruitmentPipelineReport(Carbon $from, Carbon $to): array
    {
        $candidates = Candidate::whereBetween('applied_date', [$from, $to])
            ->with('requisition:id,requisition_code,title,department')
            ->get();

        $requisitions = Requisition::whereBetween('created_at', [$from, $to])->get();
        $offers = Offer::whereBetween('created_at', [$from, $to])->get();
        $interviews = Interview::whereBetween('scheduled_at', [$from, $to])->get();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'requisitions' => [
                'total' => $requisitions->count(),
                'open' => $requisitions->where('status', 'open')->count(),
                'closed' => $requisitions->where('status', 'closed')->count(),
                'by_department' => $requisitions->groupBy('department')->map->count(),
            ],
            'candidates' => [
                'total' => $candidates->count(),
                'by_stage' => $candidates->groupBy('current_stage')->map->count(),
                'by_source' => $candidates->groupBy('source')->map->count(),
            ],
            'interviews' => [
                'total' => $interviews->count(),
                'completed' => $interviews->where('status', 'completed')->count(),
                'cancelled' => $interviews->where('status', 'cancelled')->count(),
                'avg_feedback_rating' => round((float) $interviews->avg('feedback_rating'), 2),
            ],
            'offers' => [
                'total' => $offers->count(),
                'accepted' => $offers->where('status', 'accepted')->count(),
                'declined' => $offers->where('status', 'declined')->count(),
                'pending' => $offers->where('status', 'extended')->count(),
                'acceptance_rate' => $offers->count() > 0
                    ? round(($offers->where('status', 'accepted')->count() / $offers->count()) * 100, 2) : 0,
                'avg_salary_offered' => round((float) $offers->avg('salary_offered'), 2),
            ],
            'funnel' => [
                'applied' => $candidates->count(),
                'screening' => $candidates->whereIn('current_stage', ['screening', 'interview', 'offer', 'hired'])->count(),
                'interview' => $candidates->whereIn('current_stage', ['interview', 'offer', 'hired'])->count(),
                'offer' => $candidates->whereIn('current_stage', ['offer', 'hired'])->count(),
                'hired' => $candidates->where('current_stage', 'hired')->count(),
            ],
        ];
    }

    // ============================================================
    // COMPLIANCE — AUDIT
    // ============================================================
    public function complianceAuditReport(Carbon $from, Carbon $to): array
    {
        $base = AuditLog::whereBetween('timestamp', [$from, $to]);

        $totals = [
            'total_events' => (clone $base)->count(),
            'critical' => (clone $base)->where('severity', 'critical')->count(),
            'errors' => (clone $base)->where('severity', 'error')->count(),
            'warnings' => (clone $base)->where('severity', 'warning')->count(),
            'success' => (clone $base)->where('severity', 'success')->count(),
        ];

        $bySeverity = (clone $base)->selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')->pluck('count', 'severity');

        $topActions = (clone $base)->selectRaw('action, COUNT(*) as count')
            ->groupBy('action')->orderByDesc('count')->limit(15)->pluck('count', 'action');

        $topTables = (clone $base)->selectRaw('table_name, COUNT(*) as count')
            ->groupBy('table_name')->orderByDesc('count')->limit(15)->pluck('count', 'table_name');

        $criticalEvents = (clone $base)->whereIn('severity', ['critical', 'error'])
            ->orderByDesc('timestamp')
            ->limit(50)
            ->get(['id', 'timestamp', 'action', 'user_id', 'table_name', 'record_id', 'http_status', 'error_message']);

        $chainStatus = AuditService::verifyChain();

        return [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => $totals,
            'by_severity' => $bySeverity,
            'top_actions' => $topActions,
            'top_tables' => $topTables,
            'critical_events' => $criticalEvents,
            'chain_integrity' => $chainStatus,
        ];
    }
}