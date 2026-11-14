<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoginOtp extends Model
{
    protected $fillable = [
        'user_id',
        'challenge_token',
        'code_hash',
        'attempts',
        'max_attempts',
        'purpose',
        'expires_at',
        'consumed_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'consumed_at' => 'datetime',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
    ];

    protected $hidden = ['code_hash'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isExhausted(): bool
    {
        return $this->attempts >= $this->max_attempts;
    }
}