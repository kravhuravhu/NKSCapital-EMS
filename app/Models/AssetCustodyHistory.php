<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetCustodyHistory extends Model
{
    protected $table = 'asset_custody_history';

    protected $fillable = [
        'asset_id',
        'assigned_to_id',
        'checkout_date',
        'expected_return_date',
        'actual_return_date',
        'checkout_condition',
        'return_condition',
        'damage_photo',
        'damage_photo_hash',
        'repair_priority',
        'notes',
        'returned_to_location',
        'created_by',
    ];

    protected $casts = [
        'checkout_date' => 'date',
        'expected_return_date' => 'date',
        'actual_return_date' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return is_null($this->actual_return_date);
    }

    public function isOverdue(): bool
    {
        return $this->isActive()
            && $this->expected_return_date
            && $this->expected_return_date->isPast();
    }
}