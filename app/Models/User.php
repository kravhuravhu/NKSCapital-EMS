<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'employee_number',
        'first_name',
        'last_name',
        'id_number',
        'employee_type',
        'service_type',
        'client_id',
        'project_id',
        'hire_date',
        'termination_date',
        'leave_balance_annual',
        'leave_balance_sick',
        'phone',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'profile_photo',
        'two_factor_enabled',
        'last_login_at',
        'manager_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'two_factor_enabled' => 'boolean',
        'last_login_at' => 'datetime',
        'hire_date' => 'date',
        'termination_date' => 'date',
    ];

    // Relationships
    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function psTimesheets()
    {
        return $this->hasMany(PSTimesheet::class);
    }

    public function prTimesheets()
    {
        return $this->hasMany(PRTimesheet::class);
    }

    public function leaveRequests()
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveBalances()
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class, 'current_assignee_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    // Scopes
    public function scopePS($query)
    {
        return $query->where('employee_type', 'ps');
    }

    public function scopePR($query)
    {
        return $query->where('employee_type', 'pr');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    public function getIsPSAttribute()
    {
        return $this->employee_type === 'ps';
    }

    public function getIsPRAttribute()
    {
        return $this->employee_type === 'pr';
    }
}