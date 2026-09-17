<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Contract extends Model
{
    protected $fillable = [
        'user_id',
        'parent_contract_id',
        'version',
        'file_path',
        'file_hash',
        'effective_date',
        'expiry_date',
        'probation_end_date',
        'salary_annual',
        'notice_period_days',
        'position',
        'contract_type',
        'status',
        'termination_reason',
        'signed_by_employee',
        'signed_file_path',
        'expiry_alerts_sent',
        'renewed_at',
        'terminated_at',
        'uploaded_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'expiry_date' => 'date',
        'probation_end_date' => 'date',
        'salary_annual' => 'decimal:2',
        'signed_by_employee' => 'boolean',
        'expiry_alerts_sent' => 'array',
        'renewed_at' => 'datetime',
        'terminated_at' => 'datetime',
        'version' => 'integer',
        'notice_period_days' => 'integer',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function parentContract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'parent_contract_id');
    }

    public function childContracts(): HasMany
    {
        return $this->hasMany(Contract::class, 'parent_contract_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Contract::class, 'user_id', 'user_id')
            ->orderBy('version', 'desc');
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeExpiringSoon($query, int $days = 30)
    {
        return $query->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereBetween('expiry_date', [now()->toDateString(), now()->addDays($days)->toDateString()]);
    }

    public function scopeExpired($query)
    {
        return $query->where('status', 'active')
            ->whereNotNull('expiry_date')
            ->whereDate('expiry_date', '<', now());
    }

    // ========================================
    // HELPERS
    // ========================================

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isExpired(): bool
    {
        return $this->isActive()
            && $this->expiry_date
            && $this->expiry_date->isPast();
    }

    public function daysUntilExpiry(): ?int
    {
        if (!$this->expiry_date) return null;
        return (int) now()->diffInDays($this->expiry_date, false);
    }

    public function canBeRenewed(): bool
    {
        return in_array($this->status, ['active', 'expired']);
    }

    public function canBeTerminated(): bool
    {
        return in_array($this->status, ['active', 'pending_signature']);
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