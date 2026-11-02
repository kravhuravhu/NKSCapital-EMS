<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemConfig extends Model
{
    protected $fillable = [
        'config_key',
        'config_value',
        'description',
        'config_type',
        'updated_by',
    ];

    protected $casts = [
        'updated_by' => 'integer',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // category = the part before the first dot (company.leave.timesheet.payroll.notifications.security)
    public function getCategoryAttribute(): string
    {
        return explode('.', $this->config_key)[0] ?? 'general';
    }

    // typed value, so callers do not re-cast in every consumer
    public function getTypedValueAttribute()
    {
        return match ($this->config_type) {
            'integer' => (int) $this->config_value,
            'decimal' => (float) $this->config_value,
            'boolean' => (bool) $this->config_value,
            default   => $this->config_value,
        };
    }

    // sensitive keys require password confirmation on the frontend
    public function isSensitive(): bool
    {
        $sensitive = [
            'payroll.default_hourly_rate',
            'payroll.overtime_multiplier',
            'payroll.ps_billing_multiplier',
            'security.password_reset_ttl_minutes',
            'security.max_login_attempts',
            'security.session_timeout_minutes',
        ];

        return in_array($this->config_key, $sensitive, true);
    }

    // convenience for services that want a typed value without touching the model
    public static function get(string $key, $default = null)
    {
        $config = self::where('config_key', $key)->first();

        return $config ? $config->typed_value : $default;
    }
}