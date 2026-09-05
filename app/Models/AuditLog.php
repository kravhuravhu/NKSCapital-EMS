<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    protected $fillable = [
        'user_id',
        'ip_address',
        'action',
        'table_name',
        'record_id',
        'old_values',
        'new_values',
        'user_agent',
        'timestamp',
        'previous_hash',
        'payload_hash',
        'chain_hash',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'timestamp' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}