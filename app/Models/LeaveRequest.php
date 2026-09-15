<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'days_taken' => 'decimal:2',
        'partial_approved_days' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
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
        return $query->whereIn('status', ['pending', 'approved', 'auto_approved', 'partially_approved'])
                     ->where('end_date', '>=', now()->startOfDay());
    }

    // Helpers
    public function canCancel(): bool
    {
        if (!in_array($this->status, ['approved', 'auto_approved', 'partially_approved', 'pending'])) {
            return false;
        }
        // Can only cancel 2+ days before start
        return $this->start_date->diffInDays(now()) >= 2;
    }

    public function isLateCancellation(): bool
    {
        return $this->start_date->diffInDays(now()) < 2;
    }
}