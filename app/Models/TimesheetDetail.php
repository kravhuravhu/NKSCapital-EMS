<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TimesheetDetail extends Model
{
    protected $fillable = [
        'timesheet_parent_id',
        'timesheet_parent_type',
        'work_date',
        'project_id',
        'hours_worked',
        'is_overtime',
        'task_description',
        'is_billable',
    ];

    protected $casts = [
        'work_date' => 'date',
        'hours_worked' => 'decimal:2',
        'is_overtime' => 'boolean',
        'is_billable' => 'boolean',
    ];

    public function timesheetParent(): MorphTo
    {
        return $this->morphTo();
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}