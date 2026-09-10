<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowConfig extends Model
{
    protected $fillable = [
        'workflow_type',
        'approval_chain',
        'notification_settings',
        'validation_rules',
        'escalation_rules',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'approval_chain' => 'array',
        'notification_settings' => 'array',
        'validation_rules' => 'array',
        'escalation_rules' => 'array',
        'is_active' => 'boolean',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}