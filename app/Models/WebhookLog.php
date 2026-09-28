<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    protected $fillable = [
        'webhook_id', 'event', 'payload', 'response_status',
        'response_body', 'duration_ms', 'attempt', 'status',
        'error_message', 'signature',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_status' => 'integer',
        'duration_ms' => 'integer',
        'attempt' => 'integer',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('status', ['failed', 'dead_letter']);
    }
}