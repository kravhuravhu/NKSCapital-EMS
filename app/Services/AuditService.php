<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Log an audit event with hash chaining
     */
    public static function log(
        string $action,
        string $tableName,
        int $recordId,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?int $userId = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): AuditLog {
        // Get the last audit log to chain
        $lastLog = AuditLog::orderBy('id', 'desc')->first();
        $previousHash = $lastLog ? $lastLog->chain_hash : '0';

        // Prepare payload
        $userId = $userId ?? (Auth::id() ?? null);
        $ipAddress = $ipAddress ?? Request::ip();
        $userAgent = $userAgent ?? Request::userAgent();
        $timestamp = now();

        // Build payload for hashing
        $payload = json_encode([
            'previous_hash' => $previousHash,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_agent' => $userAgent,
            'timestamp' => $timestamp->toIso8601String(),
        ]);

        // Generate hashes
        $payloadHash = hash('sha256', $payload);
        $chainHash = hash('sha256', $previousHash . $payloadHash);

        // Create audit log
        return AuditLog::create([
            'previous_hash' => $previousHash,
            'payload_hash' => $payloadHash,
            'chain_hash' => $chainHash,
            'timestamp' => $timestamp,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'action' => $action,
            'table_name' => $tableName,
            'record_id' => $recordId,
            'old_values' => $oldValues ? json_encode($oldValues) : null,
            'new_values' => $newValues ? json_encode($newValues) : null,
            'user_agent' => $userAgent,
        ]);
    }

    /**
     * Verify the integrity of the audit chain
     */
    public static function verifyChain(): array
    {
        $logs = AuditLog::orderBy('id', 'asc')->get();
        $previousHash = '0';
        $isValid = true;
        $brokenAt = null;

        foreach ($logs as $log) {
            // Rebuild payload
            $payload = json_encode([
                'previous_hash' => $log->previous_hash,
                'user_id' => $log->user_id,
                'ip_address' => $log->ip_address,
                'action' => $log->action,
                'table_name' => $log->table_name,
                'record_id' => $log->record_id,
                'old_values' => $log->old_values ? json_decode($log->old_values, true) : null,
                'new_values' => $log->new_values ? json_decode($log->new_values, true) : null,
                'user_agent' => $log->user_agent,
                'timestamp' => $log->timestamp->toIso8601String(),
            ]);

            $expectedPayloadHash = hash('sha256', $payload);
            $expectedChainHash = hash('sha256', $log->previous_hash . $expectedPayloadHash);

            if ($expectedChainHash !== $log->chain_hash || $log->previous_hash !== $previousHash) {
                $isValid = false;
                $brokenAt = $log->id;
                break;
            }

            $previousHash = $log->chain_hash;
        }

        return [
            'is_valid' => $isValid,
            'total_logs' => $logs->count(),
            'broken_at' => $brokenAt,
        ];
    }
}