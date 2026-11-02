<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class QueueController extends Controller
{
    /**
     * GET /api/v1/admin/queue/pending
     * Paginated list of jobs waiting to be processed.
     */
    public function pending(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        $perPage = min((int) ($request->per_page ?? 25), 100);
        $page = max((int) ($request->page ?? 1), 1);
        $total = DB::table('jobs')->count();

        $rows = DB::table('jobs')
            ->orderByDesc('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($row) {
                $payload = json_decode($row->payload, true) ?: [];
                return [
                    'id' => $row->id,
                    'queue' => $row->queue,
                    'job_class' => $payload['displayName'] ?? 'Unknown',
                    'attempts' => (int) $row->attempts,
                    'available_at' => $row->available_at ? date('Y-m-d H:i:s', $row->available_at) : null,
                    'created_at' => $row->created_at ? date('Y-m-d H:i:s', $row->created_at) : null,
                    'is_delayed' => $row->available_at > time(),
                    'is_reserved' => !is_null($row->reserved_at),
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'data' => $rows,
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ], 200);
    }

    /**
     * GET /api/v1/admin/queue/failed
     * Paginated list of failed jobs.
     */
    public function failed(Request $request)
    {
        $this->requireRole(['admin', 'super_admin', 'director']);

        $perPage = min((int) ($request->per_page ?? 25), 100);
        $page = max((int) ($request->page ?? 1), 1);
        $total = DB::table('failed_jobs')->count();

        $rows = DB::table('failed_jobs')
            ->orderByDesc('id')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get()
            ->map(function ($row) {
                $payload = json_decode($row->payload, true) ?: [];
                $exception = (string) ($row->exception ?? '');
                $firstLine = strtok($exception, "\n") ?: '';

                return [
                    'id' => $row->id,
                    'uuid' => $row->uuid,
                    'connection' => $row->connection,
                    'queue' => $row->queue,
                    'job_class' => $payload['displayName'] ?? 'Unknown',
                    'failed_at' => $row->failed_at,
                    'exception_summary' => mb_substr($firstLine, 0, 300),
                    'exception_full' => $exception,
                ];
            });

        return response()->json([
            'status' => 'success',
            'data' => [
                'data' => $rows,
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
        ], 200);
    }

    /**
     * POST /api/v1/admin/queue/failed/retry/{id}
     * Retry a single failed job.
     */
    public function retryFailed(Request $request, $id)
    {
        $this->requireRole(['admin', 'super_admin']);

        $job = DB::table('failed_jobs')->where('id', $id)->first();

        if (!$job) {
            return response()->json(['status' => 'error', 'message' => 'Job not found'], 404);
        }

        try {
            Artisan::call('queue:retry', ['id' => [$id]]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retry: ' . $e->getMessage(),
            ], 422);
        }

        AuditService::log(
            action: 'QUEUE_JOB_RETRIED',
            tableName: 'failed_jobs',
            recordId: (int) $id,
            newValues: ['uuid' => $job->uuid, 'queue' => $job->queue],
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Job pushed back onto the queue',
        ], 200);
    }

    /**
     * POST /api/v1/admin/queue/failed/forget/{id}
     * Permanently delete a single failed job.
     */
    public function forgetFailed(Request $request, $id)
    {
        $this->requireRole(['admin', 'super_admin']);

        $job = DB::table('failed_jobs')->where('id', $id)->first();

        if (!$job) {
            return response()->json(['status' => 'error', 'message' => 'Job not found'], 404);
        }

        DB::table('failed_jobs')->where('id', $id)->delete();

        AuditService::log(
            action: 'QUEUE_JOB_FORGOTTEN',
            tableName: 'failed_jobs',
            recordId: (int) $id,
            oldValues: ['uuid' => $job->uuid, 'queue' => $job->queue],
            logType: 'warning'
        );

        return response()->json(['status' => 'success', 'message' => 'Failed job removed'], 200);
    }

    /**
     * POST /api/v1/admin/queue/failed/retry-all
     * Push every failed job back onto the queue. Password required.
     */
    public function retryAllFailed(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin']);
        $this->verifyPassword($request, $user);

        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            return response()->json(['status' => 'error', 'message' => 'No failed jobs to retry'], 422);
        }

        try {
            Artisan::call('queue:retry', ['id' => ['all']]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retry all: ' . $e->getMessage(),
            ], 422);
        }

        AuditService::log(
            action: 'QUEUE_ALL_RETRIED',
            tableName: 'failed_jobs',
            recordId: 0,
            newValues: ['count' => $count],
            logType: 'warning'
        );

        return response()->json([
            'status' => 'success',
            'message' => "{$count} job(s) retried",
            'data' => ['count' => $count],
        ], 200);
    }

    /**
     * POST /api/v1/admin/queue/failed/flush
     * Delete every failed job. Password required.
     */
    public function flushFailed(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin']);
        $this->verifyPassword($request, $user);

        $count = DB::table('failed_jobs')->count();

        if ($count === 0) {
            return response()->json(['status' => 'error', 'message' => 'No failed jobs to flush'], 422);
        }

        DB::table('failed_jobs')->truncate();

        AuditService::log(
            action: 'QUEUE_FAILED_FLUSHED',
            tableName: 'failed_jobs',
            recordId: 0,
            oldValues: ['count' => $count],
            logType: 'critical'
        );

        return response()->json([
            'status' => 'success',
            'message' => "{$count} failed job(s) removed",
            'data' => ['count' => $count],
        ], 200);
    }

    /**
     * POST /api/v1/admin/queue/pending/flush
     * Clear every pending job. Password required.
     */
    public function flushPending(Request $request)
    {
        $user = $this->requireRole(['admin', 'super_admin']);
        $this->verifyPassword($request, $user);

        $count = DB::table('jobs')->count();

        if ($count === 0) {
            return response()->json(['status' => 'error', 'message' => 'No pending jobs to clear'], 422);
        }

        DB::table('jobs')->truncate();

        AuditService::log(
            action: 'QUEUE_PENDING_FLUSHED',
            tableName: 'jobs',
            recordId: 0,
            oldValues: ['count' => $count],
            logType: 'critical'
        );

        return response()->json([
            'status' => 'success',
            'message' => "{$count} pending job(s) removed",
            'data' => ['count' => $count],
        ], 200);
    }

    protected function requireRole(array $roles): User
    {
        $user = Auth::user();

        if (!in_array($user->role, $roles, true)) {
            AuditService::logWarning('QUEUE_ACCESS_DENIED', 'queue', 0, [
                'actor_id' => $user->id,
                'required' => $roles,
                'actual' => $user->role,
            ]);
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }

        return $user;
    }

    protected function verifyPassword(Request $request, User $user): void
    {
        if (!Hash::check((string) $request->input('password'), $user->password)) {
            abort(response()->json(['status' => 'error', 'message' => 'Password is incorrect'], 422));
        }
    }
}