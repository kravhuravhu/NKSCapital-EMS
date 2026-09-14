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
        'table_name',
        'record_id',
        'old_values',
        'new_values',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'timestamp' => 'datetime',
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
}