<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveConfig extends Model
{
    protected $fillable = [
        'leave_type',
        'default_entitlement',
        'accrual_rate',
        'accrual_frequency',
        'max_carryover',
        'requires_attachment',
        'min_days_attachment',
        'auto_approve',
        'min_service_months',
        'description',
        'updated_by',
    ];

    protected $casts = [
        'default_entitlement' => 'decimal:2',
        'accrual_rate' => 'decimal:4',
        'max_carryover' => 'decimal:2',
        'requires_attachment' => 'boolean',
        'auto_approve' => 'boolean',
        'min_service_months' => 'integer',
        'min_days_attachment' => 'integer',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}