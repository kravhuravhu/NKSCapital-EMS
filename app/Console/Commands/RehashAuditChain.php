<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RehashAuditChain extends Command
{
    protected $signature = 'audit:rehash {--force : Actually write the new hashes without prompting}';
    protected $description = 'Recompute payload_hash and chain_hash using the current timestamp format (microsecond precision)';

    public function handle(): int
    {
        $this->info('Scanning audit chain...');

        $total = AuditLog::count();
        $mismatch = 0;
        $rehashed = 0;

        // First pass, count mismatches without writing.
        foreach (AuditLog::orderBy('id')->cursor() as $log) {
            $payload = json_encode([
                'previous_hash' => $log->previous_hash,
                'user_id' => $log->user_id,
                'ip_address' => $log->ip_address,
                'action' => $log->action,
                'log_type' => $log->log_type,
                'severity' => $log->severity,
                'http_status' => $log->http_status,
                'table_name' => $log->table_name,
                'record_id' => $log->record_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'user_agent' => $log->user_agent,
                'timestamp' => $log->timestamp->format('Y-m-d\TH:i:s.uP'),
            ]);

            if (hash('sha256', $payload) !== $log->payload_hash) {
                $mismatch++;
            }
        }

        $this->info("Total rows: {$total}  Mismatches: {$mismatch}");

        if ($mismatch === 0) {
            $this->info('Chain is already using the current format. Nothing to do.');
            return self::SUCCESS;
        }

        if (!$this->option('force')) {
            $this->warn('Run again with --force to rewrite.');
            return self::SUCCESS;
        }

        DB::transaction(function () use (&$rehashed) {
            DB::table('audit_chain_locks')->where('id', 1)->lockForUpdate()->first();

            $previousHash = '0';

            foreach (AuditLog::orderBy('id')->cursor() as $log) {
                $payload = json_encode([
                    'previous_hash' => $previousHash,
                    'user_id' => $log->user_id,
                    'ip_address' => $log->ip_address,
                    'action' => $log->action,
                    'log_type' => $log->log_type,
                    'severity' => $log->severity,
                    'http_status' => $log->http_status,
                    'table_name' => $log->table_name,
                    'record_id' => $log->record_id,
                    'old_values' => $log->old_values,
                    'new_values' => $log->new_values,
                    'user_agent' => $log->user_agent,
                    'timestamp' => $log->timestamp->format('Y-m-d\TH:i:s.uP'),
                ]);

                $payloadHash = hash('sha256', $payload);
                $chainHash = hash('sha256', $previousHash . $payloadHash);

                if ($log->previous_hash !== $previousHash
                    || $log->payload_hash !== $payloadHash
                    || $log->chain_hash !== $chainHash) {
                    DB::table('audit_logs')->where('id', $log->id)->update([
                        'previous_hash' => $previousHash,
                        'payload_hash' => $payloadHash,
                        'chain_hash' => $chainHash,
                    ]);
                    $rehashed++;
                }

                $previousHash = $chainHash;
            }
        });

        $this->info("Rehashed {$rehashed} rows.");

        return self::SUCCESS;
    }
}