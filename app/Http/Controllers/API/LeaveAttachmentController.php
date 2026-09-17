<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LeaveRequest;
use App\Models\LeaveConfig;
use App\Models\Notification;
use App\Services\AuditService;
use App\Services\LeaveProofService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class LeaveAttachmentController extends Controller
{
    protected LeaveProofService $proofService;

    public function __construct(LeaveProofService $proofService)
    {
        $this->proofService = $proofService;
    }

    /**
     * POST /api/v1/leave/{leaveRequestId}/upload-attachment
     * Employee uploads proof after leave.
     */
    public function upload(Request $request, $leaveRequestId)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'attachment' => 'required|file|mimes:pdf,jpg,jpeg,png,zip|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $leaveRequest = LeaveRequest::where('user_id', $user->id)
            ->findOrFail($leaveRequestId);

        if (!in_array($leaveRequest->status, ['approved_pending_proof', 'partially_approved', 'approved'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'This leave request does not require proof upload',
                'current_status' => $leaveRequest->status,
            ], 422);
        }

        $file = $request->file('attachment');
        $filename = 'leave-proof-' . $leaveRequest->id . '-' . time() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('leave-attachments', $filename, 'public');
        $hash = hash_file('sha256', $file->getRealPath());

        $this->proofService->markProofUploaded($leaveRequest, $path, $hash);

        AuditService::log(
            action: 'LEAVE_PROOF_UPLOADED',
            tableName: 'leave_requests',
            recordId: $leaveRequest->id,
            newValues: [
                'attachment_path' => $path,
                'attachment_hash' => $hash,
            ],
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Proof uploaded successfully. Awaiting manager review.',
            'data' => [
                'leave_request_id' => $leaveRequest->id,
                'status' => $leaveRequest->fresh()->status,
                'proof_uploaded_at' => $leaveRequest->fresh()->proof_uploaded_at,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/leave/{leaveRequestId}/approve-proof
     * Manager approves proof → status becomes 'approved'.
     */
    public function approveProof(Request $request, $leaveRequestId)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['manager', 'director', 'admin', 'super_admin'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized to approve proof'
            ], 403);
        }

        $leaveRequest = LeaveRequest::findOrFail($leaveRequestId);

        if (empty($leaveRequest->proof_uploaded_at)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No proof uploaded yet'
            ], 422);
        }

        $this->proofService->approveProof($leaveRequest, $user);

        AuditService::log(
            action: 'LEAVE_PROOF_APPROVED',
            tableName: 'leave_requests',
            recordId: $leaveRequest->id,
            oldValues: ['status' => $leaveRequest->getOriginal('status')],
            newValues: [
                'status' => 'approved',
                'proof_approved_by' => $user->id,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Proof approved. Leave fully approved.',
            'data' => $leaveRequest->fresh(),
        ], 200);
    }

    /**
     * GET /api/v1/leave/{leaveRequestId}/proof-status
     */
    public function status($leaveRequestId)
    {
        $user = Auth::user();
        $leaveRequest = LeaveRequest::findOrFail($leaveRequestId);

        if ($user->id !== $leaveRequest->user_id
            && !in_array($user->role, ['manager', 'director', 'admin', 'super_admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'leave_request_id' => $leaveRequest->id,
                'status' => $leaveRequest->status,
                'requires_proof' => $leaveRequest->requires_proof,
                'proof_uploaded_at' => $leaveRequest->proof_uploaded_at,
                'proof_upload_deadline' => $leaveRequest->proof_upload_deadline,
                'proof_reminder_count' => $leaveRequest->proof_reminder_count,
                'last_proof_reminder_at' => $leaveRequest->last_proof_reminder_at,
                'is_overdue' => $leaveRequest->isOverdueForProof(),
                'proof_approved_at' => $leaveRequest->proof_approved_at,
            ]
        ], 200);
    }
}