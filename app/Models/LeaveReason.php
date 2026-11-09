<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeaveReason extends Model
{
    protected $fillable = [
        'leave_type',
        'label',
        'sort_order',
        'is_active',
        'is_other',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_other' => 'boolean',
        'sort_order' => 'integer',
    ];

    public static function activeFor(string $leaveType): array
    {
        return static::where('leave_type', $leaveType)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get(['id', 'label', 'is_other'])
            ->toArray();
    }
}