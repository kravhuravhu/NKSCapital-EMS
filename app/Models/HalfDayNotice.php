<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HalfDayNotice extends Model
{
    protected $fillable = [
        'user_id',
        'notice_date',
        'leave_time',
        'reason',
        'status',
        'manager_id',
        'manager_response',
        'responded_at',
    ];

    protected $casts = [
        'notice_date' => 'date',
        'responded_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }
}