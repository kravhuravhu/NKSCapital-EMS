<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveBalance extends Model
{
    protected $fillable = [
        'user_id',
        'leave_type',
        'balance_before',
        'balance_after',
        'adjustment_reason',
        'reference_id',
        'reference_type',
        'adjusted_by',
        'adjusted_at',
    ];

    protected $casts = [
        'balance_before' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'adjusted_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adjustedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adjusted_by');
    }
}