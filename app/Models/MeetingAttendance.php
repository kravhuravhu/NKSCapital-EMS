<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeetingAttendance extends Model
{
    protected $table = 'meeting_attendance';

    protected $fillable = [
        'meeting_id',
        'user_id',
        'check_in_time',
        'is_late',
        'minutes_late',
        'late_status',
        'escalation_level',
        'escalation_sent_at',
        'notes',
        'sign_method',
        'override_reason',
        'override_by',
        'excused',
    ];

    protected $casts = [
        'check_in_time' => 'datetime',
        'is_late' => 'boolean',
        'minutes_late' => 'integer',
        'escalation_level' => 'integer',
        'escalation_sent_at' => 'datetime',
        'excused' => 'boolean',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function overrideBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'override_by');
    }

    // Scopes
    public function scopeLate($query)
    {
        return $query->where('late_status', 'late');
    }

    public function scopeExcessiveLate($query)
    {
        return $query->where('late_status', 'excessive_late');
    }

    public function scopeOnTime($query)
    {
        return $query->where('late_status', 'on_time');
    }

    public function scopeNotExcused($query)
    {
        return $query->where('excused', false);
    }
}