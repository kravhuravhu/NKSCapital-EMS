<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PSTimesheet extends Model
{
    protected $table = 'ps_timesheets';

    protected $fillable = [
        'user_id',
        'month_year',
        'status',
        'client_manager_email',
        'signed_pdf_path',
        'signed_pdf_hash',
        'client_signature_date',
        'template_generated_at',
        'client_emailed_at',
        'notes',
    ];

    protected $casts = [
        'month_year' => 'date',
        'client_signature_date' => 'date',
        'template_generated_at' => 'datetime',
        'client_emailed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function details()
    {
        return $this->morphMany(TimesheetDetail::class, 'timesheet_parent');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeTemplateGenerated($query)
    {
        return $query->where('status', 'template_generated');
    }

    public function scopeExternalPending($query)
    {
        return $query->where('status', 'external_pending');
    }
}