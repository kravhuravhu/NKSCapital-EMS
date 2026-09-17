<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveConfig extends Model
{
    protected $fillable = [
        'leave_type',
        'approval_type',
        'auto_approve_max_days',
        'partial_approve_threshold',
        'requires_proof_after',
        'proof_upload_deadline_days',
        'notification_frequency_hours',
        'notification_start_hour',
        'notification_end_hour',
        'convert_to_unpaid_after_deadline',
        'fallback_leave_type',
        'is_accruable',
        'is_carryover_allowed',
        'is_infinite_balance',
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
        'min_service_months' => 'decimal:1',
        'requires_attachment' => 'boolean',
        'auto_approve' => 'boolean',
        'convert_to_unpaid_after_deadline' => 'boolean',
        'is_accruable' => 'boolean',
        'is_carryover_allowed' => 'boolean',
        'is_infinite_balance' => 'boolean',
        'min_days_attachment' => 'integer',
        'auto_approve_max_days' => 'integer',
        'partial_approve_threshold' => 'integer',
        'requires_proof_after' => 'integer',
        'proof_upload_deadline_days' => 'integer',
        'notification_frequency_hours' => 'integer',
        'notification_start_hour' => 'integer',
        'notification_end_hour' => 'integer',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Check if this leave type is auto-approvable for the given day count.
     */
    public function isAutoApprovable(float $days): bool
    {
        if ($this->approval_type === 'auto') {
            return true;
        }
        if ($this->approval_type === 'hybrid' && $this->auto_approve_max_days > 0) {
            return $days <= $this->auto_approve_max_days;
        }
        return false;
    }

    /**
     * Check if this leave type requires proof after the given day count.
     */
    public function requiresProofFor(float $days): bool
    {
        return $this->requires_proof_after > 0 && $days > $this->requires_proof_after;
    }

    /**
     * Check if this leave type is infinite (unpaid).
     */
    public function isInfinite(): bool
    {
        return (bool) $this->is_infinite_balance;
    }
}