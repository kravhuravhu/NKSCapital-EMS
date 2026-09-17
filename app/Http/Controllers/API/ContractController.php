<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\User;
use App\Services\ContractService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ContractController extends Controller
{
    protected ContractService $contractService;

    public function __construct(ContractService $contractService)
    {
        $this->contractService = $contractService;
    }

    /**
     * POST /api/v1/contract/upload
     */
    public function upload(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'file' => 'required|file|mimes:pdf|max:20480',
            'effective_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:effective_date',
            'probation_end_date' => 'nullable|date|after:effective_date',
            'salary_annual' => 'nullable|numeric|min:0',
            'notice_period_days' => 'nullable|integer|min:0',
            'position' => 'nullable|string|max:255',
            'contract_type' => 'nullable|in:permanent,fixed_term,consulting,internship',
            'status' => 'nullable|in:draft,pending_signature,active',
            'parent_contract_id' => 'nullable|exists:contracts,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $employee = User::findOrFail($request->user_id);
        $contract = $this->contractService->uploadContract(
            $employee,
            $request->file('file'),
            $request->all(),
            $user
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Contract uploaded successfully',
            'data' => $contract
        ], 201);
    }

    /**
     * GET /api/v1/contract/view/{id}
     */
    public function view($id)
    {
        $user = Auth::user();
        $contract = Contract::with([
            'user:id,first_name,last_name,employee_number,email,department,position',
            'uploadedBy:id,first_name,last_name',
            'parentContract:id,version,effective_date',
        ])->findOrFail($id);

        if (!$this->canView($user, $contract)) {
            AuditService::logWarning('CONTRACT_VIEW_UNAUTHORIZED', 'contracts', $contract->id, [
                'actor_id' => $user->id,
                'target_user_id' => $contract->user_id,
            ]);
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        return response()->json(['status' => 'success', 'data' => $contract], 200);
    }

    /**
     * GET /api/v1/contract/download/{id}
     */
    public function download($id, Request $request)
    {
        $user = Auth::user();
        $contract = Contract::findOrFail($id);

        if (!$this->canView($user, $contract)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $which = $request->query('signed') === '1' && $contract->signed_file_path
            ? $contract->signed_file_path
            : $contract->file_path;

        if (!Storage::disk('public')->exists($which)) {
            return response()->json(['status' => 'error', 'message' => 'File not found'], 404);
        }

        AuditService::log(
            action: 'CONTRACT_DOWNLOADED',
            tableName: 'contracts',
            recordId: $contract->id,
            newValues: ['signed' => (bool) $request->query('signed')],
            logType: 'success'
        );

        return response()->download(
            Storage::disk('public')->path($which),
            basename($which),
            ['Content-Type' => 'application/pdf']
        );
    }

    /**
     * POST /api/v1/contract/renew
     */
    public function renew(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin', 'director', 'manager'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'contract_id' => 'required|exists:contracts,id',
            'effective_date' => 'required|date',
            'expiry_date' => 'nullable|date|after:effective_date',
            'salary_annual' => 'nullable|numeric|min:0',
            'notice_period_days' => 'nullable|integer|min:0',
            'position' => 'nullable|string|max:255',
            'contract_type' => 'nullable|in:permanent,fixed_term,consulting,internship',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $old = Contract::findOrFail($request->contract_id);
        $new = $this->contractService->renewContract($old, $request->all(), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Contract renewed successfully',
            'data' => [
                'old_contract' => $old->fresh(),
                'new_contract' => $new,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/contract/status/update
     */
    public function updateStatus(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin', 'director'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'contract_id' => 'required|exists:contracts,id',
            'status' => 'required|in:active,expired,terminated,renewed,draft,pending_signature,superseded',
            'reason' => 'nullable|string|max:1000',
            'effective_date' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $contract = Contract::findOrFail($request->contract_id);
        $contract = $this->contractService->updateStatus(
            $contract,
            $request->status,
            $request->all(),
            $user
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Contract status updated',
            'data' => $contract
        ], 200);
    }

    /**
     * POST /api/v1/contract/sign/{id}
     */
    public function sign(Request $request, $id)
    {
        $user = Auth::user();
        $contract = Contract::findOrFail($id);

        // Employee can sign their own; admins can sign on behalf
        if ($user->id !== $contract->user_id && !in_array($user->role, ['admin', 'super_admin', 'director'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'signed_file' => 'required|file|mimes:pdf|max:20480',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $contract = $this->contractService->signContract($contract, $request->file('signed_file'), $user);

        return response()->json([
            'status' => 'success',
            'message' => 'Contract signed and activated',
            'data' => $contract
        ], 200);
    }

    /**
     * GET /api/v1/contract/expiring
     */
    public function expiring(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin', 'manager', 'director'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'days' => 'nullable|integer|in:7,14,30,60,90',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $days = (int) ($request->days ?? 30);

        $query = Contract::with(['user:id,first_name,last_name,employee_number,email,department'])
            ->active()
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);

        if ($user->role === 'manager') {
            $query->whereHas('user', fn ($q) => $q->where('manager_id', $user->id));
        }

        $contracts = $query->orderBy('expiry_date')->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'version' => $c->version,
                'employee' => $c->user,
                'contract_type' => $c->contract_type,
                'effective_date' => $c->effective_date?->toDateString(),
                'expiry_date' => $c->expiry_date?->toDateString(),
                'days_until_expiry' => $c->daysUntilExpiry(),
                'salary_annual' => $c->salary_annual,
                'alerts_sent' => $c->expiry_alerts_sent,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'window_days' => $days,
                'count' => $contracts->count(),
                'contracts' => $contracts,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/contract/history/{userId}
     */
    public function history($userId)
    {
        $user = Auth::user();
        $target = User::findOrFail($userId);

        if (!$this->canViewUser($user, $target)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $contracts = Contract::with('uploadedBy:id,first_name,last_name')
            ->where('user_id', $target->id)
            ->orderBy('version', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $target->only(['id', 'first_name', 'last_name', 'employee_number']),
                'total_versions' => $contracts->count(),
                'contracts' => $contracts,
            ]
        ], 200);
    }

    /**
     * DELETE /api/v1/contract/delete/{id}
     * Soft delete via status — hard delete only for drafts.
     */
    public function destroy($id)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $contract = Contract::findOrFail($id);

        if (!in_array($contract->status, ['draft', 'pending_signature'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft or pending-signature contracts can be deleted. Use status update to terminate active contracts.',
            ], 422);
        }

        AuditService::log(
            action: 'CONTRACT_DELETED',
            tableName: 'contracts',
            recordId: $contract->id,
            oldValues: $contract->toArray(),
            logType: 'warning'
        );

        // Delete file
        if ($contract->file_path && Storage::disk('public')->exists($contract->file_path)) {
            Storage::disk('public')->delete($contract->file_path);
        }

        $contract->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Contract deleted'
        ], 200);
    }

    // ========================================
    // HELPERS
    // ========================================

    protected function canView(User $actor, Contract $contract): bool
    {
        if ($actor->id === $contract->user_id) return true;
        if (in_array($actor->role, ['admin', 'super_admin', 'director'])) return true;
        if ($actor->role === 'manager' && $contract->user?->manager_id === $actor->id) return true;
        return false;
    }

    protected function canViewUser(User $actor, User $target): bool
    {
        if ($actor->id === $target->id) return true;
        if (in_array($actor->role, ['admin', 'super_admin', 'director'])) return true;
        if ($actor->role === 'manager' && $target->manager_id === $actor->id) return true;
        return false;
    }
}