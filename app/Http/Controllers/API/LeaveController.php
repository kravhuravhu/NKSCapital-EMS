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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class LeaveController extends Controller
{
    /**
     * POST /api/v1/leave/apply
     * Employee submits leave request
     */
    public function apply(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'leave_type' => 'required|in:annual,sick,family,unpaid,study,maternity',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'nullable|string|max:1000',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:10240',
        ]);

        if ($validator->fails()) {
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
            return response()->json([
                'status' => 'error',
                'message' => 'No business days in the selected range'
            ], 422);
        }

        // Get leave config
        $config = LeaveConfig::where('leave_type', $request->leave_type)->first();

        // Check balance for leave types that need it
        if (in_array($request->leave_type, ['annual', 'sick', 'family'])) {
            $balance = $user->getLeaveBalance($request->leave_type);
            if ($balance < $daysTaken) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient leave balance',
                    'required' => $daysTaken,
                    'available' => $balance,
                    'leave_type' => $request->leave_type,
                ], 422);
            }
        }

        // Check for conflicts (teammates on leave)
        $conflicts = $this->checkConflicts($user, $startDate, $endDate);

        // Validate attachment requirement
        if ($config && $config->requires_attachment && $daysTaken >= $config->min_days_attachment) {
            if (!$request->hasFile('attachment')) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Attachment is required for {$request->leave_type} leave of {$daysTaken} days or more",
                    'requires_attachment' => true,
                ], 422);
            }
        }

        // Handle attachment upload
        $attachmentPath = null;
        $attachmentHash = null;
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $filename = 'leave-attachment-' . $user->id . '-' . time() . '.' . $file->getClientOriginalExtension();
            $attachmentPath = $file->storeAs('leave-attachments', $filename, 'public');
            $attachmentHash = hash_file('sha256', $file->getRealPath());
        }

        // Determine if auto-approval applies
        $shouldAutoApprove = false;
        $autoApproveReason = '';

        // Sick leave with certificate > 2 days
        if ($request->leave_type === 'sick' && $attachmentPath && $daysTaken >= $config->auto_approve_min_days) {
            $shouldAutoApprove = true;
            $autoApproveReason = 'Sick leave with medical certificate';
        }
        // Family responsibility <= 3 days, employed > 4 months
        elseif ($request->leave_type === 'family' && $daysTaken <= $config->family_auto_approve_max_days) {
            $monthsOfService = $user->hire_date ? $user->hire_date->diffInMonths(now()) : 0;
            if ($monthsOfService >= $config->family_auto_approve_min_months) {
                $shouldAutoApprove = true;
                $autoApproveReason = 'Family responsibility leave (eligible)';
            }
        }

        $status = $shouldAutoApprove ? 'auto_approved' : 'pending';

        // Create leave request
        $leaveRequest = LeaveRequest::create([
            'user_id' => $user->id,
            'leave_type' => $request->leave_type,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'days_taken' => $daysTaken,
            'reason' => $request->reason,
            'attachment_path' => $attachmentPath,
            'attachment_hash' => $attachmentHash,
            'status' => $status,
            'approved_by' => $shouldAutoApprove ? $user->id : null,
            'approved_at' => $shouldAutoApprove ? now() : null,
        ]);

        // If auto-approved, deduct balance and block calendar
        if ($shouldAutoApprove) {
            $this->deductBalance($user, $request->leave_type, $daysTaken, $leaveRequest);
            $this->blockCalendar($user, $startDate, $endDate, $request->leave_type, $leaveRequest, true);
        }

        AuditService::log(
            action: $shouldAutoApprove ? 'LEAVE_AUTO_APPROVED' : 'LEAVE_APPLIED',
            tableName: 'leave_requests',
            recordId: $leaveRequest->id,
            newValues: [
                'leave_type' => $request->leave_type,
                'days_taken' => $daysTaken,
                'status' => $status,
                'auto_approve_reason' => $autoApproveReason,
            ]
        );

        // Notify manager if pending
        if (!$shouldAutoApprove && $user->manager_id) {
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
            'message' => $shouldAutoApprove 
                ? 'Leave auto-approved successfully' 
                : 'Leave request submitted for approval',
            'data' => [
                'leave_request' => $leaveRequest->load('user:id,first_name,last_name,employee_number'),
                'days_taken' => $daysTaken,
                'conflicts' => $conflicts,
                'auto_approved' => $shouldAutoApprove,
                'auto_approve_reason' => $autoApproveReason,
                'remaining_balance' => $user->fresh()->getLeaveBalance($request->leave_type),
            ]
        ], 201);
    }

    /**
     * GET /api/v1/leave/balance
     * Get current user's leave balances
     */
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
                ],
                'history' => $history,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/leave/history
     */
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

    /**
     * POST /api/v1/leave/approve
     */
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
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to approve leave'
            ], 403);
        }

        if ($leaveRequest->status !== 'pending') {
            return response()->json([
                'status' => 'error',
                'message' => 'Leave request is not pending',
                'current_status' => $leaveRequest->status
            ], 422);
        }

        // Deduct balance
        $this->deductBalance($employee, $leaveRequest->leave_type, $leaveRequest->days_taken, $leaveRequest);

        // Block calendar
        $this->blockCalendar(
            $employee,
            $leaveRequest->start_date,
            $leaveRequest->end_date,
            $leaveRequest->leave_type,
            $leaveRequest,
            true
        );

        // Update request
        $leaveRequest->status = 'approved';
        $leaveRequest->approved_by = $user->id;
        $leaveRequest->approved_at = now();
        $leaveRequest->save();

        AuditService::log(
            action: 'LEAVE_APPROVED',
            tableName: 'leave_requests',
            recordId: $leaveRequest->id,
            oldValues: ['status' => 'pending'],
            newValues: ['status' => 'approved', 'comment' => $request->comment]
        );

        // Notify employee
        Notification::create([
            'user_id' => $employee->id,
            'type' => 'LEAVE_APPROVED',
            'title' => 'Leave Request Approved',
            'message' => "Your {$leaveRequest->leave_type} leave ({$leaveRequest->days_taken} days) has been approved.",
            'reference_id' => $leaveRequest->id,
            'reference_type' => LeaveRequest::class,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave approved successfully',
            'data' => [
                'leave_request' => $leaveRequest->fresh(),
                'remaining_balance' => $employee->fresh()->getLeaveBalance($leaveRequest->leave_type),
            ]
        ], 200);
    }

    /**
     * POST /api/v1/leave/reject
     */
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

        if (!in_array($user->role, ['manager', 'director', 'admin', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized'
            ], 403);
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
            newValues: ['status' => 'rejected', 'reason' => $request->reason]
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

    /**
     * POST /api/v1/leave/hold
     */
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

    /**
     * POST /api/v1/leave/partial-approve
     */
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

        // Deduct only approved days
        $this->deductBalance($employee, $leaveRequest->leave_type, $request->approved_days, $leaveRequest);

        // Block calendar only for approved days
        $approvedEndDate = $leaveRequest->start_date->copy()->addWeekdays($request->approved_days - 1);
        $this->blockCalendar(
            $employee,
            $leaveRequest->start_date,
            $approvedEndDate,
            $leaveRequest->leave_type,
            $leaveRequest,
            true
        );

        $leaveRequest->status = 'partially_approved';
        $leaveRequest->approved_by = $user->id;
        $leaveRequest->approved_at = now();
        $leaveRequest->partial_approved_days = $request->approved_days;
        $leaveRequest->rejection_reason = $request->comment;
        $leaveRequest->save();

        AuditService::log(
            action: 'LEAVE_PARTIALLY_APPROVED',
            tableName: 'leave_requests',
            recordId: $leaveRequest->id,
            oldValues: ['status' => 'pending'],
            newValues: [
                'status' => 'partially_approved',
                'approved_days' => $request->approved_days,
                'requested_days' => $leaveRequest->days_taken,
            ]
        );

        Notification::create([
            'user_id' => $leaveRequest->user_id,
            'type' => 'LEAVE_PARTIALLY_APPROVED',
            'title' => 'Leave Partially Approved',
            'message' => "Your leave has been partially approved for {$request->approved_days} of {$leaveRequest->days_taken} days requested.",
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
            ]
        ], 200);
    }

    /**
     * POST /api/v1/leave/cancel
     */
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

        $wasApproved = in_array($leaveRequest->status, ['approved', 'auto_approved', 'partially_approved']);
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
            oldValues: ['status' => 'approved'],
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

    /**
     * GET /api/v1/leave/calendar
     */
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
            'leaveRequest:id,leave_type,reason'
        ])
            ->whereBetween('leave_date', [$monthStart->format('Y-m-d'), $monthEnd->format('Y-m-d')])
            ->where('is_approved', true);

        // Filter by department
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
                ],
            ]
        ], 200);
    }

    /**
     * GET /api/v1/leave/pending
     */
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

    /**
     * POST /api/v1/leave/balance/adjust
     */
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
            'leave_type' => 'required|in:annual,sick,family',
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
            'annual' => $employee->leave_balance_annual = $newBalance,
            'sick' => $employee->leave_balance_sick = $newBalance,
            default => null,
        };
        $employee->save();

        // Log adjustment
        LeaveBalance::create([
            'user_id' => $employee->id,
            'leave_type' => $request->leave_type,
            'balance_before' => $oldBalance,
            'balance_after' => $newBalance,
            'adjustment_reason' => $request->reason,
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

    /**
     * GET /api/v1/leave/conflicts/{dateRange}
     */
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
            ->where('is_approved', true)
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

    /**
     * GET /api/v1/leave/team-report
     */
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
            ->whereIn('status', ['approved', 'auto_approved', 'partially_approved']);

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

    // ========================================
    // PRIVATE HELPER METHODS
    // ========================================

    /**
     * Calculate business days between two dates (excludes weekends)
     */
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
        $conflicts = LeaveCalendar::with('user:id,first_name,last_name')
            ->where('is_approved', true)
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

        return $conflicts;
    }

    /**
     * Deduct leave balance
     */
    private function deductBalance(User $user, string $leaveType, float $days, LeaveRequest $leaveRequest): void
    {
        if ($leaveType === 'unpaid' || $leaveType === 'study' || $leaveType === 'maternity') {
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
        bool $isApproved
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
            ->whereIn('status', ['approved', 'auto_approved', 'partially_approved'])
            ->sum('days_taken');
    }
}