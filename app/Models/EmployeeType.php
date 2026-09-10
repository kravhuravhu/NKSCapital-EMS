<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeType extends Model
{
    protected $fillable = [
        'type_code',
        'type_name',
        'workflow_type',
        'has_external_approval',
        'has_internal_approval',
        'description',
    ];

    protected $casts = [
        'has_external_approval' => 'boolean',
        'has_internal_approval' => 'boolean',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'employee_type', 'type_code');
    }

    public function workflowConfig(): HasMany
    {
        return $this->hasMany(WorkflowConfig::class, 'workflow_type', 'workflow_type');
    }
}