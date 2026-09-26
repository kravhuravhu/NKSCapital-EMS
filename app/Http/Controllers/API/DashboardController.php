<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    protected DashboardService $dashboard;

    public function __construct(DashboardService $dashboard)
    {
        $this->dashboard = $dashboard;
    }

    /**
     * GET /api/v1/dashboard/employee/ps
     */
    public function employeePS(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['employee', 'manager', 'director', 'admin', 'super_admin']);
        $this->requireEmployeeType($user, 'ps');

        return response()->json([
            'status' => 'success',
            'data' => $this->dashboard->employeePSDashboard($user),
        ], 200);
    }

    /**
     * GET /api/v1/dashboard/employee/pr
     */
    public function employeePR(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['employee', 'manager', 'director', 'admin', 'super_admin']);
        $this->requireEmployeeType($user, 'pr');

        return response()->json([
            'status' => 'success',
            'data' => $this->dashboard->employeePRDashboard($user),
        ], 200);
    }

    /**
     * GET /api/v1/dashboard/manager
     */
    public function manager(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['manager', 'director', 'admin', 'super_admin']);

        return response()->json([
            'status' => 'success',
            'data' => $this->dashboard->managerDashboard($user),
        ], 200);
    }

    /**
     * GET /api/v1/dashboard/director
     */
    public function director(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['director', 'admin', 'super_admin']);

        return response()->json([
            'status' => 'success',
            'data' => $this->dashboard->directorDashboard($user),
        ], 200);
    }

    /**
     * GET /api/v1/dashboard/admin
     */
    public function admin(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['admin', 'super_admin']);

        return response()->json([
            'status' => 'success',
            'data' => $this->dashboard->adminDashboard($user),
        ], 200);
    }

    /**
     * GET /api/v1/dashboard/recruiter
     */
    public function recruiter(Request $request)
    {
        $user = Auth::user();
        $this->requireRole($user, ['recruiter', 'manager', 'director', 'admin', 'super_admin']);

        return response()->json([
            'status' => 'success',
            'data' => $this->dashboard->recruiterDashboard($user),
        ], 200);
    }

    /**
     * GET /api/v1/dashboard/websocket/token
     */
    public function websocketToken(Request $request)
    {
        $user = Auth::user();

        $token = Str::random(64);

        \Illuminate\Support\Facades\Cache::put(
            "ws_token:{$token}",
            ['user_id' => $user->id, 'created_at' => now()],
            now()->addMinutes(15)
        );

        AuditService::log(
            action: 'DASHBOARD_WEBSOCKET_TOKEN_ISSUED',
            tableName: 'users',
            recordId: $user->id,
            newValues: ['expires_in_minutes' => 15],
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'token' => $token,
                'channel' => "private-user-{$user->id}",
                'expires_in_seconds' => 900,
                'ws_url' => config('app.websocket_url', 'wss://myportal.nkscapital.co.za/ws'),
            ]
        ], 200);
    }

    /**
     * GET /api/v1/dashboard/recent-activity
     */
    public function recentActivity(Request $request)
    {
        $user = Auth::user();
        $limit = min((int) $request->query('limit', 20), 100);

        return response()->json([
            'status' => 'success',
            'data' => [
                'activity' => $this->dashboard->recentActivity($user, $limit),
            ]
        ], 200);
    }

    /**
     * GET /api/v1/dashboard/pending-counts
     */
    public function pendingCounts(Request $request)
    {
        $user = Auth::user();

        return response()->json([
            'status' => 'success',
            'data' => $this->dashboard->pendingCounts($user),
        ], 200);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function requireRole($user, array $roles): void
    {
        if (!in_array($user->role, $roles)) {
            AuditService::logWarning('DASHBOARD_ACCESS_DENIED', 'users', $user->id, [
                'required' => $roles,
                'actual' => $user->role,
                'dashboard' => request()->path(),
            ]);
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }
    }

    protected function requireEmployeeType($user, string $type): void
    {
        if ($user->employee_type !== $type
            && !in_array($user->role, ['admin', 'super_admin', 'director', 'manager'])) {
            abort(response()->json([
                'status' => 'error',
                'message' => "Dashboard is only available for {$type} employees"
            ], 403));
        }
    }
}