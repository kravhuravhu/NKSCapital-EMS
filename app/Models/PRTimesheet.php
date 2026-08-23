<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PRTimesheet extends Model
{
    protected $table = 'pr_timesheets';

    protected $fillable = [
        'user_id',
        'month_year',
        'status',
        'level1_approver_id',
        'level2_approver_id',
        'level1_approved_at',
        'level2_approved_at',
        'level1_rejection_reason',
        'level2_rejection_reason',
        'total_regular_hours',
        'total_overtime_hours',
        'pdf_path',
        'pdf_hash',
        'exported_to_payroll',
        'exported_at',
        'is_escalated',
        'escalated_at',
    ];

    protected $casts = [
        'month_year' => 'date',
        'level1_approved_at' => 'datetime',
        'level2_approved_at' => 'datetime',
        'exported_at' => 'datetime',
        'escalated_at' => 'datetime',
        'exported_to_payroll' => 'boolean',
        'is_escalated' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function level1Approver()
    {
        return $this->belongsTo(User::class, 'level1_approver_id');
    }

    public function level2Approver()
    {
        return $this->belongsTo(User::class, 'level2_approver_id');
    }

    public function details()
    {
        return $this->morphMany(TimesheetDetail::class, 'timesheet_parent');
    }

    public function scopePendingL1($query)
    {
        return $query->where('status', 'pending_l1');
    }

    public function scopePendingL2($query)
    {
        return $query->where('status', 'pending_l2');
    }
}