<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PRTimesheet extends Model
{
    protected $table = 'pr_timesheets';

    protected $fillable = [
        'user_id',
        'month_year',
        'status',
        'level1_approver_id',
        'level2_approver_id',
        'level1_approved_at',
        'level2_approved_at',
        'level1_rejection_reason',
        'level2_rejection_reason',
        'total_regular_hours',
        'total_overtime_hours',
        'pdf_path',
        'pdf_hash',
        'exported_to_payroll',
        'exported_at',
        'is_escalated',
        'escalated_at',
    ];

    protected $casts = [
        'month_year' => 'date',
        'level1_approved_at' => 'datetime',
        'level2_approved_at' => 'datetime',
        'exported_at' => 'datetime',
        'escalated_at' => 'datetime',
        'exported_to_payroll' => 'boolean',
        'is_escalated' => 'boolean',
        'total_regular_hours' => 'decimal:2',
        'total_overtime_hours' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function level1Approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'level1_approver_id');
    }

    public function level2Approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'level2_approver_id');
    }

    public function details(): MorphMany
    {
        return $this->morphMany(TimesheetDetail::class, 'timesheet_parent');
    }

    // Scopes
    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePendingL1($query)
    {
        return $query->where('status', 'pending_l1');
    }

    public function scopePendingL2($query)
    {
        return $query->where('status', 'pending_l2');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // Helper methods
    public function canSubmit(): bool
    {
        return in_array($this->status, ['draft', 'rejected']);
    }

    public function canRecall(): bool
    {
        return in_array($this->status, ['pending_l1', 'pending_l2']);
    }

    public function canApproveL1(): bool
    {
        return $this->status === 'pending_l1';
    }

    public function canApproveL2(): bool
    {
        return $this->status === 'pending_l2';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getTotalHoursAttribute(): float
    {
        return $this->details()->sum('hours_worked');
    }

    public function getOvertimeHoursAttribute(): float
    {
        return $this->details()->where('is_overtime', true)->sum('hours_worked');
    }
}