<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class RoleSmokeTest extends Command
{
    protected $signature = 'smoke:roles {--base-url= : Base URL, defaults to APP_URL}';
    protected $description = 'Hit key endpoints for each role to confirm the API and authorization paths work';

    public function handle(): int
    {
        $baseUrl = $this->option('base-url') ?: config('app.url');

        $this->info("Smoke testing against {$baseUrl}");

        $scenarios = [
            'employee' => [
                ['GET', '/api/v1/auth/permissions', 200],
                ['GET', '/api/v1/leave/balance', 200],
                ['GET', '/api/v1/notifications/list', 200],
                ['GET', '/api/v1/dashboard/employee/pr', 200],
                ['GET', '/api/v1/admin/audit/search', 403],
            ],
            'manager' => [
                ['GET', '/api/v1/leave/pending', 200],
                ['GET', '/api/v1/timesheet/pr/pending/l1', 200],
                ['GET', '/api/v1/manager/nonexistent', 404],
                ['GET', '/api/v1/admin/health', 403],
            ],
            'director' => [
                ['GET', '/api/v1/admin/health', 200],
                ['GET', '/api/v1/admin/audit/verify-chain', 200],
                ['GET', '/api/v1/reports/director/company-kpi', 200],
            ],
            'operational_manager' => [
                ['GET', '/api/v1/admin/health', 200],
                ['GET', '/api/v1/reports/director/company-kpi', 200],
            ],
            'admin' => [
                ['GET', '/api/v1/admin/health', 200],
                ['GET', '/api/v1/admin/queue/stats', 200],
                ['GET', '/api/v1/admin/system-config', 200],
            ],
            'recruiter' => [
                ['GET', '/api/v1/recruitment/requisition/list', 200],
                ['GET', '/api/v1/recruitment/candidate/list', 200],
                ['GET', '/api/v1/recruitment/interview/list', 200],
            ],
        ];

        $passed = 0;
        $failed = 0;

        foreach ($scenarios as $role => $requests) {
            $user = User::where('role', $role)->where('is_active', true)->first();

            if (! $user) {
                $this->warn("No active user with role '{$role}', skipping");
                continue;
            }

            $token = $user->createToken('smoke-test')->plainTextToken;

            $this->info("Role: {$role} ({$user->email})");

            foreach ($requests as [$method, $path, $expected]) {
                try {
                    $response = Http::withToken($token)
                        ->timeout(10)
                        ->acceptJson()
                        ->send($method, $baseUrl . $path);

                    $status = $response->status();
                    $ok = $status === $expected;

                    if ($ok) {
                        $this->line("  [OK] {$method} {$path} -> {$status}");
                        $passed++;
                    } else {
                        $this->error("  [FAIL] {$method} {$path} -> {$status} (expected {$expected})");
                        $failed++;
                    }
                } catch (\Throwable $e) {
                    $this->error("  [ERR] {$method} {$path} -> {$e->getMessage()}");
                    $failed++;
                }
            }

            $user->tokens()->where('name', 'smoke-test')->delete();
        }

        $this->newLine();
        $this->info("Passed: {$passed}  Failed: {$failed}");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}