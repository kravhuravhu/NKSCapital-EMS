<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requisition extends Model
{
    protected $fillable = [
        'requisition_code',
        'title',
        'department',
        'employment_type',
        'employee_type_target',
        'experience_level',
        'priority',
        'required_skills',
        'salary_range_min',
        'salary_range_max',
        'salary_currency',
        'reason_for_hire',
        'status',
        'rejection_reason',
        'approved_by',
        'hiring_manager_id',
        'approval_date',
        'target_start_date',
        'created_by',
    ];

    protected $casts = [
        'required_skills' => 'array',
        'salary_range_min' => 'decimal:2',
        'salary_range_max' => 'decimal:2',
        'approval_date' => 'date',
        'target_start_date' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function hiringManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hiring_manager_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    // Helpers
    public function canApprove(): bool
    {
        return $this->status === 'pending_approval';
    }

    public function canUpdate(): bool
    {
        return in_array($this->status, ['draft', 'pending_approval', 'approved', 'open']);
    }

    public function isOpenForApplications(): bool
    {
        return $this->status === 'open';
    }

    public function getCandidateCountAttribute(): int
    {
        return $this->candidates()->count();
    }
}