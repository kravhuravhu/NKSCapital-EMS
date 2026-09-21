<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApprovalDelegation extends Model
{
    protected $fillable = [
        'original_approver_id',
        'delegate_id',
        'delegation_type',
        'title',
        'delegation_modules',
        'start_date',
        'end_date',
        'reason',
        'created_by',
        'is_active',
        'activated_at',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
        'auto_activate',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'auto_activate' => 'boolean',
        'activated_at' => 'datetime',
        'revoked_at' => 'datetime',
        'delegation_modules' => 'array',
    ];

    public function originalApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'original_approver_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now());
    }

    public function scopeUpcoming($query)
    {
        return $query->where('is_active', true)
            ->whereDate('start_date', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->whereDate('end_date', '<', now());
    }

    // Helpers
    public function isCurrentlyActive(): bool
    {
        return $this->is_active
            && $this->start_date->isPast()
            && $this->end_date->isFuture();
    }

    public function coversModule(string $module): bool
    {
        if ($this->delegation_type === 'both') return true;
        $modules = $this->delegation_modules ?? [];
        if (in_array('all', $modules, true)) return true;
        return in_array($module, $modules, true);
    }
}