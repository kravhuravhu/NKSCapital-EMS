<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\ApprovalDelegation;
use App\Models\User;
use App\Services\DelegationService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class DelegationController extends Controller
{
    protected DelegationService $delegationService;

    public function __construct(DelegationService $delegationService)
    {
        $this->delegationService = $delegationService;
    }

    /**
     * GET /api/v1/admin/delegation/list
     */
    public function list(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'director', 'manager']);

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|in:active,upcoming,expired,revoked,all',
            'original_approver_id' => 'nullable|exists:users,id',
            'delegate_id' => 'nullable|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = ApprovalDelegation::with([
            'originalApprover:id,first_name,last_name,email,role',
            'delegate:id,first_name,last_name,email,role',
            'createdBy:id,first_name,last_name',
            'revokedBy:id,first_name,last_name',
        ]);

        $status = $request->status ?? 'all';
        if ($status === 'active')   $query->active();
        if ($status === 'upcoming') $query->upcoming();
        if ($status === 'expired')  $query->expired();

        if ($request->filled('original_approver_id')) $query->where('original_approver_id', $request->original_approver_id);
        if ($request->filled('delegate_id')) $query->where('delegate_id', $request->delegate_id);

        $items = $query->orderBy('start_date', 'desc')->paginate($request->per_page ?? 20);

        return response()->json(['status' => 'success', 'data' => $items], 200);
    }

    /**
     * GET /api/v1/admin/delegation/{id}
     */
    public function show($id)
    {
        $this->requireRole(['admin', 'super_admin', 'director', 'manager']);

        $delegation = ApprovalDelegation::with([
            'originalApprover:id,first_name,last_name,email,role',
            'delegate:id,first_name,last_name,email,role',
            'createdBy:id,first_name,last_name',
            'revokedBy:id,first_name,last_name',
        ])->findOrFail($id);

        return response()->json(['status' => 'success', 'data' => $delegation], 200);
    }

    /**
     * POST /api/v1/admin/delegation/create
     */
    public function create(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'director', 'manager']);

        $validator = Validator::make($request->all(), [
            'original_approver_id' => 'required|exists:users,id',
            'delegate_id' => 'required|exists:users,id',
            'delegation_type' => 'nullable|in:timesheet,leave,both',
            'title' => 'nullable|string|max:255',
            'delegation_modules' => 'nullable|array',
            'delegation_modules.*' => 'string|in:all,timesheet,leave,contract,asset,recruitment,offer',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'reason' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'auto_activate' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $delegation = $this->delegationService->createDelegation($request->all(), $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Delegation created',
            'data' => $delegation,
        ], 201);
    }

    /**
     * PUT /api/v1/admin/delegation/update/{id}
     */
    public function update(Request $request, $id)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'director']);

        $delegation = ApprovalDelegation::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'delegate_id' => 'nullable|exists:users,id',
            'delegation_type' => 'nullable|in:timesheet,leave,both',
            'title' => 'nullable|string|max:255',
            'delegation_modules' => 'nullable|array',
            'delegation_modules.*' => 'string|in:all,timesheet,leave,contract,asset,recruitment,offer',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'reason' => 'nullable|string|max:1000',
            'is_active' => 'nullable|boolean',
            'auto_activate' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $delegation = $this->delegationService->updateDelegation($delegation, $request->all(), $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Delegation updated',
            'data' => $delegation,
        ], 200);
    }

    /**
     * POST /api/v1/admin/delegation/activate/{id}
     */
    public function activate(Request $request, $id)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'director']);

        $delegation = ApprovalDelegation::findOrFail($id);

        try {
            $delegation = $this->delegationService->activateDelegation($delegation, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Delegation activated',
            'data' => $delegation,
        ], 200);
    }

    /**
     * POST /api/v1/admin/delegation/revoke
     */
    public function revoke(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'director']);

        $validator = Validator::make($request->all(), [
            'delegation_id' => 'required|exists:approval_delegations,id',
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $delegation = ApprovalDelegation::findOrFail($request->delegation_id);

        try {
            $delegation = $this->delegationService->revokeDelegation($delegation, $request->reason, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Delegation revoked',
            'data' => $delegation,
        ], 200);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function requireRole(array $roles): User
    {
        $user = Auth::user();
        if (!in_array($user->role, $roles)) {
            AuditService::logWarning('DELEGATION_ACCESS_DENIED', 'approval_delegations', 0, [
                'actor_id' => $user->id,
                'required' => $roles,
                'actual' => $user->role,
            ]);
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }
        return $user;
    }
}