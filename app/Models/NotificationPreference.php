<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'category',
        'in_app',
        'email',
        'digest_only',
    ];

    protected $casts = [
        'in_app' => 'boolean',
        'email' => 'boolean',
        'digest_only' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get preference for a user + category, defaulting to sensible values.
     */
    public static function resolveFor(User $user, string $category): self
    {
        return self::firstOrCreate(
            ['user_id' => $user->id, 'category' => $category],
            [
                'in_app' => true,
                'email' => in_array($category, ['timesheet', 'leave', 'asset', 'contract', 'payroll']),
                'digest_only' => false,
            ]
        );
    }
}