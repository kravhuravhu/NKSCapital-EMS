<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'employee_number',
        'email',
        'password',
        'first_name',
        'last_name',
        'id_number',
        'phone',
        'profile_photo',
        'department',
        'position',
        'role',
        'employee_type',
        'service_type',
        'manager_id',
        'client_id',
        'project_id',
        'hire_date',
        'termination_date',
        'is_active',
        'leave_balance_annual',
        'leave_balance_sick',
        'address',
        'emergency_contact_name',
        'emergency_contact_phone',
        'two_factor_enabled',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'last_login_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'id_number',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [
        'profile_photo_url',
        'full_name',
        'display_name',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
            'hire_date' => 'date',
            'termination_date' => 'date',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'two_factor_enabled' => 'boolean',
            'leave_balance_annual' => 'decimal:2',
            'leave_balance_sick' => 'decimal:2',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * The model's default values for attributes.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'two_factor_enabled' => false,
        'leave_balance_annual' => 0.00,
        'leave_balance_sick' => 0.00,
    ];

    // ========================================
    // ACCESSORS
    // ========================================

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get the user's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->full_name . ' (' . $this->employee_number . ')';
    }

    /**
     * Get the user's profile photo URL.
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo) {
            return asset('storage/profile-photos/' . $this->profile_photo);
        }
        
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->full_name) . '&background=1a56db&color=fff&size=200';
    }

    /**
     * Check if user is a Professional Services employee.
     */
    public function getIsPSAttribute(): bool
    {
        return $this->employee_type === 'ps';
    }

    /**
     * Check if user is a Projects employee.
     */
    public function getIsPRAttribute(): bool
    {
        return $this->employee_type === 'pr';
    }

    // ========================================
    // RELATIONSHIPS
    // ========================================

    /**
     * Get the manager of this employee.
     */
    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    /**
     * Get the subordinates of this employee.
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(User::class, 'manager_id');
    }

    /**
     * Get the client assigned to this employee (PS only).
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the project assigned to this employee (PR only).
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the employee type of this user.
     */
    public function employeeType(): BelongsTo
    {
        return $this->belongsTo(EmployeeType::class, 'employee_type', 'type_code');
    }

    /**
     * Get the service type of this user.
     */
    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class, 'service_type', 'service_code');
    }

    // ========================================
    // TIMESHEET RELATIONSHIPS
    // ========================================

    public function psTimesheets(): HasMany
    {
        return $this->hasMany(PSTimesheet::class);
    }

    public function prTimesheets(): HasMany
    {
        return $this->hasMany(PRTimesheet::class);
    }

    public function l1Approvals(): HasMany
    {
        return $this->hasMany(PRTimesheet::class, 'level1_approver_id');
    }

    public function l2Approvals(): HasMany
    {
        return $this->hasMany(PRTimesheet::class, 'level2_approver_id');
    }

    // ========================================
    // LEAVE RELATIONSHIPS
    // ========================================

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function approvedLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by');
    }

    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    public function leaveCalendar(): HasMany
    {
        return $this->hasMany(LeaveCalendar::class);
    }

    // ========================================
    // ASSET RELATIONSHIPS
    // ========================================

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'current_assignee_id');
    }

    public function assetCustodyHistory(): HasMany
    {
        return $this->hasMany(AssetCustodyHistory::class, 'assigned_to_id');
    }

    // ========================================
    // MEETING RELATIONSHIPS
    // ========================================

    public function meetingAttendance(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    public function createdMeetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'created_by');
    }

    // ========================================
    // CONTRACT RELATIONSHIPS
    // ========================================

    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    // ========================================
    // PROJECT RELATIONSHIPS
    // ========================================

    public function projectAssignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_manager_id');
    }

    // ========================================
    // RECRUITMENT RELATIONSHIPS
    // ========================================

    public function createdRequisitions(): HasMany
    {
        return $this->hasMany(Requisition::class, 'created_by');
    }

    public function approvedRequisitions(): HasMany
    {
        return $this->hasMany(Requisition::class, 'approved_by');
    }

    public function hiredCandidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'hired_employee_id');
    }

    public function conductedInterviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'interviewer_id');
    }

    public function createdOffers(): HasMany
    {
        return $this->hasMany(Offer::class, 'created_by');
    }

    // ========================================
    // SYSTEM RELATIONSHIPS
    // ========================================

    public function delegationsGiven(): HasMany
    {
        return $this->hasMany(ApprovalDelegation::class, 'original_approver_id');
    }

    public function delegationsReceived(): HasMany
    {
        return $this->hasMany(ApprovalDelegation::class, 'delegate_id');
    }

    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function twoFactorAuth(): HasOne
    {
        return $this->hasOne(TwoFactorAuth::class);
    }

    public function passkeys(): HasMany
    {
        return $this->hasMany(Passkey::class);
    }

    // ========================================
    // SCOPES
    // ========================================

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

    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    public function scopeWithEmployeeType($query, string $type)
    {
        return $query->where('employee_type', $type);
    }

    public function scopeWithServiceType($query, string $type)
    {
        return $query->where('service_type', $type);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    /**
     * Check if user requires Two-Factor Authentication based on role.
     */
    public function requiresTwoFactor(): bool
    {
        return in_array($this->role, ['manager', 'director', 'admin']);
    }

    /**
     * Check if user has 2FA enabled.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return $this->two_factor_enabled && !empty($this->two_factor_secret);
    }

    /**
     * Check if user is a Professional Services employee.
     */
    public function isPS(): bool
    {
        return $this->employee_type === 'ps';
    }

    /**
     * Check if user is a Projects employee.
     */
    public function isPR(): bool
    {
        return $this->employee_type === 'pr';
    }

    /**
     * Check if user is a manager.
     */
    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    /**
     * Check if user is a director.
     */
    public function isDirector(): bool
    {
        return $this->role === 'director';
    }

    /**
     * Check if user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Check if user is a recruiter.
     */
    public function isRecruiter(): bool
    {
        return $this->role === 'recruiter';
    }

    /**
     * Get the user's leave balance for a specific type.
     */
    public function getLeaveBalance(string $type): float
    {
        return match ($type) {
            'annual' => (float) $this->leave_balance_annual,
            'sick' => (float) $this->leave_balance_sick,
            default => 0.00,
        };
    }

    /**
     * Deduct leave balance for a specific type.
     */
    public function deductLeaveBalance(string $type, float $days): bool
    {
        $current = $this->getLeaveBalance($type);
        
        if ($current < $days) {
            return false;
        }
        
        $newBalance = $current - $days;
        
        match ($type) {
            'annual' => $this->leave_balance_annual = $newBalance,
            'sick' => $this->leave_balance_sick = $newBalance,
            default => null,
        };
        
        $this->save();
        
        return true;
    }

    /**
     * Add leave balance for a specific type.
     */
    public function addLeaveBalance(string $type, float $days): void
    {
        match ($type) {
            'annual' => $this->leave_balance_annual += $days,
            'sick' => $this->leave_balance_sick += $days,
            default => null,
        };
        
        $this->save();
    }

    /**
     * Get the user's full name.
     */
    public function getName(): string
    {
        return $this->full_name;
    }

    /**
     * Get the user's initials.
     */
    public function getInitials(): string
    {
        return strtoupper(substr($this->first_name, 0, 1) . substr($this->last_name, 0, 1));
    }
}