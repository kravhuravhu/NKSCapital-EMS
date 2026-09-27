<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Models\PayrollSyncLog;
use App\Services\WebhookService;
use App\Services\CalendarService;
use App\Services\PayrollIntegrationService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;

class IntegrationController extends Controller
{
    protected WebhookService $webhooks;
    protected CalendarService $calendar;
    protected PayrollIntegrationService $payroll;

    public function __construct(
        WebhookService $webhooks,
        CalendarService $calendar,
        PayrollIntegrationService $payroll
    ) {
        $this->webhooks = $webhooks;
        $this->calendar = $calendar;
        $this->payroll = $payroll;
    }

    // ============================================================
    // WEBHOOKS
    // ============================================================

    /**
     * GET /api/v1/integrations/webhook/list
     */
    public function webhookList(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        $validator = Validator::make($request->all(), [
            'is_active' => 'nullable|boolean',
            'event' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = Webhook::with('createdBy:id,first_name,last_name')->withCount('logs');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $items = $query->orderByDesc('created_at')->paginate($request->per_page ?? 20);

        return response()->json(['status' => 'success', 'data' => $items], 200);
    }

    /**
     * POST /api/v1/integrations/webhook/register
     */
    public function webhookRegister(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'director']);

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'target_url' => 'required|url|max:500',
            'secret' => 'nullable|string|max:255',
            'events' => 'required|array|min:1',
            'events.*' => 'string|max:100',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $webhook = $this->webhooks->register($request->all(), $user->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Webhook registered',
            'data' => $webhook,
        ], 201);
    }

    /**
     * GET /api/v1/integrations/webhook/logs
     */
    public function webhookLogs(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        $validator = Validator::make($request->all(), [
            'webhook_id' => 'nullable|exists:webhooks,id',
            'event' => 'nullable|string|max:100',
            'status' => 'nullable|in:success,failed,retrying,dead_letter',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = WebhookLog::with('webhook:id,name,target_url');

        if ($request->filled('webhook_id')) $query->where('webhook_id', $request->webhook_id);
        if ($request->filled('event')) $query->where('event', $request->event);
        if ($request->filled('status')) $query->where('status', $request->status);
        if ($request->filled('from')) $query->where('created_at', '>=', $request->from);
        if ($request->filled('to')) $query->where('created_at', '<=', $request->to);

        $items = $query->orderByDesc('created_at')->paginate($request->per_page ?? 50);

        return response()->json(['status' => 'success', 'data' => $items], 200);
    }

    /**
     * POST /api/v1/integrations/webhook/test/{id}
     */
    public function webhookTest($id)
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        $webhook = Webhook::findOrFail($id);
        $log = $this->webhooks->test($webhook);

        return response()->json([
            'status' => 'success',
            'message' => $log->status === 'success' ? 'Test delivered' : 'Test failed',
            'data' => $log,
        ], 200);
    }

    /**
     * POST /api/v1/integrations/webhook/retry/{id}
     */
    public function webhookRetry($id)
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        $log = WebhookLog::findOrFail($id);

        try {
            $newLog = $this->webhooks->retry($log);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Retry attempted',
            'data' => $newLog,
        ], 200);
    }

    /**
     * DELETE /api/v1/integrations/webhook/delete/{id}
     */
    public function webhookDelete($id)
    {
        $user = $this->requireRole(['admin', 'super_admin']);

        $webhook = Webhook::findOrFail($id);
        $this->webhooks->delete($webhook, $user->id);

        return response()->json(['status' => 'success', 'message' => 'Webhook deleted'], 200);
    }

    // ============================================================
    // CALENDAR
    // ============================================================

    /**
     * GET /api/v1/integrations/calendar/connect
     */
    public function calendarConnect(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'provider' => 'required|in:google,outlook,ical',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $result = $this->calendar->getConnectUrl($user, $request->provider);

        return response()->json(['status' => 'success', 'data' => $result], 200);
    }

    /**
     * POST /api/v1/integrations/calendar/sync
     */
    public function calendarSync(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'direction' => 'nullable|in:push,pull,both',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $result = $this->calendar->sync($user, $request->direction ?? 'both');
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => 'success', 'data' => $result], 200);
    }

    // ============================================================
    // PAYROLL
    // ============================================================

    /**
     * GET /api/v1/integrations/payroll/export
     */
    public function payrollExport(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin', 'director']);

        $validator = Validator::make($request->all(), [
            'month_year' => 'nullable|date_format:Y-m-d',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $log = $this->payroll->export($user, $request->month_year);
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Payroll export initiated',
            'data' => $log,
        ], 200);
    }

    /**
     * POST /api/v1/integrations/payroll/webhook
     */
    public function payrollWebhook(Request $request)
    {
        // No auth required — external payroll system calls this
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:completed,failed,pending',
            'external_reference' => 'nullable|string|max:255',
            'error' => 'nullable|string|max:2000',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        try {
            $log = $this->payroll->receiveAcknowledgment($request->all());
        } catch (\RuntimeException $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Payroll acknowledgment received',
            'data' => $log,
        ], 200);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function requireRole(array $roles)
    {
        $user = Auth::user();
        if (!in_array($user->role, $roles)) {
            AuditService::logWarning('INTEGRATION_ACCESS_DENIED', 'integrations', 0, [
                'actor_id' => $user->id,
                'required' => $roles,
                'actual' => $user->role,
                'path' => request()->path(),
            ]);
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }
        return $user;
    }
}