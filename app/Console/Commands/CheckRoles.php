<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;

class CheckRoles extends Command
{
    protected $signature = 'check:roles {--fix : Repair mismatches by setting the column to the highest-priority Spatie role}';
    protected $description = 'Detect and optionally repair mismatches between users.role and Spatie roles';

    protected const PRIORITY = [
        'super_admin' => 70,
        'admin' => 60,
        'director' => 50,
        'operational_manager' => 45,
        'manager' => 40,
        'recruiter' => 30,
        'employee' => 10,
    ];

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $mismatches = 0;
        $repaired = 0;
        $orphans = 0;

        $this->info('Scanning ' . User::count() . ' users...');

        foreach (User::query()->cursor() as $user) {
            $spatie = $user->getRoleNames()->toArray();
            $column = $user->role;

            if (empty($spatie) && empty($column)) {
                continue;
            }

            if (!empty($spatie) && in_array($column, $spatie, true)) {
                continue;
            }

            $mismatches++;

            if (empty($spatie)) {
                $orphans++;
                $this->warn(sprintf(
                    'ORPHAN user #%d %s (column=%s, spatie=none)',
                    $user->id,
                    $user->email,
                    $column ?? 'null'
                ));
                continue;
            }

            $highest = $this->highestPriority($spatie);
            $this->line(sprintf(
                'MISMATCH user #%d %s (column=%s, spatie=%s, fix->%s)',
                $user->id,
                $user->email,
                $column ?? 'null',
                implode('|', $spatie),
                $highest
            ));

            if ($fix) {
                $user->role = $highest;
                $user->save();

                AuditService::log(
                    action: 'ROLE_MISMATCH_REPAIRED',
                    tableName: 'users',
                    recordId: $user->id,
                    oldValues: ['role' => $column, 'spatie' => $spatie],
                    newValues: ['role' => $highest],
                    logType: 'warning',
                    severity: 'warning'
                );

                $repaired++;
            }
        }

        $this->newLine();
        $this->info("Mismatches: {$mismatches}  Orphans: {$orphans}  Repaired: {$repaired}");

        if (!$fix && $mismatches > 0) {
            $this->warn('Run again with --fix to repair.');
        }

        return self::SUCCESS;
    }

    protected function highestPriority(array $roles): string
    {
        $best = 'employee';
        $bestScore = 0;

        foreach ($roles as $role) {
            $score = self::PRIORITY[$role] ?? 0;
            if ($score > $bestScore) {
                $best = $role;
                $bestScore = $score;
            }
        }

        return $best;
    }
}