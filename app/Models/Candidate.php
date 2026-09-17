<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    protected $fillable = [
        'requisition_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'years_experience',
        'expected_salary',
        'skills',
        'resume_path',
        'resume_hash',
        'source',
        'linkedin_url',
        'tags',
        'notes',
        'rejection_reason',
        'rejected_by',
        'rejected_at',
        'current_stage',
        'applied_date',
        'hired_employee_id',
        'hire_date',
    ];

    protected $casts = [
        'skills' => 'array',
        'tags' => 'array',
        'years_experience' => 'integer',
        'expected_salary' => 'decimal:2',
        'applied_date' => 'date',
        'hire_date' => 'date',
        'rejected_at' => 'datetime',
    ];

    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class)->orderBy('scheduled_at', 'desc');
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function hiredEmployee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hired_employee_id');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    public function isRejected(): bool
    {
        return $this->current_stage === 'rejected';
    }

    public function isHired(): bool
    {
        return $this->current_stage === 'hired';
    }

    public function canAdvanceStage(): bool
    {
        return !in_array($this->current_stage, ['hired', 'rejected', 'withdrawn']);
    }

    public function canBeRejected(): bool
    {
        return !in_array($this->current_stage, ['hired', 'rejected', 'withdrawn']);
    }

    public function stagePipelinePosition(): int
    {
        $order = ['applied' => 1, 'screening' => 2, 'interview' => 3, 'offer' => 4, 'hired' => 5];
        return $order[$this->current_stage] ?? 0;
    }
}