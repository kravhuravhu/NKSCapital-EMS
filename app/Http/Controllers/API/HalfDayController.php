<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\HalfDayNotice;
use App\Models\Notification;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class HalfDayController extends Controller
{
    public function request(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'notice_date' => 'required|date|after_or_equal:today',
            'leave_time' => 'required|date_format:H:i',
            'reason' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $notice = HalfDayNotice::create([
            'user_id' => $user->id,
            'notice_date' => $request->notice_date,
            'leave_time' => $request->leave_time . ':00',
            'reason' => $request->reason,
            'status' => 'pending',
            'manager_id' => $user->manager_id,
        ]);

        if ($user->manager_id) {
            Notification::create([
                'user_id' => $user->manager_id,
                'type' => 'HALF_DAY_REQUESTED',
                'title' => 'Half-day request',
                'message' => "{$user->full_name} has requested to leave at {$request->leave_time} on {$request->notice_date}.",
                'reference_id' => $notice->id,
                'reference_type' => HalfDayNotice::class,
                'category' => 'leave',
            ]);
        }

        AuditService::log(
            action: 'HALF_DAY_REQUESTED',
            tableName: 'half_day_notices',
            recordId: $notice->id,
            newValues: $notice->toArray(),
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Half-day notice sent to your manager.',
            'data' => $notice,
        ], 201);
    }

    public function mine(Request $request)
    {
        $user = Auth::user();

        $notices = HalfDayNotice::where('user_id', $user->id)
            ->orderByDesc('notice_date')
            ->limit(30)
            ->get();

        return response()->json(['status' => 'success', 'data' => $notices], 200);
    }

    public function pending()
    {
        $user = Auth::user();
        if (! in_array($user->role, ['manager', 'director', 'admin', 'super_admin'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $notices = HalfDayNotice::with('user:id,first_name,last_name,employee_number,department')
            ->where('manager_id', $user->id)
            ->where('status', 'pending')
            ->orderBy('notice_date')
            ->get();

        return response()->json(['status' => 'success', 'data' => $notices], 200);
    }

    public function respond(Request $request, $id)
    {
        $user = Auth::user();
        if (! in_array($user->role, ['manager', 'director', 'admin', 'super_admin'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:acknowledged,declined',
            'response' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
        }

        $notice = HalfDayNotice::findOrFail($id);

        if ($notice->manager_id !== $user->id && ! in_array($user->role, ['admin', 'super_admin'], true)) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $notice->status = $request->status;
        $notice->manager_response = $request->response;
        $notice->responded_at = now();
        $notice->save();

        Notification::create([
            'user_id' => $notice->user_id,
            'type' => 'HALF_DAY_' . strtoupper($request->status),
            'title' => 'Half-day ' . $request->status,
            'message' => $request->status === 'acknowledged'
                ? "Your manager has acknowledged your half-day notice for {$notice->notice_date->format('d M Y')}."
                : "Your manager declined your half-day notice. Reason: " . ($request->response ?? 'No reason provided'),
            'reference_id' => $notice->id,
            'reference_type' => HalfDayNotice::class,
            'category' => 'leave',
        ]);

        AuditService::log(
            action: 'HALF_DAY_' . strtoupper($request->status),
            tableName: 'half_day_notices',
            recordId: $notice->id,
            newValues: ['status' => $request->status, 'response' => $request->response],
            logType: 'success'
        );

        return response()->json(['status' => 'success', 'data' => $notice->fresh()], 200);
    }
}