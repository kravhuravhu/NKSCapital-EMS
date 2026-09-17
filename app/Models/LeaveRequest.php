<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class LeaveRequest extends Model
{
    protected $fillable = [
        'user_id',
        'leave_type',
        'start_date',
        'end_date',
        'days_taken',
        'reason',
        'attachment_path',
        'attachment_hash',
        'status',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'partial_approved_days',
        'proof_uploaded_at',
        'proof_upload_deadline',
        'proof_reminder_count',
        'last_proof_reminder_at',
        'converted_to_unpaid_at',
        'original_leave_type',
        'requires_proof',
        'proof_approved_by',
        'proof_approved_at',
        'service_months_at_application',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days_taken' => 'decimal:2',
        'partial_approved_days' => 'decimal:2',
        'approved_at' => 'datetime',
        'proof_uploaded_at' => 'datetime',
        'proof_upload_deadline' => 'datetime',
        'last_proof_reminder_at' => 'datetime',
        'converted_to_unpaid_at' => 'datetime',
        'proof_approved_at' => 'datetime',
        'requires_proof' => 'boolean',
        'proof_reminder_count' => 'integer',
        'service_months_at_application' => 'decimal:2',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function proofApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proof_approved_by');
    }

    public function calendarEntries(): HasMany
    {
        return $this->hasMany(LeaveCalendar::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->whereIn('status', ['approved', 'auto_approved']);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            'pending', 'approved', 'auto_approved',
            'partially_approved', 'approved_pending_proof'
        ])->where('end_date', '>=', now()->startOfDay());
    }

    public function scopePendingProof($query)
    {
        return $query->where('status', 'approved_pending_proof');
    }

    // Helpers

    public function canCancel(): bool
    {
        if (!in_array($this->status, [
            'approved', 'auto_approved', 'partially_approved',
            'pending', 'approved_pending_proof'
        ])) {
            return false;
        }
        // Can only cancel 2+ days before start
        return $this->start_date->diffInDays(now()) >= 2;
    }

    public function isLateCancellation(): bool
    {
        return $this->start_date->diffInDays(now()) < 2;
    }

    public function isPendingProof(): bool
    {
        return $this->status === 'approved_pending_proof';
    }

    public function hasProof(): bool
    {
        return !empty($this->proof_uploaded_at) || !empty($this->attachment_path);
    }

    public function isOverdueForProof(): bool
    {
        return $this->isPendingProof()
            && $this->proof_upload_deadline
            && now()->greaterThan($this->proof_upload_deadline);
    }

    /**
     * Calculate the deadline based on return-to-work date.
     * Deadline = end_date + proof_upload_deadline_days (working days).
     */
    public function calculateProofDeadline(int $deadlineDays): Carbon
    {
        $date = $this->end_date->copy();
        $added = 0;
        while ($added < $deadlineDays) {
            $date->addDay();
            if (!$date->isWeekend()) {
                $added++;
            }
        }
        $date->setTime(17, 0, 0); // 5 PM
        return $date;
    }
}