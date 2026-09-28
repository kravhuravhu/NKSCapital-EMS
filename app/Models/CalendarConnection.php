<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarConnection extends Model
{
    protected $fillable = [
        'user_id', 'provider', 'access_token', 'refresh_token',
        'expires_at', 'calendar_id', 'last_synced_at',
        'sync_direction', 'is_active',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}