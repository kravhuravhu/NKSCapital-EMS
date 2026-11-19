<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\PRTimesheet;
use App\Models\TimesheetDetail;
use App\Models\Project;
use App\Models\User;
use App\Models\Notification;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use App\Models\LeaveCalendar;
use App\Models\SystemConfig;
use App\Support\RoleCheck;

class PRTimesheetController extends Controller
{
    /**
     * POST /api/v1/timesheet/pr/create
     */
    public function create(Request $request)
    {
        $user = Auth::user();

        if ($user->employee_type !== 'pr') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Projects employees can create PR timesheets'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'month_year' => 'required|date_format:Y-m-d',
            'entries' => 'nullable|array',
            'entries.*.work_date' => 'required_with:entries|date',
            'entries.*.project_id' => 'required_with:entries|exists:projects,id',
            'entries.*.hours_worked' => 'required_with:entries|numeric|min:0.5|max:24',
            'entries.*.task_description' => 'nullable|string|max:500',
            'entries.*.is_overtime' => 'nullable|boolean',
        ]);

        if ($request->has('entries') && !empty($request->entries)) {
            $leaveConflicts = $this->validateAgainstLeave($user, $request->entries);
            if ($leaveConflicts) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot add work hours on approved leave days',
                    'conflicts' => $leaveConflicts,
                ], 422);
            }
        }

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $monthYear = Carbon::parse($request->month_year)->startOfMonth();

        $existing = PRTimesheet::where('user_id', $user->id)
            ->where('month_year', $monthYear->format('Y-m-d'))
            ->first();

        if ($existing) {
            return response()->json([
                'status' => 'error',
                'message' => 'Timesheet already exists for this month',
                'data' => ['timesheet_id' => $existing->id]
            ], 409);
        }

        $timesheet = PRTimesheet::create([
            'user_id' => $user->id,
            'month_year' => $monthYear->format('Y-m-d'),
            'status' => 'draft',
        ]);

        if ($request->has('entries')) {
            $this->saveEntries($timesheet, $request->entries);
        }

        AuditService::log(
            action: 'PR_TIMESHEET_CREATED',
            tableName: 'pr_timesheets',
            recordId: $timesheet->id,
            newValues: ['month_year' => $monthYear->format('Y-m-d')]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'PR timesheet created successfully',
            'data' => [
                'timesheet' => $timesheet->load('details.project'),
                'total_hours' => $timesheet->total_hours,
            ]
        ], 201);
    }

    /**
     * PUT /api/v1/timesheet/pr/save-draft
     */
    public function saveDraft(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:pr_timesheets,id',
            'entries' => 'required|array',
            'entries.*.work_date' => 'required|date',
            'entries.*.project_id' => 'required|exists:projects,id',
            'entries.*.hours_worked' => 'required|numeric|min:0.5|max:24',
            'entries.*.task_description' => 'nullable|string|max:500',
            'entries.*.is_overtime' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        if (!empty($request->entries)) {
            $leaveConflicts = $this->validateAgainstLeave($user, $request->entries);
            if ($leaveConflicts) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Cannot add work hours on approved leave days',
                    'conflicts' => $leaveConflicts,
                ], 422);
            }
        }

        $timesheet = PRTimesheet::where('user_id', $user->id)
            ->findOrFail($request->timesheet_id);

        if (!in_array($timesheet->status, ['draft', 'rejected'], true)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft or rejected timesheets can be edited',
                'current_status' => $timesheet->status,
            ], 422);
        }

        $timesheet->details()->delete();
        $this->saveEntries($timesheet, $request->entries);

        $totalRegular = (float) $timesheet->details()->where('is_overtime', false)->sum('hours_worked');
        $totalOvertime = (float) $timesheet->details()->where('is_overtime', true)->sum('hours_worked');

        $timesheet->total_regular_hours = $totalRegular;
        $timesheet->total_overtime_hours = $totalOvertime;
        $timesheet->save();

        AuditService::log(
            action: 'PR_DRAFT_SAVED',
            tableName: 'pr_timesheets',
            recordId: $timesheet->id,
            newValues: ['entries_count' => count($request->entries), 'was_rejected' => $timesheet->wasChanged('status')],
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Draft saved successfully',
            'data' => [
                'timesheet_id' => $timesheet->id,
                'total_hours' => $timesheet->total_hours,
                'entries_count' => count($request->entries),
            ],
        ], 200);
    }

    /**
     * check timesheet entries against leave days
     */
    private function validateAgainstLeave(User $user, array $entries): ?array
    {
        $conflicts = [];
        foreach ($entries as $entry) {
            $workDate = $entry['work_date'];
            $leaveEntry = LeaveCalendar::where('user_id', $user->id)
                ->where('leave_date', $workDate)
                ->where(function ($q) {
                    $q->where('is_approved', true)
                      ->orWhere('is_pending_proof', true)
                      ->orWhere('is_unpaid_conversion', true);
                })
                ->first();

            if ($leaveEntry) {
                $conflicts[] = [
                    'date' => $workDate,
                    'leave_type' => $leaveEntry->leave_type,
                    'is_pending_proof' => (bool) $leaveEntry->is_pending_proof,
                    'is_unpaid_conversion' => (bool) $leaveEntry->is_unpaid_conversion,
                ];
            }
        }
        return empty($conflicts) ? null : $conflicts;
    }

    /**
     * POST /api/v1/timesheet/pr/submit
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:pr_timesheets,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $timesheet = PRTimesheet::with('details')
            ->where('user_id', $user->id)
            ->findOrFail($request->timesheet_id);

        if (!$timesheet->canSubmit()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Timesheet cannot be submitted in current status',
                'current_status' => $timesheet->status,
            ], 422);
        }

        $maxRegular = (float) SystemConfig::get('timesheet.max_regular_hours', 45);
        $maxOvertime = (float) SystemConfig::get('timesheet.max_overtime_hours', 15);
        $maxTotal = $maxRegular + $maxOvertime;

        $totalRegular = (float) $timesheet->details()->where('is_overtime', false)->sum('hours_worked');
        $totalOvertime = (float) $timesheet->details()->where('is_overtime', true)->sum('hours_worked');

        if ($totalRegular > $maxRegular) {
            return response()->json([
                'status' => 'error',
                'message' => "Regular hours cannot exceed {$maxRegular} hours",
                'regular_hours' => $totalRegular,
            ], 422);
        }

        if ($totalRegular + $totalOvertime > $maxTotal) {
            return response()->json([
                'status' => 'error',
                'message' => "Total hours cannot exceed {$maxTotal} hours",
                'total_hours' => $totalRegular + $totalOvertime,
            ], 422);
        }

        if ($timesheet->details()->count() === 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot submit empty timesheet',
            ], 422);
        }

        $project = $user->project;
        $l1ApproverId = $project ? $project->project_manager_id : null;

        $timesheet->status = 'pending_l1';
        $timesheet->level1_approver_id = $l1ApproverId;
        $timesheet->level2_approver_id = null;
        $timesheet->total_regular_hours = $totalRegular;
        $timesheet->total_overtime_hours = $totalOvertime;
        // Clear stale rejection and approval timestamps so a resubmitted
        // rejected timesheet does not carry its prior rejection reason.
        $timesheet->level1_rejection_reason = null;
        $timesheet->level2_rejection_reason = null;
        $timesheet->level1_approved_at = null;
        $timesheet->level2_approved_at = null;
        $timesheet->level1_approval_comment = null;
        $timesheet->level2_approval_comment = null;
        $timesheet->save();

        if ($l1ApproverId) {
            Notification::create([
                'user_id' => $l1ApproverId,
                'type' => 'TIMESHEET_PENDING_L1',
                'title' => 'Timesheet Pending L1 Approval',
                'message' => "{$user->full_name} submitted a timesheet for {$timesheet->month_year->format('F Y')} awaiting your approval.",
                'reference_id' => $timesheet->id,
                'reference_type' => PRTimesheet::class,
            ]);
        }

        AuditService::log(
            action: 'PR_TIMESHEET_SUBMITTED',
            tableName: 'pr_timesheets',
            recordId: $timesheet->id,
            newValues: [
                'total_regular_hours' => $totalRegular,
                'total_overtime_hours' => $totalOvertime,
                'l1_approver_id' => $l1ApproverId,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Timesheet submitted for L1 approval',
            'data' => [
                'timesheet_id' => $timesheet->id,
                'status' => $timesheet->status,
                'total_regular_hours' => $totalRegular,
                'total_overtime_hours' => $totalOvertime,
                'l1_approver_id' => $l1ApproverId,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/timesheet/pr/approve/l1
     */
    public function approveL1(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:pr_timesheets,id',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $timesheet = PRTimesheet::findOrFail($request->timesheet_id);

        if ($user->id !== $timesheet->level1_approver_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to approve this timesheet as L1',
            ], 403);
        }

        if (!$timesheet->canApproveL1()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Timesheet is not pending L1 approval',
                'current_status' => $timesheet->status,
            ], 422);
        }

        $l2Approver = User::where('role', 'operational_manager')
                ->where('is_active', true)
                ->first()
            ?? User::where('role', 'director')
                ->where('is_active', true)
                ->first();

        if (!$l2Approver) {
            AuditService::logError(
                action: 'PR_L1_APPROVE_NO_L2',
                tableName: 'pr_timesheets',
                recordId: $timesheet->id,
                errorMessage: 'No active L2 approver (operational_manager or director) exists.',
                context: ['l1_approver_id' => $user->id]
            );

            return response()->json([
                'status' => 'error',
                'message' => 'No active L2 approver is configured. Ask an administrator to activate an operational manager or director before this timesheet can proceed.',
            ], 422);
        }

        $timesheet->status = 'pending_l2';
        $timesheet->level1_approved_at = now();
        $timesheet->level2_approver_id = $l2Approver->id;
        $timesheet->level1_approval_comment = $request->comment;
        $timesheet->save();

        Notification::create([
            'user_id' => $l2Approver->id,
            'type' => 'TIMESHEET_PENDING_L2',
            'title' => 'Timesheet Pending L2 Approval',
            'message' => "Timesheet for {$timesheet->user->full_name} ({$timesheet->month_year->format('F Y')}) is awaiting your final approval.",
            'reference_id' => $timesheet->id,
            'reference_type' => PRTimesheet::class,
        ]);

        Notification::create([
            'user_id' => $timesheet->user_id,
            'type' => 'TIMESHEET_L1_APPROVED',
            'title' => 'Timesheet Approved by L1',
            'message' => "Your timesheet for {$timesheet->month_year->format('F Y')} has been approved by L1 and is now awaiting L2 approval.",
            'reference_id' => $timesheet->id,
            'reference_type' => PRTimesheet::class,
        ]);

        AuditService::log(
            action: 'PR_L1_APPROVED',
            tableName: 'pr_timesheets',
            recordId: $timesheet->id,
            oldValues: ['status' => 'pending_l1'],
            newValues: [
                'status' => 'pending_l2',
                'comment' => $request->comment,
                'level2_approver_id' => $l2Approver->id,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Timesheet approved at L1 successfully',
            'data' => [
                'timesheet_id' => $timesheet->id,
                'status' => $timesheet->status,
                'level1_approved_at' => $timesheet->level1_approved_at,
                'l2_approver_id' => $l2Approver->id,
            ],
        ], 200);
    }

    /**
     * POST /api/v1/timesheet/pr/reject/l1
     */
    public function rejectL1(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:pr_timesheets,id',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $timesheet = PRTimesheet::findOrFail($request->timesheet_id);

        if ($user->id !== $timesheet->level1_approver_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not authorized to reject this timesheet'
            ], 403);
        }

        if (!$timesheet->canApproveL1()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Timesheet is not pending L1 approval'
            ], 422);
        }

        $timesheet->status = 'rejected';
        $timesheet->level1_rejection_reason = $request->reason;
        $timesheet->save();

        Notification::create([
            'user_id' => $timesheet->user_id,
            'type' => 'TIMESHEET_L1_REJECTED',
            'title' => 'Timesheet Rejected by L1',
            'message' => "Your timesheet for {$timesheet->month_year->format('F Y')} was rejected. Reason: {$request->reason}",
            'reference_id' => $timesheet->id,
            'reference_type' => PRTimesheet::class,
        ]);

        AuditService::log(
            action: 'PR_L1_REJECTED',
            tableName: 'pr_timesheets',
            recordId: $timesheet->id,
            oldValues: ['status' => 'pending_l1'],
            newValues: ['status' => 'rejected', 'reason' => $request->reason]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Timesheet rejected at L1',
            'data' => [
                'timesheet_id' => $timesheet->id,
                'status' => $timesheet->status,
                'reason' => $timesheet->level1_rejection_reason,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/timesheet/pr/approve/l2
     */
    public function approveL2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:pr_timesheets,id',
            '2fa_code' => 'required|string|size:6',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        if (!$user->two_factor_enabled || !$user->two_factor_secret) {
            return response()->json([
                'status' => 'error',
                'message' => '2FA is not enabled. Enable it on your security page first.',
                'requires_2fa_setup' => true,
            ], 403);
        }

        $secret = decrypt($user->two_factor_secret);
        $google2fa = new \PragmaRX\Google2FA\Google2FA();

        if (!$google2fa->verifyKey($secret, $request->input('2fa_code'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid 2FA code',
            ], 422);
        }

        $timesheet = PRTimesheet::findOrFail($request->timesheet_id);

        if ($timesheet->level2_approver_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not the assigned L2 approver',
            ], 403);
        }

        if (!$timesheet->canApproveL2()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Timesheet is not pending L2 approval',
            ], 422);
        }

        // Generate PDF
        $pdf = Pdf::loadView('pdf.pr-timesheet', [
            'timesheet' => $timesheet,
            'user' => $timesheet->user,
            'details' => $timesheet->details,
            'generated_at' => now()->format('d M Y H:i'),
        ]);

        $filename = 'pr-timesheet-' . $timesheet->user_id . '-' . $timesheet->month_year->format('Y-m') . '.pdf';
        $path = 'pr-timesheets/' . $filename;
        Storage::disk('public')->put($path, $pdf->output());
        $pdfHash = hash('sha256', $pdf->output());

        $timesheet->status = 'approved';
        $timesheet->level2_approved_at = now();
        $timesheet->pdf_path = $path;
        $timesheet->pdf_hash = $pdfHash;
        $timesheet->level2_approval_comment = $request->comment;
        $timesheet->save();

        Notification::create([
            'user_id' => $timesheet->user_id,
            'type' => 'TIMESHEET_APPROVED',
            'title' => 'Timesheet Fully Approved',
            'message' => "Your timesheet for {$timesheet->month_year->format('F Y')} has been fully approved and is ready for payroll processing.",
            'reference_id' => $timesheet->id,
            'reference_type' => PRTimesheet::class,
        ]);

        $financeUsers = User::where('role', 'finance')->orWhere('role', 'admin')->get();
        foreach ($financeUsers as $finance) {
            Notification::create([
                'user_id' => $finance->id,
                'type' => 'PAYROLL_READY',
                'title' => 'Timesheet Ready for Payroll',
                'message' => "Timesheet for {$timesheet->user->full_name} ({$timesheet->month_year->format('F Y')}) is approved and ready for payroll export.",
                'reference_id' => $timesheet->id,
                'reference_type' => PRTimesheet::class,
            ]);
        }

        AuditService::log(
            action: 'PR_L2_APPROVED',
            tableName: 'pr_timesheets',
            recordId: $timesheet->id,
            oldValues: ['status' => 'pending_l2'],
            newValues: [
                'status' => 'approved',
                'pdf_hash' => $pdfHash,
                'comment' => $request->comment,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Timesheet approved at L2 successfully',
            'data' => [
                'timesheet_id' => $timesheet->id,
                'status' => $timesheet->status,
                'level2_approved_at' => $timesheet->level2_approved_at,
                'pdf_url' => Storage::url($path),
                'pdf_hash' => $pdfHash,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/timesheet/pr/reject/l2
     */
    public function rejectL2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:pr_timesheets,id',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $timesheet = PRTimesheet::findOrFail($request->timesheet_id);

        if ($timesheet->level2_approver_id !== $user->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not the assigned L2 approver'
            ], 403);
        }

        if (!$timesheet->canApproveL2()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Timesheet is not pending L2 approval'
            ], 422);
        }

        $timesheet->status = 'rejected';
        $timesheet->level2_rejection_reason = $request->reason;
        $timesheet->save();

        Notification::create([
            'user_id' => $timesheet->user_id,
            'type' => 'TIMESHEET_L2_REJECTED',
            'title' => 'Timesheet Rejected by Director',
            'message' => "Your timesheet for {$timesheet->month_year->format('F Y')} was rejected. Reason: {$request->reason}",
            'reference_id' => $timesheet->id,
            'reference_type' => PRTimesheet::class,
        ]);

        AuditService::log(
            action: 'PR_L2_REJECTED',
            tableName: 'pr_timesheets',
            recordId: $timesheet->id,
            oldValues: ['status' => 'pending_l2'],
            newValues: ['status' => 'rejected', 'reason' => $request->reason]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Timesheet rejected at L2',
            'data' => [
                'timesheet_id' => $timesheet->id,
                'status' => $timesheet->status,
                'reason' => $timesheet->level2_rejection_reason,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/timesheet/pr/recall
     */
    public function recall(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:pr_timesheets,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $timesheet = PRTimesheet::where('user_id', $user->id)
            ->findOrFail($request->timesheet_id);

        if (!$timesheet->canRecall()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot recall timesheet in current status',
                'current_status' => $timesheet->status
            ], 422);
        }

        $oldStatus = $timesheet->status;
        $timesheet->status = 'draft';
        $timesheet->level1_approver_id = null;
        $timesheet->level2_approver_id = null;
        $timesheet->save();

        AuditService::log(
            action: 'PR_TIMESHEET_RECALLED',
            tableName: 'pr_timesheets',
            recordId: $timesheet->id,
            oldValues: ['status' => $oldStatus],
            newValues: ['status' => 'draft']
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Timesheet recalled successfully',
            'data' => ['timesheet_id' => $timesheet->id, 'status' => $timesheet->status]
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/pr/{id}
     */
    public function show($id)
    {
        $user = Auth::user();
        $timesheet = PRTimesheet::with([
            'details.project',
            'user:id,first_name,last_name,employee_number,email',
            'level1Approver:id,first_name,last_name',
            'level2Approver:id,first_name,last_name'
        ])->findOrFail($id);

        if ($user->id !== $timesheet->user_id 
            && $user->id !== $timesheet->level1_approver_id
            && $user->id !== $timesheet->level2_approver_id
            && !RoleCheck::has($user, ['admin', 'director', 'operational_manager', 'manager'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to view this timesheet'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'timesheet' => $timesheet,
                'total_hours' => $timesheet->total_hours,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/pr/list
     */
    public function list(Request $request)
    {
        $user = Auth::user();
        $query = PRTimesheet::with('details')->where('user_id', $user->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $timesheets = $query->orderBy('month_year', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => 'success',
            'data' => $timesheets
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/pr/history
     */
    public function history(Request $request)
    {
        $user = Auth::user();
        $timesheets = PRTimesheet::with('details')
            ->where('user_id', $user->id)
            ->orderBy('month_year', 'desc')
            ->limit(12)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $timesheets->map(function($ts) {
                return [
                    'id' => $ts->id,
                    'month_year' => $ts->month_year->format('F Y'),
                    'status' => $ts->status,
                    'total_regular_hours' => $ts->total_regular_hours,
                    'total_overtime_hours' => $ts->total_overtime_hours,
                    'submitted_at' => $ts->created_at,
                    'level1_approved_at' => $ts->level1_approved_at,
                    'level2_approved_at' => $ts->level2_approved_at,
                    'rejection_reason' => $ts->level1_rejection_reason ?? $ts->level2_rejection_reason,
                ];
            })
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/pr/pending/l1
     */
    public function pendingL1()
    {
        $user = Auth::user();

        if (!RoleCheck::has($user, ['manager', 'admin', 'director'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $timesheets = PRTimesheet::with([
            'details.project',
            'user:id,first_name,last_name,employee_number'
        ])
            ->where('status', 'pending_l1')
            ->where('level1_approver_id', $user->id)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $timesheets
        ], 200);
    }

    /**
     * GET /api/v1/timesheet/pr/pending/l2
     */
    public function pendingL2()
    {
        $user = Auth::user();

        if (!RoleCheck::has($user, ['director', 'operational_manager', 'admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
        }

        $timesheets = PRTimesheet::with([
            'details.project',
            'user:id,first_name,last_name,employee_number',
            'level1Approver:id,first_name,last_name'
        ])
            ->where('status', 'pending_l2')
            ->where('level2_approver_id', $user->id)
            ->orderBy('level1_approved_at', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $timesheets
        ], 200);
    }

    /**
     * Helper: Save timesheet entries
     */
    private function saveEntries(PRTimesheet $timesheet, array $entries): void
    {
        foreach ($entries as $entry) {
            TimesheetDetail::create([
                'timesheet_parent_id' => $timesheet->id,
                'timesheet_parent_type' => PRTimesheet::class,
                'work_date' => $entry['work_date'],
                'project_id' => $entry['project_id'],
                'hours_worked' => $entry['hours_worked'],
                'is_overtime' => $entry['is_overtime'] ?? false,
                'task_description' => $entry['task_description'] ?? null,
                'is_billable' => true,
            ]);
        }
    }

    /**
     * POST /api/v1/timesheet/pr/override/l2
     *
     * Director-only override that transitions a rejected timesheet to approved.
     * Requires 2FA and a written justification. Writes a critical-severity
     * audit entry distinct from the normal L2 approval path.
     */
    public function overrideL2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'timesheet_id' => 'required|exists:pr_timesheets,id',
            '2fa_code' => 'required|string|size:6',
            'comment' => 'required|string|min:10|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();

        if (!\App\Support\RoleCheck::has($user, ['director', 'operational_manager', 'super_admin'])) {
            AuditService::logWarning('PR_OVERRIDE_UNAUTHORIZED', 'pr_timesheets', 0, [
                'actor_id' => $user->id,
                'actor_role' => $user->role,
                'timesheet_id' => $request->timesheet_id,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Only a Director or Operational Manager may override a rejection.',
            ], 403);
        }

        if (!$user->two_factor_enabled || !$user->two_factor_secret) {
            return response()->json([
                'status' => 'error',
                'message' => '2FA is not enabled. Enable it on your security page first.',
                'requires_2fa_setup' => true,
            ], 403);
        }

        $secret = decrypt($user->two_factor_secret);
        $google2fa = new \PragmaRX\Google2FA\Google2FA();

        if (!$google2fa->verifyKey($secret, $request->input('2fa_code'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid 2FA code',
            ], 422);
        }

        $timesheet = PRTimesheet::findOrFail($request->timesheet_id);

        if ($timesheet->status !== 'rejected') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only rejected timesheets can be overridden.',
                'current_status' => $timesheet->status,
            ], 422);
        }

        // Conflict of interest: the person who rejected at L1 or L2 cannot
        // also override.
        $rejectedAtL2ByUser = (int) $timesheet->level2_approver_id === (int) $user->id
            && !empty($timesheet->level2_rejection_reason);
        $rejectedAtL1ByUser = (int) $timesheet->level1_approver_id === (int) $user->id
            && !empty($timesheet->level1_rejection_reason);

        if ($rejectedAtL2ByUser || $rejectedAtL1ByUser) {
            AuditService::logWarning('PR_OVERRIDE_CONFLICT_OF_INTEREST', 'pr_timesheets', $timesheet->id, [
                'actor_id' => $user->id,
                'rejected_at_l1' => $rejectedAtL1ByUser,
                'rejected_at_l2' => $rejectedAtL2ByUser,
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'You cannot override your own rejection. Another director must review this timesheet.',
            ], 403);
        }

        $originalReason = $timesheet->level2_rejection_reason
            ?? $timesheet->level1_rejection_reason
            ?? null;

        $pdf = Pdf::loadView('pdf.pr-timesheet', [
            'timesheet' => $timesheet,
            'user' => $timesheet->user,
            'details' => $timesheet->details,
            'generated_at' => now()->format('d M Y H:i'),
        ]);
        $filename = 'pr-timesheet-' . $timesheet->user_id . '-' . $timesheet->month_year->format('Y-m') . '.pdf';
        $path = 'pr-timesheets/' . $filename;
        Storage::disk('public')->put($path, $pdf->output());
        $pdfHash = hash('sha256', $pdf->output());

        $timesheet->status = 'approved';
        $timesheet->level2_approver_id = $user->id;
        $timesheet->level2_approved_at = now();
        $timesheet->level2_rejection_reason = null;
        $timesheet->pdf_path = $path;
        $timesheet->pdf_hash = $pdfHash;
        $timesheet->save();

        AuditService::log(
            action: 'PR_OVERRIDE_APPROVED',
            tableName: 'pr_timesheets',
            recordId: $timesheet->id,
            oldValues: [
                'status' => 'rejected',
                'rejection_reason' => $originalReason,
            ],
            newValues: [
                'status' => 'approved',
                'overridden_by' => $user->id,
                'override_comment' => $request->comment,
                'pdf_hash' => $pdfHash,
            ],
            logType: 'warning',
            severity: 'critical'
        );

        Notification::create([
            'user_id' => $timesheet->user_id,
            'type' => 'TIMESHEET_OVERRIDE_APPROVED',
            'title' => 'Timesheet Approved (Director Override)',
            'message' => "Your timesheet for {$timesheet->month_year->format('F Y')} has been approved by the Director. Note: {$request->comment}",
            'reference_id' => $timesheet->id,
            'reference_type' => PRTimesheet::class,
        ]);

        $financeUsers = User::where('role', 'finance')->orWhere('role', 'admin')->get();
        foreach ($financeUsers as $finance) {
            Notification::create([
                'user_id' => $finance->id,
                'type' => 'PAYROLL_READY',
                'title' => 'Timesheet Ready for Payroll (Override)',
                'message' => "Timesheet for {$timesheet->user->full_name} ({$timesheet->month_year->format('F Y')}) was approved via Director override.",
                'reference_id' => $timesheet->id,
                'reference_type' => PRTimesheet::class,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Rejection overridden, timesheet approved.',
            'data' => [
                'timesheet_id' => $timesheet->id,
                'status' => $timesheet->status,
                'level2_approved_at' => $timesheet->level2_approved_at,
                'pdf_url' => Storage::url($path),
                'pdf_hash' => $pdfHash,
                'original_rejection_reason' => $originalReason,
            ],
        ], 200);
    }
}
