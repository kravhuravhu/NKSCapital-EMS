<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Asset extends Model
{
    protected $fillable = [
        'asset_tag',
        'serial_number',
        'model',
        'manufacturer',
        'purchase_date',
        'purchase_price',
        'warranty_expiry',
        'condition',
        'status',
        'current_assignee_id',
        'location',
        'notes',
        'overdue_notification_level',
        'last_overdue_notified_at',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_expiry' => 'date',
        'purchase_price' => 'decimal:2',
        'overdue_notification_level' => 'integer',
        'last_overdue_notified_at' => 'datetime',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function currentAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'current_assignee_id');
    }

    public function custodyHistory(): HasMany
    {
        return $this->hasMany(AssetCustodyHistory::class)->orderBy('checkout_date', 'desc');
    }

    public function activeLoan(): HasMany
    {
        return $this->hasMany(AssetCustodyHistory::class)
            ->whereNull('actual_return_date')
            ->orderBy('checkout_date', 'desc');
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeAvailable($query)
    {
        return $query->where('status', 'available');
    }

    public function scopeLoaned($query)
    {
        return $query->where('status', 'loaned');
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'loaned')
            ->whereHas('activeLoan', function ($q) {
                $q->whereDate('expected_return_date', '<', now());
            });
    }

    public function scopeInMaintenance($query)
    {
        return $query->whereIn('status', ['maintenance', 'repair_requested']);
    }

    // ========================================
    // HELPERS
    // ========================================

    public function isLoaned(): bool
    {
        return $this->status === 'loaned';
    }

    public function isAvailable(): bool
    {
        return $this->status === 'available';
    }

    public function isOverdue(): bool
    {
        if (!$this->isLoaned()) {
            return false;
        }
        $loan = $this->activeLoan()->first();
        return $loan && $loan->expected_return_date && $loan->expected_return_date->isPast();
    }

    /**
     * Days since expected return. Negative if not yet due.
     */
    public function daysOverdue(): int
    {
        $loan = $this->activeLoan()->first();
        if (!$loan || !$loan->expected_return_date) {
            return 0;
        }
        return (int) $loan->expected_return_date->diffInDays(now(), false);
    }

    public function needsOverdueEscalation(): bool
    {
        $days = $this->daysOverdue();
        if ($days >= 14 && $this->overdue_notification_level < 14) return true;
        if ($days >= 7  && $this->overdue_notification_level < 7)  return true;
        if ($days >= 3  && $this->overdue_notification_level < 3)  return true;
        return false;
    }
}