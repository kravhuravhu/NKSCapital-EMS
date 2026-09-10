<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'client_id',
        'project_code',
        'name',
        'description',
        'project_type',
        'budgeted_hours',
        'hourly_rate',
        'start_date',
        'end_date',
        'project_manager_id',
        'status',
    ];

    protected $casts = [
        'budgeted_hours' => 'decimal:2',
        'hourly_rate' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
    ];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'project_assignments');
    }

    public function timesheetDetails()
    {
        return $this->hasMany(TimesheetDetail::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeClientProject($query)
    {
        return $query->where('project_type', 'client');
    }

    public function scopeInternalProject($query)
    {
        return $query->where('project_type', 'internal');
    }
}