<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\LeaveReason;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LeaveReasonsController extends Controller
{
    public function forType(string $leaveType)
    {
        return response()->json([
            'status' => 'success',
            'data' => LeaveReason::activeFor($leaveType),
        ], 200);
    }

    public function index()
    {
        $user = Auth::user();
        if (! in_array($user->role, ['admin', 'super_admin', 'director'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $reasons = LeaveReason::orderBy('leave_type')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return response()->json(['status' => 'success', 'data' => $reasons], 200);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        if (! in_array($user->role, ['admin', 'super_admin', 'director'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'leave_type' => 'required|in:annual,sick,family,unpaid,study,maternity,paternity',
            'label' => 'required|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_other' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $reason = LeaveReason::create([
            'leave_type' => $request->leave_type,
            'label' => $request->label,
            'sort_order' => $request->sort_order ?? 100,
            'is_active' => $request->boolean('is_active', true),
            'is_other' => $request->boolean('is_other', false),
            'created_by' => $user->id,
        ]);

        AuditService::log(
            action: 'LEAVE_REASON_CREATED',
            tableName: 'leave_reasons',
            recordId: $reason->id,
            newValues: $reason->toArray(),
            logType: 'success'
        );

        return response()->json(['status' => 'success', 'data' => $reason], 201);
    }

    public function update(Request $request, $id)
    {
        $user = Auth::user();
        if (! in_array($user->role, ['admin', 'super_admin', 'director'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $reason = LeaveReason::findOrFail($id);
        $old = $reason->toArray();

        $validator = Validator::make($request->all(), [
            'label' => 'sometimes|string|max:255',
            'sort_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'is_other' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $reason->fill($request->only(['label', 'sort_order', 'is_active', 'is_other']));
        $reason->save();

        AuditService::log(
            action: 'LEAVE_REASON_UPDATED',
            tableName: 'leave_reasons',
            recordId: $reason->id,
            oldValues: $old,
            newValues: $reason->fresh()->toArray(),
            logType: 'success'
        );

        return response()->json(['status' => 'success', 'data' => $reason->fresh()], 200);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        if (! in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $reason = LeaveReason::findOrFail($id);
        $snapshot = $reason->toArray();
        $reason->delete();

        AuditService::log(
            action: 'LEAVE_REASON_DELETED',
            tableName: 'leave_reasons',
            recordId: $snapshot['id'] ?? 0,
            oldValues: $snapshot,
            logType: 'warning'
        );

        return response()->json(['status' => 'success', 'message' => 'Deleted'], 200);
    }
}