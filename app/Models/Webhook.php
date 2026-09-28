<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    protected $fillable = [
        'name', 'target_url', 'secret', 'events', 'is_active',
        'created_by', 'last_triggered_at', 'last_status',
        'failure_count', 'success_count',
    ];

    protected $casts = [
        'events' => 'array',
        'is_active' => 'boolean',
        'last_triggered_at' => 'datetime',
        'last_status' => 'integer',
        'failure_count' => 'integer',
        'success_count' => 'integer',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(WebhookLog::class)->orderByDesc('created_at');
    }

    public function subscribesTo(string $event): bool
    {
        $events = $this->events ?? [];
        return in_array('*', $events, true) || in_array($event, $events, true);
    }

    public function hasFailed(): bool
    {
        return $this->failure_count >= 5;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}