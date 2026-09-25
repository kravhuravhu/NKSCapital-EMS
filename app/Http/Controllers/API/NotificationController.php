<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\NotificationPreference;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    protected NotificationService $notifications;

    public function __construct(NotificationService $notifications)
    {
        $this->notifications = $notifications;
    }

    /**
     * GET /api/v1/notifications/list
     */
    public function list(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'category' => 'nullable|string|max:50',
            'type' => 'nullable|string|max:100',
            'unread_only' => 'nullable|boolean',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = Notification::where('user_id', $user->id)->notExpired();

        if ($request->filled('category'))  $query->where('category', $request->category);
        if ($request->filled('type'))      $query->where('type', $request->type);
        if ($request->filled('priority'))  $query->where('priority', $request->priority);
        if ($request->boolean('unread_only')) $query->unread();
        if ($request->filled('from'))      $query->where('created_at', '>=', $request->from);
        if ($request->filled('to'))        $query->where('created_at', '<=', $request->to);

        $items = $query->orderByDesc('created_at')->paginate($request->per_page ?? 20);

        return response()->json([
            'status' => 'success',
            'data' => [
                'notifications' => $items,
                'unread_count' => Notification::where('user_id', $user->id)->unread()->notExpired()->count(),
            ]
        ], 200);
    }

    /**
     * GET /api/v1/notifications/unread/count
     */
    public function unreadCount()
    {
        $user = Auth::user();

        $total = Notification::where('user_id', $user->id)->unread()->notExpired()->count();
        $byCategory = Notification::where('user_id', $user->id)
            ->unread()
            ->notExpired()
            ->selectRaw('category, COUNT(*) as count')
            ->groupBy('category')
            ->pluck('count', 'category');

        return response()->json([
            'status' => 'success',
            'data' => [
                'total' => $total,
                'by_category' => $byCategory,
            ]
        ], 200);
    }

    /**
     * POST /api/v1/notifications/mark-read
     */
    public function markRead(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'notification_id' => 'required|exists:notifications,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $notification = Notification::findOrFail($request->notification_id);

        try {
            $notification = $this->notifications->markRead($notification, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }

        return response()->json(['status' => 'success', 'data' => $notification], 200);
    }

    /**
     * POST /api/v1/notifications/mark-all-read
     */
    public function markAllRead()
    {
        $user = Auth::user();
        $count = $this->notifications->markAllRead($user);

        return response()->json([
            'status' => 'success',
            'message' => "{$count} notifications marked as read",
            'data' => ['count' => $count],
        ], 200);
    }

    /**
     * DELETE /api/v1/notifications/delete/{id}
     */
    public function delete($id)
    {
        $user = Auth::user();
        $notification = Notification::findOrFail($id);

        try {
            $this->notifications->delete($notification, $user);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 403);
        }

        return response()->json(['status' => 'success', 'message' => 'Notification deleted'], 200);
    }

    /**
     * GET /api/v1/notifications/preferences
     */
    public function preferences()
    {
        $user = Auth::user();

        $categories = [
            'timesheet', 'leave', 'asset', 'contract', 'recruitment',
            'meeting', 'payroll', 'delegation', 'general',
        ];

        $prefs = [];
        foreach ($categories as $cat) {
            $pref = NotificationPreference::resolveFor($user, $cat);
            $prefs[] = [
                'category' => $cat,
                'in_app' => $pref->in_app,
                'email' => $pref->email,
                'digest_only' => $pref->digest_only,
            ];
        }

        return response()->json(['status' => 'success', 'data' => $prefs], 200);
    }

    /**
     * PUT /api/v1/notifications/preferences/update
     */
    public function updatePreferences(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'preferences' => 'required|array',
            'preferences.*.category' => 'required|string|max:50',
            'preferences.*.in_app' => 'required|boolean',
            'preferences.*.email' => 'required|boolean',
            'preferences.*.digest_only' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $updated = [];
        foreach ($request->preferences as $p) {
            $pref = NotificationPreference::updateOrCreate(
                ['user_id' => $user->id, 'category' => $p['category']],
                [
                    'in_app' => $p['in_app'],
                    'email' => $p['email'],
                    'digest_only' => $p['digest_only'] ?? false,
                ]
            );
            $updated[] = $pref;
        }

        AuditService::log(
            action: 'NOTIFICATION_PREFERENCES_UPDATED',
            tableName: 'notification_preferences',
            recordId: $user->id,
            newValues: ['preferences' => $updated],
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Preferences updated',
            'data' => $updated,
        ], 200);
    }

    /**
     * POST /api/v1/notifications/send-email
     * Admin-only: manually trigger email notification.
     */
    public function sendEmail(Request $request)
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin', 'director'])) {
            return response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'exists:users,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
            'category' => 'nullable|string|max:50',
            'priority' => 'nullable|in:low,normal,high,urgent',
            'action_url' => 'nullable|url|max:500',
            'reference_id' => 'nullable|integer',
            'reference_type' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $sent = $this->notifications->broadcast(
            $request->user_ids,
            'ADMIN_EMAIL',
            $request->title,
            $request->message,
            $request->category ?? 'general',
            $request->priority ?? 'normal',
            $request->action_url,
            $request->reference_id,
            $request->reference_type
        );

        AuditService::log(
            action: 'NOTIFICATION_BROADCAST_SENT',
            tableName: 'notifications',
            recordId: 0,
            newValues: [
                'recipient_count' => $sent,
                'title' => $request->title,
                'category' => $request->category ?? 'general',
            ],
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'message' => "Notification sent to {$sent} recipient(s)",
            'data' => ['sent_count' => $sent],
        ], 200);
    }
}