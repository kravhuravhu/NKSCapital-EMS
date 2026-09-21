<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;

class Meeting extends Model
{
    protected $fillable = [
        'title',
        'type',
        'is_mandatory',
        'late_threshold_minutes',
        'excessive_late_threshold_minutes',
        'department',
        'description',
        'start_time',
        'end_time',
        'location',
        'meeting_link',
        'qr_code_secret',
        'qr_code_path',
        'created_by',
        'status',
        'cancellation_reason',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'is_mandatory' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'late_threshold_minutes' => 'integer',
        'excessive_late_threshold_minutes' => 'integer',
        'cancelled_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>=', now())
            ->where('status', 'scheduled')
            ->orderBy('start_time');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('start_time', now()->toDateString());
    }

    public function scopeOngoing($query)
    {
        return $query->where('status', 'ongoing')
            ->orWhere(function ($q) {
                $q->where('start_time', '<=', now())
                  ->where('end_time', '>=', now())
                  ->where('status', 'scheduled');
            });
    }

    public function scopePast($query)
    {
        return $query->where('end_time', '<', now());
    }

    // Helpers
    public function isPast(): bool
    {
        return $this->end_time->isPast();
    }

    public function isOngoing(): bool
    {
        return $this->start_time->isPast() && $this->end_time->isFuture();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['scheduled', 'postponed']);
    }

    public function canBeUpdated(): bool
    {
        return in_array($this->status, ['scheduled', 'postponed']);
    }

    public function canAcceptCheckin(): bool
    {
        return in_array($this->status, ['scheduled', 'ongoing'])
            && now()->between(
                $this->start_time->copy()->subMinutes(15),
                $this->end_time
            );
    }

    public function computeLateStatus(int $minutesLate): string
    {
        if ($minutesLate <= 0) return 'on_time';
        if ($minutesLate <= $this->late_threshold_minutes) return 'on_time';
        if ($minutesLate <= $this->excessive_late_threshold_minutes) return 'late';
        return 'excessive_late';
    }

    public function getAttendanceRateAttribute(): float
    {
        $total = $this->attendance()->count();
        if ($total === 0) return 0;
        $present = $this->attendance()->whereIn('late_status', ['on_time', 'late'])->count();
        return round(($present / $total) * 100, 2);
    }
}