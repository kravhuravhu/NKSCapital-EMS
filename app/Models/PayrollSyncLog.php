<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollSyncLog extends Model
{
    protected $fillable = [
        'month_year', 'records_count', 'total_amount', 'status',
        'external_reference', 'payload', 'response_payload',
        'error_message', 'initiated_by', 'completed_at',
    ];

    protected $casts = [
        'month_year' => 'date',
        'total_amount' => 'decimal:2',
        'records_count' => 'integer',
        'payload' => 'array',
        'response_payload' => 'array',
        'completed_at' => 'datetime',
    ];

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}