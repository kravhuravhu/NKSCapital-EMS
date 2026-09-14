<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PRTimesheet;
use App\Models\PSTimesheet;
use App\Models\User;
use App\Models\Project;
use App\Services\PayrollExportService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class TimesheetReportController extends Controller
{
    protected PayrollExportService $payrollService;

    public function __construct(PayrollExportService $payrollService)
    {
        $this->payrollService = $payrollService;
    }

    /**
     * GET /api/v1/timesheet/pr/export/payroll
     * Export approved PR timesheets to CSV/PDF
     */
    public function exportPayroll(Request $request)
    {
        $user = Auth::user();

        // Only Director, Admin, or Finance
        if (!in_array($user->role, ['admin', 'director', 'finance', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to export payroll data'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'month_year' => 'nullable|date_format:Y-m-d',
            'format' => 'nullable|in:csv,pdf,json',
            'user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $monthYear = $request->month_year ? Carbon::parse($request->month_year)->format('Y-m-d') : null;
        $format = $request->format ?? 'json';

        // Generate payroll data
        $payrollData = $this->payrollService->generatePayrollData($monthYear, $request->user_id);

        if (empty($payrollData['timesheets'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'No approved timesheets available for export'
            ], 404);
        }

        // Log the export
        AuditService::log(
            action: 'PAYROLL_EXPORTED',
            tableName: 'pr_timesheets',
            recordId: 0,
            newValues: [
                'month_year' => $monthYear,
                'format' => $format,
                'employee_count' => $payrollData['summary']['total_employees'],
                'total_amount' => $payrollData['summary']['total_amount'],
            ]
        );

        // Return based on format
        if ($format === 'csv') {
            $path = $this->payrollService->exportToCsv($payrollData);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Payroll exported to CSV successfully',
                'data' => [
                    'download_url' => Storage::url($path),
                    'filename' => basename($path),
                    'summary' => $payrollData['summary'],
                ]
            ], 200);
        }

        if ($format === 'pdf') {
            $pdf = Pdf::loadView('pdf.payroll-export', [
                'payroll' => $payrollData,
                'generated_at' => now()->format('d M Y H:i'),
                'generated_by' => $user->full_name,
            ]);

            $filename = 'payroll-export-' . now()->format('Y-m-d-H-i-s') . '.pdf';
            $path = 'payroll-exports/' . $filename;
            Storage::disk('public')->put($path, $pdf->output());

            return response()->json([
                'status' => 'success',
                'message' => 'Payroll exported to PDF successfully',
                'data' => [
                    'download_url' => Storage::url($path),
                    'filename' => $filename,
                    'summary' => $payrollData['summary'],
                ]
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'data' => $payrollData
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/pr/report/monthly
     * Generate monthly PR timesheet report
     */
    public function monthlyReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month_year' => 'required|date_format:Y-m-d',
            'department' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $monthYear = Carbon::parse($request->month_year)->startOfMonth();

        $query = PRTimesheet::with(['user:id,first_name,last_name,employee_number,department'])
            ->where('month_year', $monthYear->format('Y-m-d'));

        if ($request->has('department')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('department', $request->department);
            });
        }

        $timesheets = $query->get();

        // Aggregate stats
        $stats = [
            'total_submitted' => $timesheets->count(),
            'pending_l1' => $timesheets->where('status', 'pending_l1')->count(),
            'pending_l2' => $timesheets->where('status', 'pending_l2')->count(),
            'approved' => $timesheets->where('status', 'approved')->count(),
            'rejected' => $timesheets->where('status', 'rejected')->count(),
            'draft' => $timesheets->where('status', 'draft')->count(),
            'total_regular_hours' => round($timesheets->sum('total_regular_hours'), 2),
            'total_overtime_hours' => round($timesheets->sum('total_overtime_hours'), 2),
            'total_hours' => round($timesheets->sum('total_regular_hours') + $timesheets->sum('total_overtime_hours'), 2),
        ];

        // Approval rate
        $stats['approval_rate'] = $stats['total_submitted'] > 0
            ? round(($stats['approved'] / $stats['total_submitted']) * 100, 2)
            : 0;

        // Rejection rate
        $stats['rejection_rate'] = $stats['total_submitted'] > 0
            ? round(($stats['rejected'] / $stats['total_submitted']) * 100, 2)
            : 0;

        // Average approval cycle time
        $approvedTs = $timesheets->where('status', 'approved');
        if ($approvedTs->count() > 0) {
            $totalCycleTime = $approvedTs->sum(function ($ts) {
                return $ts->level2_approved_at->diffInHours($ts->created_at);
            });
            $stats['avg_approval_cycle_hours'] = round($totalCycleTime / $approvedTs->count(), 2);
        } else {
            $stats['avg_approval_cycle_hours'] = 0;
        }

        // By department
        $byDepartment = $timesheets->groupBy('user.department')
            ->map(function ($group) {
                return [
                    'count' => $group->count(),
                    'total_hours' => round($group->sum('total_regular_hours') + $group->sum('total_overtime_hours'), 2),
                    'approved' => $group->where('status', 'approved')->count(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'month' => $monthYear->format('F Y'),
                'stats' => $stats,
                'by_department' => $byDepartment,
                'timesheets' => $timesheets->map(function ($ts) {
                    return [
                        'id' => $ts->id,
                        'employee_number' => $ts->user->employee_number,
                        'employee_name' => $ts->user->full_name,
                        'department' => $ts->user->department,
                        'status' => $ts->status,
                        'regular_hours' => (float) $ts->total_regular_hours,
                        'overtime_hours' => (float) $ts->total_overtime_hours,
                        'submitted_at' => $ts->created_at->format('Y-m-d H:i'),
                        'approved_at' => $ts->level2_approved_at?->format('Y-m-d H:i'),
                    ];
                }),
            ]
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/ps/history
     * Get all PS timesheets history (Admin/Director)
     */
    public function psHistory(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'director', 'manager', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $query = PSTimesheet::with([
            'user:id,first_name,last_name,employee_number,department',
            'user.client:id,company_name',
        ]);

        // Filters
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('month_year')) {
            $query->where('month_year', $request->month_year);
        }
        if ($request->has('client_id')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('client_id', $request->client_id);
            });
        }

        $timesheets = $query->orderBy('month_year', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'status' => 'success',
            'data' => $timesheets
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/pr/history
     * Get all PR timesheets history (Admin/Director)
     */
    public function prHistory(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'director', 'manager', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $query = PRTimesheet::with([
            'user:id,first_name,last_name,employee_number,department',
            'level1Approver:id,first_name,last_name',
            'level2Approver:id,first_name,last_name',
        ]);

        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('month_year')) {
            $query->where('month_year', $request->month_year);
        }

        $timesheets = $query->orderBy('month_year', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'status' => 'success',
            'data' => $timesheets
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/report/compliance
     * Compliance report (audit compliance, timely submissions)
     */
    public function complianceReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month_year' => 'nullable|date_format:Y-m-d',
            'year' => 'nullable|integer|min:2020|max:2100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'director', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $year = $request->year ?? now()->year;

        // All active employees
        $totalEmployees = User::where('is_active', true)
            ->whereIn('employee_type', ['ps', 'pr'])
            ->count();

        // Monthly compliance stats
        $monthlyStats = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthDate = Carbon::create($year, $month, 1)->format('Y-m-d');

            $prSubmitted = PRTimesheet::where('month_year', $monthDate)->count();
            $prApproved = PRTimesheet::where('month_year', $monthDate)->where('status', 'approved')->count();
            $psSubmitted = PSTimesheet::where('month_year', $monthDate)->count();

            $monthlyStats[] = [
                'month' => Carbon::create($year, $month, 1)->format('F'),
                'pr_submitted' => $prSubmitted,
                'pr_approved' => $prApproved,
                'ps_submitted' => $psSubmitted,
                'submission_rate' => $totalEmployees > 0
                    ? round((($prSubmitted + $psSubmitted) / $totalEmployees) * 100, 2)
                    : 0,
            ];
        }

        // Employees who haven't submitted this month
        $currentMonth = now()->startOfMonth()->format('Y-m-d');
        $nonSubmitters = User::where('is_active', true)
            ->whereIn('employee_type', ['ps', 'pr'])
            ->whereDoesntHave('prTimesheets', function ($q) use ($currentMonth) {
                $q->where('month_year', $currentMonth);
            })
            ->whereDoesntHave('psTimesheets', function ($q) use ($currentMonth) {
                $q->where('month_year', $currentMonth);
            })
            ->select('id', 'employee_number', 'first_name', 'last_name', 'email', 'employee_type', 'department')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'year' => $year,
                'total_active_employees' => $totalEmployees,
                'monthly_stats' => $monthlyStats,
                'current_month_non_submitters' => [
                    'count' => $nonSubmitters->count(),
                    'employees' => $nonSubmitters,
                ],
            ]
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/report/department
     * Department timesheet report
     */
    public function departmentReport(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month_year' => 'nullable|date_format:Y-m-d',
            'department' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'director', 'manager', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $monthYear = $request->month_year 
            ? Carbon::parse($request->month_year)->startOfMonth() 
            : now()->startOfMonth();

        $query = PRTimesheet::with('user:id,first_name,last_name,department')
            ->where('month_year', $monthYear->format('Y-m-d'));

        if ($request->has('department')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('department', $request->department);
            });
        }

        $timesheets = $query->get();

        $byDepartment = $timesheets->groupBy('user.department')
            ->map(function ($group, $dept) {
                return [
                    'department' => $dept ?? 'Unassigned',
                    'total_employees' => $group->count(),
                    'approved' => $group->where('status', 'approved')->count(),
                    'pending' => $group->whereIn('status', ['pending_l1', 'pending_l2'])->count(),
                    'rejected' => $group->where('status', 'rejected')->count(),
                    'total_regular_hours' => round($group->sum('total_regular_hours'), 2),
                    'total_overtime_hours' => round($group->sum('total_overtime_hours'), 2),
                    'approval_rate' => $group->count() > 0
                        ? round(($group->where('status', 'approved')->count() / $group->count()) * 100, 2)
                        : 0,
                ];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'month' => $monthYear->format('F Y'),
                'departments' => $byDepartment,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/timesheet/report/export/excel
     * Export timesheet report to Excel
     */
    public function exportExcel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month_year' => 'required|date_format:Y-m-d',
            'report_type' => 'required|in:monthly,department,compliance',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'director', 'manager', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $monthYear = Carbon::parse($request->month_year)->startOfMonth();

        $timesheets = PRTimesheet::with([
            'user:id,employee_number,first_name,last_name,department,position',
            'level1Approver:id,first_name,last_name',
            'level2Approver:id,first_name,last_name',
        ])
            ->where('month_year', $monthYear->format('Y-m-d'))
            ->get();

        // For now, return CSV (Excel-compatible)
        $csv = "Employee No,Name,Department,Position,Status,Regular Hours,Overtime Hours,Total Hours,L1 Approver,L2 Approver,Submitted At\n";

        foreach ($timesheets as $ts) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s\n",
                $ts->user->employee_number,
                $ts->user->full_name,
                $ts->user->department ?? 'N/A',
                $ts->user->position ?? 'N/A',
                $ts->status,
                $ts->total_regular_hours,
                $ts->total_overtime_hours,
                $ts->total_regular_hours + $ts->total_overtime_hours,
                $ts->level1Approver?->full_name ?? 'N/A',
                $ts->level2Approver?->full_name ?? 'N/A',
                $ts->created_at->format('Y-m-d H:i')
            );
        }

        $filename = 'timesheet-report-' . $monthYear->format('Y-m') . '-' . time() . '.csv';
        $path = 'reports/' . $filename;
        Storage::disk('public')->put($path, $csv);

        AuditService::log(
            action: 'TIMESHEET_REPORT_EXPORTED_EXCEL',
            tableName: 'pr_timesheets',
            recordId: 0,
            newValues: ['month_year' => $monthYear->format('Y-m-d'), 'report_type' => $request->report_type]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Report exported successfully',
            'data' => [
                'download_url' => Storage::url($path),
                'filename' => $filename,
                'record_count' => $timesheets->count(),
            ]
        ], 200);
    }

    /**
     * POST /api/v1/timesheet/report/export/pdf
     * Export timesheet report to PDF
     */
    public function exportPdf(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'month_year' => 'required|date_format:Y-m-d',
            'report_type' => 'required|in:monthly,department,compliance',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'director', 'manager', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $monthYear = Carbon::parse($request->month_year)->startOfMonth();

        $timesheets = PRTimesheet::with([
            'user:id,employee_number,first_name,last_name,department,position',
            'level1Approver:id,first_name,last_name',
            'level2Approver:id,first_name,last_name',
        ])
            ->where('month_year', $monthYear->format('Y-m-d'))
            ->get();

        $pdf = Pdf::loadView('pdf.timesheet-report', [
            'timesheets' => $timesheets,
            'month' => $monthYear->format('F Y'),
            'report_type' => $request->report_type,
            'generated_at' => now()->format('d M Y H:i'),
            'generated_by' => $user->full_name,
            'summary' => [
                'total' => $timesheets->count(),
                'approved' => $timesheets->where('status', 'approved')->count(),
                'pending' => $timesheets->whereIn('status', ['pending_l1', 'pending_l2'])->count(),
                'rejected' => $timesheets->where('status', 'rejected')->count(),
                'total_hours' => round($timesheets->sum('total_regular_hours') + $timesheets->sum('total_overtime_hours'), 2),
            ],
        ]);

        $filename = 'timesheet-report-' . $monthYear->format('Y-m') . '-' . time() . '.pdf';
        $path = 'reports/' . $filename;
        Storage::disk('public')->put($path, $pdf->output());

        AuditService::log(
            action: 'TIMESHEET_REPORT_EXPORTED_PDF',
            tableName: 'pr_timesheets',
            recordId: 0,
            newValues: ['month_year' => $monthYear->format('Y-m-d'), 'report_type' => $request->report_type]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Report exported to PDF successfully',
            'data' => [
                'download_url' => Storage::url($path),
                'filename' => $filename,
                'record_count' => $timesheets->count(),
            ]
        ], 200);
    }
}