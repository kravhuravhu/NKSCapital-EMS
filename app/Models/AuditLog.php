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

    // Disable mass assignment protection bypass for immutable logs
    protected $guarded = [];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Prevent updates to audit logs
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

    // Scopes
    public function scopeErrors($query)
    {
        return $query->where('log_type', 'error');
    }

    public function scopeSuccess($query)
    {
        return $query->where('log_type', 'success');
    }

    public function scopeForRequest($query, string $requestId)
    {
        return $query->where('request_id', $requestId);
    }

    // Notification scopes
    public function scopeCritical($query)
    {
        return $query->where('severity', 'critical');
    }

    public function scopeWarnings($query)
    {
        return $query->where('severity', 'warning');
    }

    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeSlow($query)
    {
        return $query->where('is_slow', true);
    }

    public function scopeBetweenDates($query, $from, $to)
    {
        return $query->whereBetween('timestamp', [$from, $to]);
    }

    public function scopeActionLike($query, string $term)
    {
        return $query->where('action', 'like', "%{$term}%");
    }
}