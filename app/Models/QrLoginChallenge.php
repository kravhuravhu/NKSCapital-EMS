<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QrLoginChallenge extends Model
{
    protected $fillable = [
        'challenge',
        'user_id',
        'challenge_token',
        'status',
        'approved_by_id',
        'approved_at',
        'consumed_at',
        'expires_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'consumed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isConsumed(): bool
    {
        return $this->status === 'consumed';
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}