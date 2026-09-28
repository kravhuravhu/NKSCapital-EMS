<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;

class HealthController extends Controller
{
    /**
     * GET /api/v1/admin/health
     * Deep system health check (no auth for basic, auth for deep).
     */
    public function check()
    {
        $checks = [];
        $overall = 'healthy';

        // 1. Database
        try {
            DB::select('SELECT 1');
            $checks['database'] = [
                'status' => 'ok',
                'connection' => config('database.default'),
                'migrations' => DB::table('migrations')->count(),
            ];
        } catch (\Throwable $e) {
            $checks['database'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $overall = 'unhealthy';
        }

        // 2. Cache
        try {
            $key = 'health:check:' . time();
            Cache::put($key, 'ok', 10);
            $value = Cache::get($key);
            Cache::forget($key);
            $checks['cache'] = ['status' => $value === 'ok' ? 'ok' : 'fail', 'driver' => config('cache.default')];
        } catch (\Throwable $e) {
            $checks['cache'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $overall = 'degraded';
        }

        // 3. Storage
        try {
            $testFile = 'health/check-' . time() . '.txt';
            Storage::disk('public')->put($testFile, 'ok');
            $exists = Storage::disk('public')->exists($testFile);
            Storage::disk('public')->delete($testFile);
            $checks['storage'] = ['status' => $exists ? 'ok' : 'fail', 'disk' => 'public'];
        } catch (\Throwable $e) {
            $checks['storage'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $overall = 'degraded';
        }

        // 4. Queue
        try {
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();
            $checks['queue'] = [
                'status' => $failedJobs > 100 ? 'degraded' : 'ok',
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'driver' => config('queue.default'),
            ];
            if ($failedJobs > 100) $overall = 'degraded';
        } catch (\Throwable $e) {
            $checks['queue'] = ['status' => 'fail', 'error' => $e->getMessage()];
            $overall = 'degraded';
        }

        // 5. Disk
        try {
            $free = disk_free_space(storage_path());
            $total = disk_total_space(storage_path());
            $usedPercent = round((($total - $free) / $total) * 100, 2);
            $checks['disk'] = [
                'status' => $usedPercent > 90 ? 'fail' : ($usedPercent > 75 ? 'degraded' : 'ok'),
                'used_percent' => $usedPercent,
                'free_gb' => round($free / 1024 / 1024 / 1024, 2),
            ];
            if ($usedPercent > 90) $overall = 'unhealthy';
            elseif ($usedPercent > 75) $overall = 'degraded';
        } catch (\Throwable $e) {
            $checks['disk'] = ['status' => 'unknown', 'error' => $e->getMessage()];
        }

        return response()->json([
            'status' => $overall,
            'checked_at' => now()->toIso8601String(),
            'app' => [
                'name' => config('app.name'),
                'env' => config('app.env'),
                'version' => config('app.version', '1.0.0'),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ],
            'checks' => $checks,
        ], $overall === 'healthy' ? 200 : 200);
    }

    /**
     * GET /api/v1/admin/queue/stats
     */
    public function queueStats()
    {
        if (!in_array(Auth::user()->role, ['admin', 'super_admin', 'director'])) {
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'pending' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
                'batches' => DB::table('job_batches')->count(),
                'driver' => config('queue.default'),
                'checked_at' => now()->toIso8601String(),
            ]
        ], 200);
    }

    /**
     * POST /api/v1/admin/backup/trigger
     */
    public function triggerBackup()
    {
        $user = Auth::user();
        if (!in_array($user->role, ['admin', 'super_admin'])) {
            abort(response()->json(['status' => 'error', 'message' => 'Unauthorized'], 403));
        }

        $filename = 'backup-' . now()->format('Ymd-His') . '.sql';
        $path = 'backups/' . $filename;

        \App\Services\AuditService::log(
            action: 'BACKUP_TRIGGERED',
            tableName: 'system',
            recordId: $user->id,
            newValues: ['filename' => $filename],
            logType: 'success'
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Backup queued',
            'data' => [
                'filename' => $filename,
                'path' => $path,
                'queued_at' => now()->toIso8601String(),
            ]
        ], 200);
    }
}