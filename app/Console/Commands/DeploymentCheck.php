<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeploymentCheck extends Command
{
    protected $signature = 'deploy:check';
    protected $description = 'Run production readiness checklist';

    protected array $results = [];

    public function handle(): int
    {
        $this->info('========================================');
        $this->info('NKS Capital EMS — Deployment Check');
        $this->info('========================================');

        $this->checkEnv();
        $this->checkDebugMode();
        $this->checkAppKey();
        $this->checkDatabaseConnection();
        $this->checkStorage();
        $this->checkQueue();
        $this->checkMigrations();
        $this->checkCriticalTables();
        $this->checkRolePermissions();
        $this->checkAuditChain();
        $this->checkDiskSpace();

        $this->newLine();
        $this->info('========================================');
        $failed = collect($this->results)->where('passed', false)->count();
        $total = count($this->results);

        if ($failed === 0) {
            $this->info("✓ All {$total} checks passed.");
            return self::SUCCESS;
        }

        $this->error("✗ {$failed} of {$total} checks failed.");
        return self::FAILURE;
    }

    protected function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = compact('name', 'passed', 'detail');
        $icon = $passed ? '✓' : '✗';
        $line = "{$icon} {$name}";
        if ($detail) $line .= " — {$detail}";
        $passed ? $this->info($line) : $this->error($line);
    }

    protected function checkEnv(): void
    {
        $env = config('app.env');
        $this->record('App environment is production', $env === 'production', "Current: {$env}");
    }

    protected function checkDebugMode(): void
    {
        $debug = config('app.debug');
        $this->record('Debug mode is OFF', $debug === false, 'Debug: ' . ($debug ? 'ON' : 'OFF'));
    }

    protected function checkAppKey(): void
    {
        $key = config('app.key');
        $this->record('APP_KEY is set', !empty($key), 'Key length: ' . strlen($key ?? ''));
    }

    protected function checkDatabaseConnection(): void
    {
        try {
            DB::select('SELECT 1');
            $this->record('Database connection', true, config('database.default'));
        } catch (\Throwable $e) {
            $this->record('Database connection', false, $e->getMessage());
        }
    }

    protected function checkStorage(): void
    {
        try {
            $test = 'deploy-check-' . time() . '.txt';
            Storage::disk('public')->put($test, 'ok');
            $ok = Storage::disk('public')->exists($test);
            Storage::disk('public')->delete($test);
            $this->record('Storage writable', $ok);
        } catch (\Throwable $e) {
            $this->record('Storage writable', false, $e->getMessage());
        }
    }

    protected function checkQueue(): void
    {
        try {
            $driver = config('queue.default');
            $this->record('Queue driver configured', !empty($driver), $driver);
        } catch (\Throwable $e) {
            $this->record('Queue driver configured', false, $e->getMessage());
        }
    }

    protected function checkMigrations(): void
    {
        try {
            $count = DB::table('migrations')->count();
            $this->record('Migrations applied', $count > 0, "{$count} migrations");
        } catch (\Throwable $e) {
            $this->record('Migrations applied', false, $e->getMessage());
        }
    }

    protected function checkCriticalTables(): void
    {
        $tables = [
            'users', 'audit_logs', 'notifications', 'leave_requests',
            'pr_timesheets', 'ps_timesheets', 'assets', 'contracts',
            'requisitions', 'candidates', 'interviews', 'offers',
            'approval_delegations', 'meetings', 'meeting_attendance',
            'webhooks', 'webhook_logs', 'calendar_connections',
        ];

        $missing = [];
        foreach ($tables as $table) {
            if (!DB::getSchemaBuilder()->hasTable($table)) {
                $missing[] = $table;
            }
        }

        $this->record(
            'All critical tables exist',
            empty($missing),
            empty($missing) ? 'All present' : 'Missing: ' . implode(', ', $missing)
        );
    }

    protected function checkRolePermissions(): void
    {
        try {
            $roles = DB::table('roles')->count();
            $perms = DB::table('permissions')->count();
            $this->record('Roles & permissions seeded', $roles > 0 && $perms > 0, "{$roles} roles, {$perms} permissions");
        } catch (\Throwable $e) {
            $this->record('Roles & permissions seeded', false, $e->getMessage());
        }
    }

    protected function checkAuditChain(): void
    {
        try {
            $result = \App\Services\AuditService::verifyChain();
            $this->record('Audit chain integrity', $result['is_valid'], "Total logs: {$result['total_logs']}");
        } catch (\Throwable $e) {
            $this->record('Audit chain integrity', false, $e->getMessage());
        }
    }

    protected function checkDiskSpace(): void
    {
        try {
            $free = disk_free_space(storage_path());
            $total = disk_total_space(storage_path());
            $usedPercent = round((($total - $free) / $total) * 100, 2);
            $this->record('Disk space', $usedPercent < 90, "{$usedPercent}% used");
        } catch (\Throwable $e) {
            $this->record('Disk space', false, $e->getMessage());
        }
    }
}