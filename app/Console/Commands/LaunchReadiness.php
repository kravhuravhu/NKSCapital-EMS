<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class LaunchReadiness extends Command
{
    protected $signature = 'launch:check {--email-to= : Send a test email to this address}';
    protected $description = 'Verify the system is ready for launch';

    protected array $results = [];

    public function handle(): int
    {
        $this->info('Launch readiness check');
        $this->info('======================');

        $this->check2faCoverage();
        $this->checkCompanyEmailPending();
        $this->checkMailer();
        $this->checkStorage();
        $this->checkAuditChain();
        $this->checkQueue();
        $this->checkSmtpSend();
        $this->checkPasswordReset();
        $this->checkCriticalTables();

        $failed = collect($this->results)->where('passed', false)->count();
        $total = count($this->results);

        $this->newLine();

        if ($failed === 0) {
            $this->info("All {$total} checks passed. Ready for launch.");
            return self::SUCCESS;
        }

        $this->error("{$failed} of {$total} checks failed. Resolve before launch.");
        return self::FAILURE;
    }

    protected function record(string $name, bool $passed, string $detail = ''): void
    {
        $this->results[] = compact('name', 'passed', 'detail');
        $icon = $passed ? '[OK]' : '[FAIL]';
        $line = "{$icon} {$name}";
        if ($detail !== '') {
            $line .= " - {$detail}";
        }
        $passed ? $this->info($line) : $this->error($line);
    }

    protected function check2faCoverage(): void
    {
        $total = User::where('is_active', true)->count();
        $enrolled = User::where('is_active', true)
            ->where(function ($q) {
                $q->where('two_factor_enabled', true)
                  ->orWhereHas('passkeys');
            })
            ->count();

        $this->record(
            'All active users enrolled in 2FA or passkeys',
            $enrolled === $total,
            "{$enrolled} of {$total} enrolled"
        );
    }

    protected function checkCompanyEmailPending(): void
    {
        $pending = User::where('pending_company_email', true)->where('is_active', true)->count();

        $this->record(
            'No active employees awaiting company email',
            $pending === 0,
            $pending === 0 ? 'All clear' : "{$pending} pending"
        );
    }

    protected function checkMailer(): void
    {
        $default = config('mail.default');

        $this->record(
            'Mail driver is smtp',
            $default === 'smtp',
            "Current: {$default}"
        );
    }

    protected function checkStorage(): void
    {
        try {
            $test = 'launch-check-' . time() . '.txt';
            Storage::disk('public')->put($test, 'ok');
            $exists = Storage::disk('public')->exists($test);
            Storage::disk('public')->delete($test);
            $this->record('Public storage writable', $exists);
        } catch (\Throwable $e) {
            $this->record('Public storage writable', false, $e->getMessage());
        }
    }

    protected function checkAuditChain(): void
    {
        try {
            $result = AuditService::verifyChain();
            $this->record(
                'Audit chain integrity',
                $result['is_valid'],
                "Total logs: {$result['total_logs']}" . ($result['broken_at'] ? " broken at {$result['broken_at']}" : '')
            );
        } catch (\Throwable $e) {
            $this->record('Audit chain integrity', false, $e->getMessage());
        }
    }

    protected function checkQueue(): void
    {
        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            $this->record(
                'Queue is healthy',
                $failed < 100,
                "Pending: {$pending}, Failed: {$failed}"
            );
        } catch (\Throwable $e) {
            $this->record('Queue is healthy', false, $e->getMessage());
        }
    }

    protected function checkSmtpSend(): void
    {
        $email = $this->option('email-to');

        if (! $email) {
            $this->record('SMTP test email', true, 'Skipped (pass --email-to to enable)');
            return;
        }

        try {
            Mail::raw(
                "Launch readiness SMTP test.\n\nSent at " . now()->toIso8601String() . "\nFrom " . config('app.url'),
                function ($mail) use ($email) {
                    $mail->to($email)->subject('NKS EMS: launch readiness test');
                }
            );

            $this->record('SMTP test email', true, "Sent to {$email}");
        } catch (\Throwable $e) {
            $this->record('SMTP test email', false, $e->getMessage());
        }
    }

    protected function checkPasswordReset(): void
    {
        try {
            $token = DB::table('password_reset_tokens')->count();
            $this->record('Password reset tokens table reachable', true, "{$token} rows");
        } catch (\Throwable $e) {
            $this->record('Password reset tokens table reachable', false, $e->getMessage());
        }
    }

    protected function checkCriticalTables(): void
    {
        $tables = [
            'users', 'login_otps', 'qr_login_challenges', 'personal_access_tokens',
            'audit_logs', 'audit_chain_locks', 'employee_onboarding_items',
            'project_change_requests', 'notifications',
        ];

        $missing = [];

        foreach ($tables as $table) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                $missing[] = $table;
            }
        }

        $this->record(
            'All critical tables exist',
            empty($missing),
            empty($missing) ? 'All present' : 'Missing: ' . implode(', ', $missing)
        );
    }
}