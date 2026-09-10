<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceType extends Model
{
    protected $fillable = [
        'service_code',
        'service_name',
        'has_benefits',
        'has_leave_accrual',
        'leave_accrual_rate',
        'has_probation_period',
        'probation_days',
        'description',
    ];

    protected $casts = [
        'has_benefits' => 'boolean',
        'has_leave_accrual' => 'boolean',
        'has_probation_period' => 'boolean',
        'leave_accrual_rate' => 'decimal:2',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'service_type', 'service_code');
    }
}