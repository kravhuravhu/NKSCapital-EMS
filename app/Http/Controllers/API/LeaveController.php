<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveCalendar;
use App\Models\LeaveConfig;
use App\Models\User;
use App\Models\Notification;
use App\Services\AuditService;
use App\Services\LeaveApprovalService;
use App\Services\LeaveProofService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class LeaveController extends Controller
{
    protected LeaveApprovalService $approvalService;
    protected LeaveProofService $proofService;

    public function __construct(
        LeaveApprovalService $approvalService,
        LeaveProofService $proofService
    ) {
        $this->approvalService = $approvalService;
        $this->proofService = $proofService;
    }

    // ============================================================
    // APPLY
    // ============================================================

    public function apply(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'leave_type' => 'required|in:annual,sick,family,unpaid,study,maternity,paternity',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png,zip|max:10240',
        ]);

        if ($validator->fails()) {
            AuditService::logWarning('LEAVE_APPLY_VALIDATION_FAILED', 'leave_requests', 0, [
                'errors' => $validator->errors()->toArray(),
                'input' => $request->except(['attachment']),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Calculate days (business days only)
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $daysTaken = $this->calculateBusinessDays($startDate, $endDate);

        if ($daysTaken <= 0) {
            AuditService::logWarning('LEAVE_APPLY_NO_BUSINESS_DAYS', 'leave_requests', 0, [
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'No business days in the selected range'
            ], 422);
        }

        // Get leave config
        $config = LeaveConfig::where('leave_type', $request->leave_type)->first();
        if (!$config) {
            return response()->json([
                'status' => 'error',
                'message' => "Leave type '{$request->leave_type}' is not configured."
            ], 422);
        }

        // Check balance for leave types that need it
        if (!in_array($request->leave_type, ['unpaid', 'study'])) {
            $balance = $user->getLeaveBalance($request->leave_type);
            $isInfinite = $config->isInfinite();

            if (!$isInfinite && $balance < $daysTaken) {
                AuditService::logWarning('LEAVE_APPLY_INSUFFICIENT_BALANCE', 'leave_requests', 0, [
                    'leave_type' => $request->leave_type,
                    'required' => $daysTaken,
                    'available' => $balance,
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient leave balance',
                    'required' => $daysTaken,
                    'available' => $balance,
                    'leave_type' => $request->leave_type,
                ], 422);
            }
        }

        // Evaluate application (service-month + approval path)
        $decision = $this->approvalService->evaluateApplication(
            $user,
            $request->leave_type,
            $daysTaken,
            $request->hasFile('attachment')
        );

        if (!empty($decision['errors'])) {
            AuditService::logWarning('LEAVE_APPLY_RULE_FAILED', 'leave_requests', 0, [
                'leave_type' => $request->leave_type,
                'errors' => $decision['errors'],
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $decision['reason'],
                'errors' => $decision['errors'],
            ], 422);
        }

        // Conflict check
        $conflicts = $this->checkConflicts($user, $startDate, $endDate);

        // Handle optional attachment upload at apply time
        $attachmentPath = null;
        $attachmentHash = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = 'leave-attachment-' . $user->id . '-' . time() . '.' . $file->getClientOriginalExtension();
            $attachmentPath = $file->storeAs('leave-attachments', $filename, 'public');
            $attachmentHash = hash_file('sha256', $file->getRealPath());
        }

        // Resolve initial status
        $status = $decision['status'];
        $requiresProof = $decision['requires_proof'];

        // If sick leave requires proof and no attachment uploaded at apply time → set status to pending
        // (Manager will approve → approved_pending_proof)
        // Auto-approval only happens if not requiring proof
        if ($status === 'auto_approved' && $requiresProof) {
            $status = 'pending';
        }

        $leaveRequest = LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type' => $request->leave_type,
            'original_leave_type' => $request->leave_type,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'days_taken' => $daysTaken,
            'reason' => $request->reason,
            'attachment_path' => $attachmentPath,
            'attachment_hash' => $attachmentHash,
            'status' => $status,
            'requires_proof' => $requiresProof,
            'approved_by' => $status === 'auto_approved' ? $user->id : null,
            'approved_at' => $status === 'auto_approved' ? now() : null,
            'service_months_at_application' => $user->getMonthsOfService(),
        ]);

        // If auto-approved, deduct and block calendar
        if ($status === 'auto_approved') {
            $this->deductBalance($user, $request->leave_type, $daysTaken, $leaveRequest);
            $this->blockCalendar($user, $startDate, $endDate, $request->leave_type, $leaveRequest, true, false, false);
        }

        AuditService::log(
            action: $status === 'auto_approved' ? 'LEAVE_AUTO_APPROVED' : 'LEAVE_APPLIED',
            tableName: 'leave_requests',
            recordId: $leaveRequest->id,
            newValues: [
                'leave_type' => $request->leave_type,
                'days_taken' => $daysTaken,
                'status' => $status,
                'requires_proof' => $requiresProof,
                'service_months' => $user->getMonthsOfService(),
            ],
            logType: 'success'
        );

        if ($status !== 'auto_approved' && $user->manager_id) {
            Notification::create([
                'user_id' => $user->manager_id,
                'type' => 'LEAVE_PENDING',
                'title' => 'Leave Request Pending Approval',
                'message' => "{$user->full_name} has applied for {$daysTaken} days of {$request->leave_type} leave.",
                'reference_id' => $leaveRequest->id,
                'reference_type' => LeaveRequest::class,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => $status === 'auto_approved'
                ? 'Leave auto-approved successfully'
                : 'Leave request submitted for approval',
            'data' => [
                'leave_request' => $leaveRequest->load('user:id,first_name,last_name,employee_number'),
                'days_taken' => $daysTaken,
                'conflicts' => $conflicts,
                'auto_approved' => $status === 'auto_approved',
                'requires_proof' => $requiresProof,
                'service_months' => $user->getMonthsOfService(),
                'remaining_balance' => $user->fresh()->getLeaveBalance($request->leave_type),
            ]
        ], 201);
    }

    // ============================================================
    // BALANCE
    // ============================================================

    public function balance()
    {
        $user = Auth::user();

        // Get configs
        $configs = LeaveConfig::all()->keyBy('leave_type');

        // Get recent balance history
        $history = LeaveBalance::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'balances' => [
                    'annual' => [
                        'available' => (float) $user->leave_balance_annual,
                        'entitlement' => (float) ($configs['annual']->default_entitlement ?? 18),
                        'accrual_rate' => (float) ($configs['annual']->accrual_rate ?? 1.5),
                        'used_this_year' => $this->getUsedDays($user->id, 'annual'),
                    ],
                    'sick' => [
                        'available' => (float) $user->leave_balance_sick,
                        'entitlement' => (float) ($configs['sick']->default_entitlement ?? 30),
                        'accrual_rate' => (float) ($configs['sick']->accrual_rate ?? 2.5),
                        'used_this_year' => $this->getUsedDays($user->id, 'sick'),
                    ],
                    'family' => [
                        'available' => (float) $user->leave_balance_family,
                        'entitlement' => (float) ($configs['family']->default_entitlement ?? 3),
                        'used_this_year' => $this->getUsedDays($user->id, 'family'),
                    ],
                    'unpaid' => [
                        'available' => $configs['unpaid']->isInfinite() ?? false
                            ? 'infinite'
                            : (float) $user->leave_balance_unpaid,
                        'entitlement' => $configs['unpaid']->isInfinite() ?? false
                            ? 'infinite'
                            : (float) ($configs['unpaid']->default_entitlement ?? 0),
                        'used_this_year' => $this->getUsedDays($user->id, 'unpaid'),
                    ],
                    'study' => [
                        'available' => (float) $user->leave_balance_study,
                        'entitlement' => (float) ($configs['study']->default_entitlement ?? 0),
                        'used_this_year' => $this->getUsedDays($user->id, 'study'),
                    ],
                    'maternity' => [
                        'available' => (float) $user->leave_balance_maternity,
                        'entitlement' => (float) ($configs['maternity']->default_entitlement ?? 90),
                        'used_this_year' => $this->getUsedDays($user->id, 'maternity'),
                    ],
                    'paternity' => [
                        'available' => (float) $user->leave_balance_paternity,
                        'entitlement' => (float) ($configs['paternity']->default_entitlement ?? 10),
                        'used_this_year' => $this->getUsedDays($user->id, 'paternity'),
                    ],
                ],
                'history' => $history,
                'months_of_service' => $user->getMonthsOfService(),
            ]
        ], 200);
    }

    // ============================================================
    // HISTORY
    // ============================================================

    public function history(Request $request)
    {
        $user = Auth::user();

        $query = LeaveRequest::with('approver:id,first_name,last_name')
            ->where('user_id', $user->id);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }

        $requests = $query->orderBy('created_at', 'desc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => 'success',
            'data' => $requests
        ], 200);
    }

    // ============================================================
    // APPROVE (manager)
    // ============================================================

    public function approve(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leave_request_id' => 'required|exists:leave_requests,id',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $leaveRequest = LeaveRequest::findOrFail($request->leave_request_id);
        $employee = $leaveRequest->user;

        // Check permission (manager or director)
        if (!in_array($user->role, ['manager', 'director', 'admin', 'super_admin'])) {
            AuditService::logWarning('LEAVE_APPROVE_UNAUTHORIZED', 'leave_requests', $leaveRequest->id, [
                'actor_id' => $user->id,
            ]);
            return response()->json(['status' => 'error', 'message' => 'Unauthorized to approve leave'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Leave request is not pending',
                'current_status' => $leaveRequest->status
            ], 422);
        }

        $config = LeaveConfig::where('leave_type', $leaveRequest->leave_type)->firstOrFail();
        $newStatus = $this->approvalService->resolveApprovedStatus($leaveRequest, $config);

        // Deduct balance
        $this->deductBalance($employee, $leaveRequest->leave_type, $leaveRequest->days_taken, $leaveRequest);

        // Determine calendar flags
        $isApprovedForCalendar = $newStatus === 'approved';
        $isPendingProof = $newStatus === 'approved_pending_proof';

        $this->blockCalendar(
            $employee,
            $leaveRequest->start_date,
            $leaveRequest->end_date,
            $leaveRequest->leave_type,
            $leaveRequest,
            $isApprovedForCalendar,
            $isPendingProof,
            false
        );

        // Update request
        $leaveRequest->status = $newStatus;
        $leaveRequest->approved_by = $user->id;
        $leaveRequest->approved_at = now();
        if ($isPendingProof) {
            $leaveRequest->requires_proof = true;
            $leaveRequest->proof_upload_deadline = $this->approvalService->computeProofDeadline($leaveRequest, $config);
        }
        $leaveRequest->save();

        AuditService::log(
            action: 'LEAVE_APPROVED',
            tableName: 'leave_requests',
            recordId: $leaveRequest->id,
            oldValues: ['status' => 'pending'],
            newValues: [
                'status' => $newStatus,
                'comment' => $request->comment,
                'requires_proof' => $isPendingProof,
                'proof_deadline' => $leaveRequest->proof_upload_deadline?->toIso8601String(),
            ],
            logType: 'success'
        );

        Notification::create([
            'user_id' => $employee->id,
            'type' => $isPendingProof ? 'LEAVE_APPROVED_PENDING_PROOF' : 'LEAVE_APPROVED',
            'title' => $isPendingProof ? 'Leave Approved — Proof Required' : 'Leave Request Approved',
            'message' => $isPendingProof
                ? "Your {$leaveRequest->leave_type} leave ({$leaveRequest->days_taken} days) has been approved. Please upload proof by {$leaveRequest->proof_upload_deadline->format('d M Y H:i')}."
                : "Your {$leaveRequest->leave_type} leave ({$leaveRequest->days_taken} days) has been approved.",
            'reference_id' => $leaveRequest->id,
            'reference_type' => LeaveRequest::class,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => $isPendingProof
                ? 'Leave approved — proof upload required'
                : 'Leave approved successfully',
            'data' => [
                'leave_request' => $leaveRequest->fresh(),
                'remaining_balance' => $employee->fresh()->getLeaveBalance($leaveRequest->leave_type),
                'requires_proof' => $isPendingProof,
                'proof_upload_deadline' => $leaveRequest->proof_upload_deadline,
            ]
        ], 200);
    }

    // ============================================================
    // REJECT
    // ============================================================

    public function reject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leave_request_id' => 'required|exists:leave_requests,id',
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
        $leaveRequest = LeaveRequest::findOrFail($request->leave_request_id);

        // Check permission (manager or director)
        if (!in_array($user->role, ['manager', 'director', 'admin', 'super_admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Leave request is not pending'
            ], 422);
        }

        $leaveRequest->status = 'rejected';
        $leaveRequest->approved_by = $user->id;
        $leaveRequest->approved_at = now();
        $leaveRequest->rejection_reason = $request->reason;
        $leaveRequest->save();

        AuditService::log(
            action: 'LEAVE_REJECTED',
            tableName: 'leave_requests',
            recordId: $leaveRequest->id,
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'rejected', 'reason' => $request->reason],
            logType: 'success'
        );

        Notification::create([
            'user_id' => $leaveRequest->user_id,
            'type' => 'LEAVE_REJECTED',
            'title' => 'Leave Request Rejected',
            'message' => "Your leave request was rejected. Reason: {$request->reason}",
            'reference_id' => $leaveRequest->id,
            'reference_type' => LeaveRequest::class,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave rejected',
            'data' => $leaveRequest
        ], 200);
    }

    // ============================================================
    // HOLD
    // ============================================================

    public function hold(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leave_request_id' => 'required|exists:leave_requests,id',
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
        $leaveRequest = LeaveRequest::findOrFail($request->leave_request_id);

        if (!in_array($user->role, ['manager', 'director', 'admin', 'super_admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Leave request is not pending'
            ], 422);
        }

        $leaveRequest->status = 'hold';
        $leaveRequest->rejection_reason = $request->reason;
        $leaveRequest->save();

        AuditService::log(
            action: 'LEAVE_HOLD',
            tableName: 'leave_requests',
            recordId: $leaveRequest->id,
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'hold', 'reason' => $request->reason]
        );

        Notification::create([
            'user_id' => $leaveRequest->user_id,
            'type' => 'LEAVE_HOLD',
            'title' => 'Leave Request On Hold',
            'message' => "Your leave request is on hold. Reason: {$request->reason}",
            'reference_id' => $leaveRequest->id,
            'reference_type' => LeaveRequest::class,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave placed on hold',
            'data' => $leaveRequest
        ], 200);
    }

    // ============================================================
    // PARTIAL APPROVE
    // ============================================================

    public function partialApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leave_request_id' => 'required|exists:leave_requests,id',
            'approved_days' => 'required|numeric|min:0.5',
            'comment' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $leaveRequest = LeaveRequest::findOrFail($request->leave_request_id);

        if (!in_array($user->role, ['manager', 'director', 'admin', 'super_admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Leave request is not pending'
            ], 422);
        }

        if ($request->approved_days > $leaveRequest->days_taken) {
            return response()->json([
                'status' => 'error',
                'message' => 'Approved days cannot exceed requested days'
            ], 422);
        }

        $employee = $leaveRequest->user;
        $config = LeaveConfig::where('leave_type', $leaveRequest->leave_type)->firstOrFail();

        // Deduct only approved days
        $this->deductBalance($employee, $leaveRequest->leave_type, $request->approved_days, $leaveRequest);

        // Block calendar for approved days
        $approvedEndDate = $leaveRequest->start_date->copy()->addWeekdays($request->approved_days - 1);

        $requiresProof = $config->requiresProofFor((float) $request->approved_days) && !$leaveRequest->hasProof();
        $isPendingProof = $requiresProof;

        $this->blockCalendar(
            $employee,
            $leaveRequest->start_date,
            $approvedEndDate,
            $leaveRequest->leave_type,
            $leaveRequest,
            !$isPendingProof,
            $isPendingProof,
            false
        );

        $newStatus = $isPendingProof ? 'approved_pending_proof' : 'partially_approved';

        $leaveRequest->status = $newStatus;
        $leaveRequest->approved_by = $user->id;
        $leaveRequest->approved_at = now();
        $leaveRequest->partial_approved_days = $request->approved_days;
        $leaveRequest->rejection_reason = $request->comment;
        $leaveRequest->requires_proof = $requiresProof;
        if ($requiresProof) {
            $leaveRequest->proof_upload_deadline = $this->approvalService->computeProofDeadline($leaveRequest, $config);
        }
        $leaveRequest->save();

        AuditService::log(
            action: 'LEAVE_PARTIALLY_APPROVED',
            tableName: 'leave_requests',
            recordId: $leaveRequest->id,
            oldValues: ['status' => 'pending'],
            newValues: [
                'status' => $newStatus,
                'approved_days' => $request->approved_days,
                'requested_days' => $leaveRequest->days_taken,
                'requires_proof' => $requiresProof,
            ]
        );

        Notification::create([
            'user_id' => $leaveRequest->user_id,
            'type' => $isPendingProof ? 'LEAVE_PARTIALLY_APPROVED_PENDING_PROOF' : 'LEAVE_PARTIALLY_APPROVED',
            'title' => $isPendingProof
                ? 'Leave Partially Approved — Proof Required'
                : 'Leave Partially Approved',
            'message' => $isPendingProof
                ? "Your leave has been partially approved for {$request->approved_days} of {$leaveRequest->days_taken} days. Please upload proof by {$leaveRequest->proof_upload_deadline->format('d M Y H:i')}."
                : "Your leave has been partially approved for {$request->approved_days} of {$leaveRequest->days_taken} days requested.",
            'reference_id' => $leaveRequest->id,
            'reference_type' => LeaveRequest::class,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave partially approved',
            'data' => [
                'leave_request' => $leaveRequest->fresh(),
                'approved_days' => $request->approved_days,
                'remaining_balance' => $employee->fresh()->getLeaveBalance($leaveRequest->leave_type),
                'requires_proof' => $requiresProof,
            ]
        ], 200);
    }

    // ============================================================
    // CANCEL
    // ============================================================

    public function cancel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'leave_request_id' => 'required|exists:leave_requests,id',
            'reason' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $leaveRequest = LeaveRequest::where('user_id', $user->id)
            ->findOrFail($request->leave_request_id);

        if (!$leaveRequest->canCancel()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot cancel leave less than 2 days before start date, or in current status',
                'current_status' => $leaveRequest->status
            ], 422);
        }

        $wasApproved = in_array($leaveRequest->status, [
            'approved', 'auto_approved', 'partially_approved', 'approved_pending_proof'
        ]);
        $daysToReturn = $leaveRequest->partial_approved_days ?? $leaveRequest->days_taken;
        $isLate = $leaveRequest->isLateCancellation();

        // Return balance if was approved and not late cancellation
        if ($wasApproved && !$isLate) {
            $employee = $leaveRequest->user;
            $currentBalance = $employee->getLeaveBalance($leaveRequest->leave_type);
            $newBalance = $currentBalance + $daysToReturn;
            $employee->addLeaveBalance($leaveRequest->leave_type, $daysToReturn);

            // Log the balance restoration
            LeaveBalance::create([
                'user_id' => $employee->id,
                'leave_type' => $leaveRequest->leave_type,
                'balance_before' => $currentBalance,
                'balance_after' => $newBalance,
                'adjustment_reason' => 'Leave cancellation - balance restored',
                'reference_id' => $leaveRequest->id,
                'reference_type' => 'leave_cancellation',
                'adjusted_by' => $user->id,
                'adjusted_at' => now(),
            ]);
        }

        // Remove calendar entries
        LeaveCalendar::where('leave_request_id', $leaveRequest->id)->delete();

        $leaveRequest->status = 'cancelled';
        $leaveRequest->rejection_reason = $request->reason ?? 'Cancelled by employee';
        $leaveRequest->save();

        AuditService::log(
            action: 'LEAVE_CANCELLED',
            tableName: 'leave_requests',
            recordId: $leaveRequest->id,
            oldValues: ['status' => $leaveRequest->getOriginal('status')],
            newValues: [
                'status' => 'cancelled',
                'days_returned' => $wasApproved && !$isLate ? $daysToReturn : 0,
                'is_late_cancellation' => $isLate,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => $isLate
                ? 'Leave cancelled (late cancellation - days forfeited)'
                : 'Leave cancelled successfully',
            'data' => [
                'leave_request' => $leaveRequest->fresh(),
                'days_returned' => $wasApproved && !$isLate ? $daysToReturn : 0,
                'is_late_cancellation' => $isLate,
            ]
        ], 200);
    }

    // ============================================================
    // CALENDAR
    // ============================================================

    public function calendar(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'month' => 'nullable|date_format:Y-m',
            'department' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $month = $request->month ? Carbon::parse($request->month . '-01') : now()->startOfMonth();
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $query = LeaveCalendar::with([
            'user:id,first_name,last_name,employee_number,department',
            'leaveRequest:id,leave_type,reason,status'
        ])
            ->whereBetween('leave_date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
            ->where(function ($q) {
                $q->where('is_approved', true)
                  ->orWhere('is_pending_proof', true)
                  ->orWhere('is_unpaid_conversion', true);
            });

        if ($request->has('department')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('department', $request->department);
            });
        }

        // Filter by specific user
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // For regular employees, show team view only
        if (!in_array($user->role, ['admin', 'director', 'manager', 'super_admin'])) {
            // Show team (same department)
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('department', $user->department);
            });
        }

        $entries = $query->orderBy('leave_date')->get();

        // Group by date for easier viewing
        $grouped = $entries->groupBy(function ($entry) {
            return $entry->leave_date->format('Y-m-d');
        })->map(function ($dayEntries, $date) {
            return [
                'date' => $date,
                'count' => $dayEntries->count(),
                'employees' => $dayEntries->map(function ($e) {
                    return [
                        'id' => $e->user->id,
                        'name' => $e->user->full_name,
                        'employee_number' => $e->user->employee_number,
                        'department' => $e->user->department,
                        'leave_type' => $e->leave_type,
                        'is_pending_proof' => (bool) $e->is_pending_proof,
                        'is_unpaid_conversion' => (bool) $e->is_unpaid_conversion,
                    ];
                }),
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'month' => $month->format('F Y'),
                'total_leaves' => $entries->count(),
                'by_date' => $grouped,
                'by_type' => [
                    'annual' => $entries->where('leave_type', 'annual')->count(),
                    'sick' => $entries->where('leave_type', 'sick')->count(),
                    'family' => $entries->where('leave_type', 'family')->count(),
                    'unpaid' => $entries->where('leave_type', 'unpaid')->count(),
                    'maternity' => $entries->where('leave_type', 'maternity')->count(),
                    'paternity' => $entries->where('leave_type', 'paternity')->count(),
                ],
            ]
        ], 200);
    }

    // ============================================================
    // PENDING
    // ============================================================

    public function pending(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['manager', 'director', 'admin', 'super_admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $query = LeaveRequest::with(['user:id,first_name,last_name,employee_number,department'])
            ->whereIn('status', ['pending', 'hold']);

        // Manager sees own team
        if ($user->role === 'manager') {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('manager_id', $user->id);
            });
        }

        $requests = $query->orderBy('created_at', 'asc')
            ->paginate($request->per_page ?? 15);

        return response()->json([
            'status' => 'success',
            'data' => $requests
        ], 200);
    }

    // ============================================================
    // ADJUST BALANCE
    // ============================================================

    public function adjustBalance(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'director', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only Admin and Director can adjust balances'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'leave_type' => 'required|in:annual,sick,family,unpaid,study,maternity,paternity',
            'adjustment' => 'required|numeric',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $employee = User::findOrFail($request->user_id);
        $oldBalance = $employee->getLeaveBalance($request->leave_type);
        $newBalance = max(0, $oldBalance + $request->adjustment);

        // Update balance
        match ($request->leave_type) {
            'annual'    => $employee->leave_balance_annual = $newBalance,
            'sick'      => $employee->leave_balance_sick = $newBalance,
            'family'    => $employee->leave_balance_family = $newBalance,
            'unpaid'    => $employee->leave_balance_unpaid = $newBalance,
            'study'     => $employee->leave_balance_study = $newBalance,
            'maternity' => $employee->leave_balance_maternity = $newBalance,
            'paternity' => $employee->leave_balance_paternity = $newBalance,
            default     => null,
        };
        $employee->save();

        // Log adjustment
        LeaveBalance::create([
            'user_id' => $employee->id,
            'leave_type' => $request->leave_type,
            'balance_before' => $oldBalance,
            'balance_after' => $newBalance,
            'adjustment_reason' => $request->reason,
            'reference_type' => 'manual_adjustment',
            'adjusted_by' => $user->id,
            'adjusted_at' => now(),
        ]);

        AuditService::log(
            action: 'LEAVE_BALANCE_ADJUSTED',
            tableName: 'users',
            recordId: $employee->id,
            oldValues: [$request->leave_type => $oldBalance],
            newValues: [$request->leave_type => $newBalance, 'reason' => $request->reason]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Balance adjusted successfully',
            'data' => [
                'employee' => $employee->full_name,
                'leave_type' => $request->leave_type,
                'old_balance' => $oldBalance,
                'new_balance' => $newBalance,
                'adjustment' => $request->adjustment,
            ]
        ], 200);
    }

    // ============================================================
    // CONFLICTS
    // ============================================================

    public function conflicts(Request $request, $dateRange)
    {
        // dateRange format: YYYY-MM-DD_YYYY-MM-DD
        $dates = explode('_', $dateRange);
        if (count($dates) !== 2) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid date range format. Use YYYY-MM-DD_YYYY-MM-DD'
            ], 422);
        }

        $user = Auth::user();
        $startDate = Carbon::parse($dates[0]);
        $endDate = Carbon::parse($dates[1]);

        // Find team members (same department) with approved leave in this range
        $conflicts = LeaveCalendar::with(['user:id,first_name,last_name,employee_number,department'])
            ->where(function ($q) {
                $q->where('is_approved', true)
                  ->orWhere('is_pending_proof', true)
                  ->orWhere('is_unpaid_conversion', true);
            })
            ->whereBetween('leave_date', [$startDate, $endDate])
            ->where('user_id', '!=', $user->id)
            ->whereHas('user', function ($q) use ($user) {
                $q->where('department', $user->department);
            })
            ->get()
            ->groupBy('user_id')
            ->map(function ($entries) {
                $user = $entries->first()->user;
                return [
                    'user_id' => $user->id,
                    'name' => $user->full_name,
                    'employee_number' => $user->employee_number,
                    'leave_dates' => $entries->pluck('leave_date')->map(fn($d) => $d->format('Y-m-d')),
                    'conflicting_days' => $entries->count(),
                ];
            })
            ->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'date_range' => [
                    'start' => $startDate->format('Y-m-d'),
                    'end' => $endDate->format('Y-m-d'),
                ],
                'has_conflicts' => $conflicts->count() > 0,
                'conflicts' => $conflicts,
            ]
        ], 200);
    }

    // ============================================================
    // TEAM REPORT
    // ============================================================

    public function teamReport(Request $request)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['manager', 'director', 'admin', 'super_admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'year' => 'nullable|integer|min:2020|max:2100',
            'department' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $year = $request->year ?? now()->year;

        $query = LeaveRequest::with('user:id,first_name,last_name,employee_number,department')
            ->whereYear('start_date', $year)
            ->whereIn('status', ['approved', 'auto_approved', 'partially_approved', 'approved_pending_proof']);

        if ($request->has('department')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('department', $request->department);
            });
        } elseif ($user->role === 'manager') {
            $query->whereHas('user', function ($q) use ($user) {
                $q->where('manager_id', $user->id);
            });
        }

        $requests = $query->get();

        $byDepartment = $requests->groupBy('user.department')->map(function ($group, $dept) {
            return [
                'department' => $dept ?? 'Unassigned',
                'total_employees' => $group->pluck('user_id')->unique()->count(),
                'total_leave_days' => $group->sum(function ($r) {
                    return $r->partial_approved_days ?? $r->days_taken;
                }),
                'by_type' => [
                    'annual' => $group->where('leave_type', 'annual')->sum('days_taken'),
                    'sick' => $group->where('leave_type', 'sick')->sum('days_taken'),
                    'family' => $group->where('leave_type', 'family')->sum('days_taken'),
                    'unpaid' => $group->where('leave_type', 'unpaid')->sum('days_taken'),
                    'maternity' => $group->where('leave_type', 'maternity')->sum('days_taken'),
                    'paternity' => $group->where('leave_type', 'paternity')->sum('days_taken'),
                ],
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'year' => $year,
                'total_requests' => $requests->count(),
                'total_days' => $requests->sum('days_taken'),
                'by_department' => $byDepartment,
            ]
        ], 200);
    }

    // ============================================================
    // PRIVATE HELPERS
    // ============================================================

    private function calculateBusinessDays(Carbon $start, Carbon $end): float
    {
        $days = 0;
        $current = $start->copy();

        while ($current->lte($end)) {
            if (!$current->isWeekend()) {
                $days++;
            }
            $current->addDay();
        }

        return $days;
    }

    /**
     * Check for team conflicts
     */
    private function checkConflicts(User $user, Carbon $start, Carbon $end): array
    {
        return LeaveCalendar::with('user:id,first_name,last_name')
            ->where(function ($q) {
                $q->where('is_approved', true)
                  ->orWhere('is_pending_proof', true)
                  ->orWhere('is_unpaid_conversion', true);
            })
            ->whereBetween('leave_date', [$start, $end])
            ->where('user_id', '!=', $user->id)
            ->whereHas('user', function ($q) use ($user) {
                $q->where('department', $user->department);
            })
            ->get()
            ->groupBy('user_id')
            ->map(function ($entries) {
                return [
                    'name' => $entries->first()->user->full_name,
                    'days' => $entries->count(),
                ];
            })
            ->values()
            ->toArray();
    }

    /**
     * Deduct leave balance
     */
    private function deductBalance(User $user, string $leaveType, float $days, LeaveRequest $leaveRequest): void
    {
        if (in_array($leaveType, ['unpaid', 'study'])) {
            // Unpaid/study: infinite → just log
            LeaveBalance::create([
                'user_id' => $user->id,
                'leave_type' => $leaveType,
                'balance_before' => $user->getLeaveBalance($leaveType),
                'balance_after' => $user->getLeaveBalance($leaveType),
                'adjustment_reason' => "Leave request {$leaveType} (no balance deduction)",
                'reference_id' => $leaveRequest->id,
                'reference_type' => 'leave_request',
                'adjusted_by' => Auth::id(),
                'adjusted_at' => now(),
            ]);
            return;
        }

        $config = LeaveConfig::where('leave_type', $leaveType)->first();
        if ($config && $config->isInfinite()) {
            LeaveBalance::create([
                'user_id' => $user->id,
                'leave_type' => $leaveType,
                'balance_before' => $user->getLeaveBalance($leaveType),
                'balance_after' => $user->getLeaveBalance($leaveType),
                'adjustment_reason' => "Leave request {$leaveType} (infinite balance)",
                'reference_id' => $leaveRequest->id,
                'reference_type' => 'leave_request',
                'adjusted_by' => Auth::id(),
                'adjusted_at' => now(),
            ]);
            return;
        }

        $oldBalance = $user->getLeaveBalance($leaveType);
        $user->deductLeaveBalance($leaveType, $days);

        // Log the deduction
        LeaveBalance::create([
            'user_id' => $user->id,
            'leave_type' => $leaveType,
            'balance_before' => $oldBalance,
            'balance_after' => $oldBalance - $days,
            'adjustment_reason' => 'Leave request approved',
            'reference_id' => $leaveRequest->id,
            'reference_type' => 'leave_request',
            'adjusted_by' => Auth::id(),
            'adjusted_at' => now(),
        ]);
    }

    /**
     * Block calendar days for approved leave
     */
    private function blockCalendar(
        User $user,
        Carbon $start,
        Carbon $end,
        string $leaveType,
        LeaveRequest $leaveRequest,
        bool $isApproved,
        bool $isPendingProof,
        bool $isUnpaidConversion
    ): void {
        $current = $start->copy();
        while ($current->lte($end)) {
            if (!$current->isWeekend()) {
                LeaveCalendar::updateOrCreate(
                    [
                        'user_id' => $user->id,
                        'leave_date' => $current->format('Y-m-d'),
                    ],
                    [
                        'leave_type' => $leaveType,
                        'leave_request_id' => $leaveRequest->id,
                        'is_approved' => $isApproved,
                        'is_pending_proof' => $isPendingProof,
                        'is_unpaid_conversion' => $isUnpaidConversion,
                    ]
                );
            }
            $current->addDay();
        }
    }

    /**
     * Get used days for a leave type in current year
     */
    private function getUsedDays(int $userId, string $leaveType): float
    {
        return LeaveRequest::where('user_id', $userId)
            ->where('leave_type', $leaveType)
            ->whereYear('start_date', now()->year)
            ->whereIn('status', ['approved', 'auto_approved', 'partially_approved', 'approved_pending_proof', 'converted_to_unpaid'])
            ->sum('days_taken');
    }
}