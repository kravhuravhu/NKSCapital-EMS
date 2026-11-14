<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'previous_hash',
        'payload_hash',
        'chain_hash',
        'timestamp',
        'user_id',
        'ip_address',
        'action',
        'log_type',
        'http_status',
        'table_name',
        'record_id',
        'old_values',
        'new_values',
        'user_agent',
        'request_method',
        'request_path',
        'request_id',
        'duration_ms',
        'error_message',
        'error_trace',
        'response_status',
        'response_size',
        'memory_peak_kb',
        'is_slow',
        'severity',
        'environment',
        'session_id',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'timestamp' => 'datetime',
        'http_status' => 'integer',
        'duration_ms' => 'integer',
        'response_status' => 'integer',
        'response_size' => 'integer',
        'memory_peak_kb' => 'integer',
        'is_slow' => 'boolean',
    ];

    /**
     * Get the user that owns the audit log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * prevent updates and deletions of audit logs, ensuring immutability.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \Exception('Audit logs are immutable and cannot be updated.');
        });

        static::deleting(function () {
            throw new \Exception('Audit logs are immutable and cannot be deleted.');
        });
    }

    /**
     * Filter the query to only include error logs.
     */
    public function scopeErrors($query)
    {
        return $query->where('log_type', 'error');
    }

    /**
     * Filter the query to only include success logs.
     */
    public function scopeSuccess($query)
    {
        return $query->where('log_type', 'success');
    }

    /**
     * Filter the query to only include logs for a specific user.
     */
    public function scopeForRequest($query, string $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    /**
     * Filter the query to only include logs for a specific user.
     */
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    /**
     * Filter the query to only include warning logs.
     */
    public function scopeWarnings($query)
    {
        return $query->where('severity', 'warning');
    }

    /**
     * Filter the query to only include info logs.
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Filter the query to only include slow logs.
     */
    public function scopeSlow($query)
    {
        return $query->where('is_slow', true);
    }

    /**
     * Only include logs between two dates.
     */
    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('timestamp', [$from, $to]);
    }

    /**
     * Include logs for a specific action.
     */
    public function scopeActionLike($query, string $term)
    {
        return $query->where('action', 'like', "%{$term}%");
    }
}