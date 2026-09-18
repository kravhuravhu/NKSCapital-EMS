<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Offer extends Model
{
    protected $fillable = [
        'candidate_id',
        'offer_letter_path',
        'offer_letter_hash',
        'salary_offered',
        'position',
        'department',
        'contract_type',
        'manager_id',
        'notice_period_days',
        'benefits',
        'start_date',
        'expiry_date',
        'status',
        'accepted_at',
        'declined_at',
        'declined_reason',
        'withdrawn_at',
        'withdrawn_by',
        'withdrawn_reason',
        'expiry_alerts_sent',
        'sent_at',
        'created_by',
    ];

    protected $casts = [
        'salary_offered' => 'decimal:2',
        'benefits' => 'array',
        'start_date' => 'date',
        'expiry_date' => 'date',
        'accepted_at' => 'datetime',
        'declined_at' => 'datetime',
        'withdrawn_at' => 'datetime',
        'sent_at' => 'datetime',
        'expiry_alerts_sent' => 'array',
        'notice_period_days' => 'integer',
    ];

    // Relationships
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'extended');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'extended');
    }

    public function scopeExpiringSoon($query, int $days = 3)
    {
        return $query->where('status', 'extended')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    // Helpers
    public function isPending(): bool
    {
        return $this->status === 'extended';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function canAccept(): bool
    {
        return $this->status === 'extended'
            && (!$this->expiry_date || $this->expiry_date->isFuture());
    }

    public function canDecline(): bool
    {
        return $this->status === 'extended';
    }

    public function canWithdraw(): bool
    {
        return in_array($this->status, ['extended']);
    }

    public function isExpired(): bool
    {
        return $this->status === 'extended'
            && $this->expiry_date
            && $this->expiry_date->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->expiry_date) return null;
        return (int) now()->diffInDays($this->expiry_date, false);
    }

    public function hasAlertBeenSent(string $level): bool
    {
        $sent = $this->expiry_alerts_sent ?? [];
        return !empty($sent[$level]);
    }

    public function markAlertSent(string $level): void
    {
        $sent = $this->expiry_alerts_sent ?? [];
        $sent[$level] = now()->toIso8601String();
        $this->expiry_alerts_sent = $sent;
        $this->save();
    }
}