<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\UatSession;
use App\Models\User;
use Illuminate\Support\Facades\Http;

class RunUatSuite extends Command
{
    protected $signature = 'uat:run {--tester_id= : User ID running the UAT}';
    protected $description = 'Run the User Acceptance Test suite against a running API';

    public function handle(): int
    {
        $testerId = $this->option('tester_id');
        $tester = $testerId ? User::find($testerId) : User::where('role', 'admin')->first();

        if (!$tester) {
            $this->error('No tester found.');
            return self::FAILURE;
        }

        $baseUrl = config('app.url') . '/api/v1';
        $results = [];
        $passed = 0;
        $failed = 0;

        $session = UatSession::create([
            'title' => 'UAT Suite — ' . now()->format('Y-m-d H:i'),
            'tester_id' => $tester->id,
            'started_at' => now(),
            'status' => 'in_progress',
        ]);

        $scenarios = $this->scenarios();

        foreach ($scenarios as $scenario) {
            $this->info("▶ {$scenario['name']}");

            try {
                $response = Http::timeout(15)
                    ->withHeaders($scenario['headers'] ?? [])
                    ->send($scenario['method'], $baseUrl . $scenario['endpoint'], $scenario['body'] ?? []);

                $expectedStatus = $scenario['expected_status'] ?? 200;
                $actualStatus = $response->status();
                $ok = $actualStatus === $expectedStatus;

                if ($ok) {
                    $passed++;
                    $this->line("  ✓ {$actualStatus}");
                } else {
                    $failed++;
                    $this->error("  ✗ expected {$expectedStatus}, got {$actualStatus}");
                }

                $results[] = [
                    'name' => $scenario['name'],
                    'endpoint' => $scenario['endpoint'],
                    'expected_status' => $expectedStatus,
                    'actual_status' => $actualStatus,
                    'passed' => $ok,
                ];
            } catch (\Throwable $e) {
                $failed++;
                $this->error("  ✗ Exception: {$e->getMessage()}");
                $results[] = [
                    'name' => $scenario['name'],
                    'endpoint' => $scenario['endpoint'],
                    'passed' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $session->results = $results;
        $session->status = $failed === 0 ? 'passed' : 'failed';
        $session->completed_at = now();
        $session->notes = "Passed: {$passed}, Failed: {$failed}";
        $session->save();

        $this->info("UAT completed: {$passed} passed, {$failed} failed.");

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    protected function scenarios(): array
    {
        return [
            [
                'name' => 'Health check',
                'method' => 'GET',
                'endpoint' => '/admin/health',
                'expected_status' => 200,
            ],
            [
                'name' => 'Login page (via API login)',
                'method' => 'POST',
                'endpoint' => '/auth/login',
                'body' => ['email' => 'admin@nkscapital.co.za', 'password' => 'password'],
                'expected_status' => 200,
            ],
            [
                'name' => 'Unauthorized without token',
                'method' => 'GET',
                'endpoint' => '/notifications/list',
                'expected_status' => 401,
            ],
            [
                'name' => 'Role escalation blocked (employee tries admin audit)',
                'method' => 'POST',
                'endpoint' => '/admin/audit/search',
                'expected_status' => 401,
            ],
        ];
    }
}