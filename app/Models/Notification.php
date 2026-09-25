<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'channel',
        'category',
        'priority',
        'title',
        'message',
        'action_url',
        'action_label',
        'reference_id',
        'reference_type',
        'group_key',
        'is_read',
        'read_at',
        'delivered_at',
        'sent_at',
        'failed_at',
        'failure_reason',
        'dismissible',
        'expires_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'dismissible' => 'boolean',
        'read_at' => 'datetime',
        'delivered_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    // Scopes
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    public function scopeRead($query)
    {
        return $query->where('is_read', true);
    }

    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    public function scopeUrgent($query)
    {
        return $query->whereIn('priority', ['high', 'urgent']);
    }

    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    // Helpers
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function markAsRead(): void
    {
        if (!$this->is_read) {
            $this->is_read = true;
            $this->read_at = now();
            $this->save();
        }
    }

    public static function byCategory(User $user, string $category, int $limit = 20)
    {
        return self::where('user_id', $user->id)
            ->category($category)
            ->notExpired()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}