<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ReportingService;
use App\Services\ExportService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ReportController extends Controller
{
    protected ReportingService $reports;
    protected ExportService $exports;

    public function __construct(ReportingService $reports, ExportService $exports)
    {
        $this->reports = $reports;
        $this->exports = $exports;
    }

    // ============================================================
    // EMPLOYEE TIMESHEET REPORTS
    // ============================================================

    /**
     * GET /api/v1/reports/employee/ps/timesheet
     */
    public function employeePSTimesheet(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['employee', 'manager', 'director', 'admin', 'super_admin']);
        $this->requireEmployeeType($user, 'ps');

        [$from, $to] = $this->parsePeriod($request);

        $data = $this->reports->employeeTimesheetReport($user, $from, $to);

        return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    /**
     * GET /api/v1/reports/employee/pr/timesheet
     */
    public function employeePRTimesheet(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['employee', 'manager', 'director', 'admin', 'super_admin']);
        $this->requireEmployeeType($user, 'pr');

        [$from, $to] = $this->parsePeriod($request);

        $data = $this->reports->employeeTimesheetReport($user, $from, $to);

        return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    // ============================================================
    // MANAGER — TEAM PERFORMANCE
    // ============================================================

    /**
     * GET /api/v1/reports/manager/team-performance
     */
    public function teamPerformance(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['manager', 'director', 'admin', 'super_admin']);

        [$from, $to] = $this->parsePeriod($request);

        $data = $this->reports->teamPerformanceReport($user, $from, $to);

        return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    // ============================================================
    // DIRECTOR — COMPANY KPI & FINANCIAL
    // ============================================================

    /**
     * GET /api/v1/reports/director/company-kpi
     */
    public function companyKpi(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['director', 'admin', 'super_admin']);

        [$from, $to] = $this->parsePeriod($request);

        $data = $this->reports->companyKpiReport($from, $to);

        return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    /**
     * GET /api/v1/reports/director/financial
     */
    public function financial(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['director', 'admin', 'super_admin']);

        [$from, $to] = $this->parsePeriod($request);

        $data = $this->reports->financialReport($from, $to);

        return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    // ============================================================
    // ADMIN — SYSTEM USAGE
    // ============================================================

    /**
     * GET /api/v1/reports/admin/system-usage
     */
    public function systemUsage(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['admin', 'super_admin', 'director']);

        [$from, $to] = $this->parsePeriod($request);

        $data = $this->reports->systemUsageReport($from, $to);

        return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    // ============================================================
    // RECRUITMENT — PIPELINE
    // ============================================================

    /**
     * GET /api/v1/reports/recruitment/pipeline
     */
    public function recruitmentPipeline(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['recruiter', 'manager', 'director', 'admin', 'super_admin']);

        [$from, $to] = $this->parsePeriod($request);

        $data = $this->reports->recruitmentPipelineReport($from, $to);

        return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    // ============================================================
    // COMPLIANCE — AUDIT
    // ============================================================

    /**
     * GET /api/v1/reports/compliance/audit
     */
    public function complianceAudit(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['admin', 'super_admin', 'director']);

        [$from, $to] = $this->parsePeriod($request);

        $data = $this->reports->complianceAuditReport($from, $to);

        return response()->json(['status' => 'success', 'data' => $data], 200);
    }

    // ============================================================
    // EXPORTS
    // ============================================================

    /**
     * GET /api/v1/reports/export/excel
     */
    public function exportExcel(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:employee_timesheet,team_performance,company_kpi,financial,system_usage,recruitment_pipeline,compliance_audit',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        [$from, $to] = $this->parsePeriod($request);

        $data = $this->dispatchReport($user, $request->report_type, $from, $to);

        $export = $this->exports->toExcel($data, $request->report_type, $user, [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);

        AuditService::log(
            action: 'REPORT_EXPORTED_EXCEL',
            tableName: 'report_exports',
            recordId: $export->id,
            newValues: ['report_type' => $request->report_type, 'records' => $export->record_count],
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'export_id' => $export->id,
                'download_url' => Storage::url($export->file_path),
                'filename' => basename($export->file_path),
                'file_size' => $export->file_size,
                'record_count' => $export->record_count,
                'format' => $export->format,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/reports/export/pdf
     */
    public function exportPdf(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'report_type' => 'required|in:employee_timesheet,team_performance,company_kpi,financial,system_usage,recruitment_pipeline,compliance_audit',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        [$from, $to] = $this->parsePeriod($request);

        $data = $this->dispatchReport($user, $request->report_type, $from, $to);

        $export = $this->exports->toPdf($data, $request->report_type, $user, [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]);

        AuditService::log(
            action: 'REPORT_EXPORTED_PDF',
            tableName: 'report_exports',
            recordId: $export->id,
            newValues: ['report_type' => $request->report_type, 'records' => $export->record_count],
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'export_id' => $export->id,
                'download_url' => Storage::url($export->file_path),
                'filename' => basename($export->file_path),
                'file_size' => $export->file_size,
                'record_count' => $export->record_count,
                'format' => $export->format,
            ]
        ], 200);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function parsePeriod(Request $request): array
    {
        $from = $request->filled('from')
            ? Carbon::parse($request->from)->startOfDay()
            : now()->subDays(30)->startOfDay();
        $to = $request->filled('to')
            ? Carbon::parse($request->to)->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    protected function dispatchReport(User $user, string $type, Carbon $from, Carbon $to): array
    {
        return match ($type) {
            'employee_timesheet' => $this->reports->employeeTimesheetReport($user, $from, $to),
            'team_performance' => $this->reports->teamPerformanceReport($user, $from, $to),
            'company_kpi' => $this->reports->companyKpiReport($from, $to),
            'financial' => $this->reports->financialReport($from, $to),
            'system_usage' => $this->reports->systemUsageReport($from, $to),
            'recruitment_pipeline' => $this->reports->recruitmentPipelineReport($from, $to),
            'compliance_audit' => $this->reports->complianceAuditReport($from, $to),
            default => [],
        };
    }

    protected function requireRole(User $user, array $roles): void
    {
        if (!in_array($user->role, $roles)) {
            AuditService::logWarning('REPORT_ACCESS_DENIED', 'reports', 0, [
                'actor_id' => $user->id,
                'required' => $roles,
                'actual' => $user->role,
                'path' => request()->path(),
            ]);
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }
    }

    protected function requireEmployeeType(User $user, string $type): void
    {
        if ($user->employee_type !== $type
            && !in_array($user->role, ['admin', 'super_admin', 'director', 'manager'])) {
            abort(response()->json([
                'status' => 'error',
                'message' => "This report is only available for {$type} employees"
            ], 403));
        }
    }
}