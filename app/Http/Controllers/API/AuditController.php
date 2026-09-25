<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class AuditController extends Controller
{
    /**
     * POST /api/v1/admin/audit/search
     */
    public function search(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        $validator = Validator::make($request->all(), [
            'user_id' => 'nullable|exists:users,id',
            'action' => 'nullable|string|max:255',
            'table_name' => 'nullable|string|max:100',
            'record_id' => 'nullable|integer',
            'log_type' => 'nullable|in:info,success,warning,error',
            'severity' => 'nullable|in:info,success,warning,error,critical',
            'http_status' => 'nullable|integer',
            'request_id' => 'nullable|string|max:100',
            'ip_address' => 'nullable|string|max:45',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'is_slow' => 'nullable|boolean',
            'search' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = AuditLog::with('user:id,first_name,last_name,email');

        if ($request->filled('user_id'))      $query->where('user_id', $request->user_id);
        if ($request->filled('action'))       $query->where('action', 'like', "%{$request->action}%");
        if ($request->filled('table_name'))   $query->where('table_name', $request->table_name);
        if ($request->filled('record_id'))    $query->where('record_id', $request->record_id);
        if ($request->filled('log_type'))     $query->where('log_type', $request->log_type);
        if ($request->filled('severity'))     $query->where('severity', $request->severity);
        if ($request->filled('http_status'))  $query->where('http_status', $request->http_status);
        if ($request->filled('request_id'))   $query->where('request_id', $request->request_id);
        if ($request->filled('ip_address'))   $query->where('ip_address', $request->ip_address);
        if ($request->boolean('is_slow'))     $query->where('is_slow', true);
        if ($request->filled('from'))         $query->where('timestamp', '>=', $request->from);
        if ($request->filled('to'))           $query->where('timestamp', '<=', $request->to);

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('action', 'like', "%{$s}%")
                  ->orWhere('error_message', 'like', "%{$s}%")
                  ->orWhere('request_path', 'like', "%{$s}%")
                  ->orWhere('new_values', 'like', "%{$s}%");
            });
        }

        $items = $query->orderByDesc('timestamp')->paginate($request->per_page ?? 50);

        AuditService::log(
            action: 'AUDIT_SEARCH_PERFORMED',
            tableName: 'audit_logs',
            recordId: 0,
            newValues: ['filters' => $request->except('page')],
            logType: 'success'
        );

        return response()->json(['status' => 'success', 'data' => $items], 200);
    }

    /**
     * GET /api/v1/admin/audit/export
     */
    public function export(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        $validator = Validator::make($request->all(), [
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'format' => 'nullable|in:json,csv',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $from = Carbon::parse($request->from)->startOfDay();
        $to = Carbon::parse($request->to)->endOfDay();
        $format = $request->format ?? 'json';

        $logs = AuditLog::with('user:id,first_name,last_name,email')
            ->whereBetween('timestamp', [$from, $to])
            ->orderBy('timestamp')
            ->get();

        $export = $logs->map(function ($log) {
            return [
                'id' => $log->id,
                'timestamp' => $log->timestamp->toIso8601String(),
                'action' => $log->action,
                'log_type' => $log->log_type,
                'severity' => $log->severity,
                'user_id' => $log->user_id,
                'user_name' => $log->user?->full_name,
                'ip_address' => $log->ip_address,
                'table_name' => $log->table_name,
                'record_id' => $log->record_id,
                'http_status' => $log->http_status,
                'duration_ms' => $log->duration_ms,
                'request_id' => $log->request_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'error_message' => $log->error_message,
            ];
        });

        AuditService::log(
            action: 'AUDIT_EXPORTED',
            tableName: 'audit_logs',
            recordId: 0,
            newValues: [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'record_count' => $logs->count(),
                'format' => $format,
            ],
            logType: 'success'
        );

        if ($format === 'csv') {
            $csv = "id,timestamp,action,log_type,severity,user_id,user_name,ip_address,table_name,record_id,http_status,duration_ms,request_id,error_message\n";
            foreach ($export as $row) {
                $csv .= sprintf(
                    "%d,%s,\"%s\",%s,%s,%s,\"%s\",%s,%s,%d,%s,%s,%s,\"%s\"\n",
                    $row['id'],
                    $row['timestamp'],
                    str_replace('"', '""', $row['action']),
                    $row['log_type'],
                    $row['severity'],
                    $row['user_id'] ?? '',
                    str_replace('"', '""', $row['user_name'] ?? ''),
                    $row['ip_address'] ?? '',
                    $row['table_name'],
                    $row['record_id'],
                    $row['http_status'] ?? '',
                    $row['duration_ms'] ?? '',
                    $row['request_id'] ?? '',
                    str_replace('"', '""', $row['error_message'] ?? '')
                );
            }

            $filename = 'audit-export-' . $from->format('Ymd') . '-' . $to->format('Ymd') . '.csv';
            $path = 'audit-exports/' . $filename;
            Storage::disk('public')->put($path, $csv);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'download_url' => Storage::url($path),
                    'filename' => $filename,
                    'record_count' => $logs->count(),
                ]
            ], 200);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
                'record_count' => $logs->count(),
                'logs' => $export,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/admin/audit/verify-chain
     */
    public function verifyChain()
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        $result = AuditService::verifyChain();

        AuditService::log(
            action: 'AUDIT_CHAIN_VERIFIED',
            tableName: 'audit_logs',
            recordId: 0,
            newValues: $result,
            logType: $result['is_valid'] ? 'success' : 'error',
            severity: $result['is_valid'] ? 'success' : 'critical'
        );

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ], 200);
    }

    /**
     * GET /api/v1/admin/audit/report
     */
    public function report(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        $validator = Validator::make($request->all(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $from = $request->filled('from') ? Carbon::parse($request->from)->startOfDay() : now()->subDays(30)->startOfDay();
        $to = $request->filled('to') ? Carbon::parse($request->to)->endOfDay() : now()->endOfDay();

        $base = AuditLog::whereBetween('timestamp', [$from, $to]);

        $report = [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'totals' => [
                'total_events' => (clone $base)->count(),
                'success' => (clone $base)->where('log_type', 'success')->count(),
                'warnings' => (clone $base)->where('log_type', 'warning')->count(),
                'errors' => (clone $base)->where('log_type', 'error')->count(),
                'info' => (clone $base)->where('log_type', 'info')->count(),
                'slow_requests' => (clone $base)->where('is_slow', true)->count(),
            ],
            'by_severity' => (clone $base)->selectRaw('severity, COUNT(*) as count')->groupBy('severity')->pluck('count', 'severity'),
            'by_http_status' => (clone $base)->selectRaw('http_status, COUNT(*) as count')
                ->whereNotNull('http_status')
                ->groupBy('http_status')
                ->pluck('count', 'http_status'),
            'top_actions' => (clone $base)->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(15)
                ->pluck('count', 'action'),
            'top_users' => (clone $base)->selectRaw('user_id, COUNT(*) as count')
                ->whereNotNull('user_id')
                ->groupBy('user_id')
                ->orderByDesc('count')
                ->limit(15)
                ->with('user:id,first_name,last_name')
                ->get()
                ->map(fn ($r) => [
                    'user_id' => $r->user_id,
                    'name' => $r->user?->full_name ?? 'Unknown',
                    'count' => $r->count,
                ]),
            'critical_errors' => (clone $base)->where('severity', 'critical')
                ->orderByDesc('timestamp')
                ->limit(20)
                ->get([
                    'id', 'timestamp', 'action', 'user_id', 'request_path',
                    'http_status', 'error_message',
                ]),
            'slowest_requests' => (clone $base)->whereNotNull('duration_ms')
                ->orderByDesc('duration_ms')
                ->limit(10)
                ->get(['id', 'timestamp', 'action', 'duration_ms', 'http_status']),
        ];

        return response()->json(['status' => 'success', 'data' => $report], 200);
    }

    /**
     * GET /api/v1/admin/audit/user/{userId}
     */
    public function userAudit($userId, Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        $target = User::findOrFail($userId);

        $validator = Validator::make($request->all(), [
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'log_type' => 'nullable|in:info,success,warning,error',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = AuditLog::where('user_id', $target->id);

        if ($request->filled('from'))     $query->where('timestamp', '>=', $request->from);
        if ($request->filled('to'))       $query->where('timestamp', '<=', $request->to);
        if ($request->filled('log_type')) $query->where('log_type', $request->log_type);

        $logs = $query->orderByDesc('timestamp')->paginate($request->per_page ?? 50);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => $target->only(['id', 'first_name', 'last_name', 'email', 'employee_number', 'role']),
                'total_events' => $logs->total(),
                'logs' => $logs,
            ]
        ], 200);
    }

    /**
     * GET /api/v1/admin/audit/table/{tableName}
     */
    public function tableAudit($tableName, Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        $validator = Validator::make($request->all(), [
            'record_id' => 'nullable|integer',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'log_type' => 'nullable|in:info,success,warning,error',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $query = AuditLog::with('user:id,first_name,last_name,email')
            ->where('table_name', $tableName);

        if ($request->filled('record_id')) $query->where('record_id', $request->record_id);
        if ($request->filled('from'))      $query->where('timestamp', '>=', $request->from);
        if ($request->filled('to'))        $query->where('timestamp', '<=', $request->to);
        if ($request->filled('log_type'))  $query->where('log_type', $request->log_type);

        $logs = $query->orderByDesc('timestamp')->paginate($request->per_page ?? 50);

        return response()->json([
            'status' => 'success',
            'data' => [
                'table_name' => $tableName,
                'total_events' => $logs->total(),
                'logs' => $logs,
            ]
        ], 200);
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function requireRole(array $roles): User
    {
        $user = Auth::user();
        if (!in_array($user->role, $roles)) {
            AuditService::logWarning('AUDIT_ACCESS_DENIED', 'audit_logs', 0, [
                'actor_id' => $user->id,
                'required' => $roles,
                'actual' => $user->role,
            ]);
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }
        return $user;
    }
}