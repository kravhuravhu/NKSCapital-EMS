<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveCalendar extends Model
{
    protected $table = 'leave_calendar';

    protected $fillable = [
        'user_id',
        'leave_date',
        'leave_type',
        'leave_request_id',
        'is_approved',
    ];

    protected $casts = [
        'leave_date' => 'date',
        'is_approved' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    // Scope: Get approved leave for a specific user on a specific date
    public function scopeForUserOnDate($query, int $userId, string $date)
    {
        return $query->where('user_id', $userId)
                     ->where('leave_date', $date)
                     ->where('is_approved', true);
    }
}