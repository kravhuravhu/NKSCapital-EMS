<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DashboardCache extends Model
{
    protected $table = 'dashboard_cache';

    protected $fillable = ['user_id', 'widget_key', 'payload', 'expires_at'];

    protected $casts = [
        'payload' => 'array',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isFresh(): bool
    {
        return $this->expires_at->isFuture();
    }

    // Static helper: get or compute a widget
    public static function remember(User $user, string $widgetKey, int $ttlSeconds, callable $callback)
    {
        $cached = self::where('user_id', $user->id)
            ->where('widget_key', $widgetKey)
            ->first();

        if ($cached && $cached->isFresh()) {
            return $cached->payload;
        }

        $payload = $callback();

        self::updateOrCreate(
            ['user_id' => $user->id, 'widget_key' => $widgetKey],
            ['payload' => $payload, 'expires_at' => now()->addSeconds($ttlSeconds)]
        );

        return $payload;
    }
}