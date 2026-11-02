<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Interview extends Model
{
    protected $fillable = [
        'candidate_id',
        'interviewer_id',
        'scheduled_at',
        'duration_minutes',
        'interview_type',
        'round',
        'location_or_link',
        'meeting_link',
        'status',
        'feedback_rating',
        'feedback_notes',
        'recommendation',
        'completed_at',
        'cancel_reason',
        'cancelled_by',
        'cancelled_at',
        'reschedule_count',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'duration_minutes' => 'integer',
        'round' => 'integer',
        'reschedule_count' => 'integer',
        'feedback_rating' => 'integer',
    ];

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function interviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'interviewer_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    // Scopes
    public function scopeUpcoming($query)
    {
        return $query->where('scheduled_at', '>=', now())
            ->whereIn('status', ['scheduled', 'rescheduled'])
            ->orderBy('scheduled_at');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeFeedbackPending($query)
    {
        return $query->where('status', 'completed')->whereNull('feedback_rating');
    }

    // Helpers
    public function canSubmitFeedback(): bool
    {
        // cannot re-submit
        if (!is_null($this->feedback_rating)) {
            return false;
        }

        // cancelled and no-show interviews do not get feedback
        if (in_array($this->status, ['cancelled', 'no_show'], true)) {
            return false;
        }

        // feedback can only be submitted once the interview has happened
        return $this->scheduled_at && $this->scheduled_at->isPast();
    }

    public function canReschedule(): bool
    {
        return in_array($this->status, ['scheduled', 'rescheduled']);
    }

    public function canCancel(): bool
    {
        return in_array($this->status, ['scheduled', 'rescheduled']);
    }

    public function isUpcoming(): bool
    {
        return $this->scheduled_at->isFuture()
            && in_array($this->status, ['scheduled', 'rescheduled']);
    }
}