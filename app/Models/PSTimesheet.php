<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class PSTimesheet extends Model
{
    protected $table = 'ps_timesheets';

    protected $fillable = [
        'user_id',
        'month_year',
        'status',
        'client_manager_id',
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
        'client_signature_date' => 'datetime',
        'template_generated_at' => 'datetime',
        'client_emailed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clientManager(): BelongsTo
    {
        return $this->belongsTo(ClientManager::class, 'client_manager_id');
    }

    public function details(): MorphMany
    {
        return $this->morphMany(TimesheetDetail::class, 'timesheet_parent');
    }

    // Scopes
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

    public function scopeExternalSigned($query)
    {
        return $query->where('status', 'external_signed');
    }

    public function scopeSubmittedToClient($query)
    {
        return $query->where('status', 'submitted_to_client');
    }

    // Helper methods
    public function canRecall(): bool
    {
        return in_array($this->status, ['draft', 'template_generated', 'external_pending']);
    }

    public function getTotalHoursAttribute(): float
    {
        return $this->details()->sum('hours_worked');
    }
}