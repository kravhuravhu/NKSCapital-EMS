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
use Illuminate\Database\Eloquent\Relations\MorphMany;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'two_factor_confirmed_at',
        'employee_number',
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
        'created_at',
        'updated_at',
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
        'is_ps',
        'is_pr',
        'is_active',
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
        'role' => 'employee',
    ];

    // ========================================
    // ACCESSORS
    // ========================================

    /**
     * Get the user's full name.
     */
    public function getFullNameAttribute(): string
    {
        return trim($this->first_name . ' ' . $this->last_name);
    }

    /**
     * Get the user's display name.
     */
    public function getDisplayNameAttribute(): string
    {
        if ($this->employee_number) {
            return $this->full_name . ' (' . $this->employee_number . ')';
        }
        return $this->full_name;
    }

    /**
     * Get the user's profile photo URL.
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo) {
            return asset('storage/profile-photos/' . $this->profile_photo);
        }
        
        $name = urlencode($this->full_name ?: $this->email);
        return 'https://ui-avatars.com/api/?name=' . $name . '&background=1a56db&color=fff&size=200';
    }

    /**
     * Check if user is a Professional Services employee.
     */
    public function getIsPsAttribute(): bool
    {
        return $this->employee_type === 'ps';
    }

    /**
     * Check if user is a Projects employee.
     */
    public function getIsPrAttribute(): bool
    {
        return $this->employee_type === 'pr';
    }

    /**
     * Check if user is active.
     */
    public function getIsActiveAttribute(): bool
    {
        return (bool) ($this->attributes['is_active'] ?? true);
    }

    /**
     * Get the user's initials.
     */
    public function getInitialsAttribute(): string
    {
        return strtoupper(
            substr($this->first_name ?? '', 0, 1) . 
            substr($this->last_name ?? '', 0, 1)
        );
    }

    /**
     * Get the user's role label.
     */
    public function getRoleLabelAttribute(): string
    {
        $labels = [
            'employee' => 'Employee',
            'manager' => 'Manager',
            'director' => 'Director',
            'admin' => 'Administrator',
            'recruiter' => 'Recruiter',
        ];
        return $labels[$this->role] ?? ucfirst($this->role);
    }

    /**
     * Get the employee type label.
     */
    public function getEmployeeTypeLabelAttribute(): string
    {
        $labels = [
            'ps' => 'Professional Services',
            'pr' => 'Projects',
        ];
        return $labels[$this->employee_type] ?? 'Not Assigned';
    }

    /**
     * Get the service type label.
     */
    public function getServiceTypeLabelAttribute(): string
    {
        $labels = [
            'permanent' => 'Permanent',
            'contractor' => 'Contractor',
            'temporary' => 'Temporary',
            'intern' => 'Intern',
        ];
        return $labels[$this->service_type] ?? 'Not Assigned';
    }

    // ========================================
    // MUTATORS
    // ========================================

    /**
     * Set the employee type with validation.
     */
    public function setEmployeeTypeAttribute($value)
    {
        if ($value && !in_array($value, ['ps', 'pr'])) {
            throw new \InvalidArgumentException('Invalid employee type. Must be ps or pr.');
        }
        $this->attributes['employee_type'] = $value;
    }

    /**
     * Set the service type with validation.
     */
    public function setServiceTypeAttribute($value)
    {
        if ($value && !in_array($value, ['permanent', 'contractor', 'temporary', 'intern'])) {
            throw new \InvalidArgumentException('Invalid service type.');
        }
        $this->attributes['service_type'] = $value;
    }

    /**
     * Set the role with validation.
     */
    public function setRoleAttribute($value)
    {
        if ($value && !in_array($value, ['employee', 'manager', 'director', 'admin', 'recruiter'])) {
            throw new \InvalidArgumentException('Invalid role.');
        }
        $this->attributes['role'] = $value;
    }

    // ========================================
    // RELATIONSHIPS
    // ========================================

    // ---------- MANAGER / SUBORDINATE ----------
    
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
     * Get all subordinates recursively (for hierarchy).
     */
    public function allSubordinates(): \Illuminate\Support\Collection
    {
        $subordinates = collect();
        foreach ($this->subordinates as $subordinate) {
            $subordinates->push($subordinate);
            $subordinates = $subordinates->merge($subordinate->allSubordinates());
        }
        return $subordinates;
    }

    // ---------- CLIENT / PROJECT ----------
    
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

    // ---------- EMPLOYEE TYPES ----------
    
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

    // ---------- TIMESHEETS ----------
    
    /**
     * Get the PS timesheets for this employee.
     */
    public function psTimesheets(): HasMany
    {
        return $this->hasMany(PSTimesheet::class);
    }

    /**
     * Get the PR timesheets for this employee.
     */
    public function prTimesheets(): HasMany
    {
        return $this->hasMany(PRTimesheet::class);
    }

    /**
     * Get timesheets where this user is L1 approver.
     */
    public function l1Approvals(): HasMany
    {
        return $this->hasMany(PRTimesheet::class, 'level1_approver_id');
    }

    /**
     * Get timesheets where this user is L2 approver.
     */
    public function l2Approvals(): HasMany
    {
        return $this->hasMany(PRTimesheet::class, 'level2_approver_id');
    }

    // ---------- LEAVE ----------
    
    /**
     * Get the leave requests for this employee.
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Get leave requests approved by this user.
     */
    public function approvedLeaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'approved_by');
    }

    /**
     * Get the leave balances for this employee.
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * Get the leave calendar entries for this employee.
     */
    public function leaveCalendar(): HasMany
    {
        return $this->hasMany(LeaveCalendar::class);
    }

    // ---------- ASSETS ----------
    
    /**
     * Get assets currently assigned to this employee.
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'current_assignee_id');
    }

    /**
     * Get asset custody history for this employee.
     */
    public function assetCustodyHistory(): HasMany
    {
        return $this->hasMany(AssetCustodyHistory::class, 'assigned_to_id');
    }

    // ---------- MEETINGS ----------
    
    /**
     * Get meeting attendance records for this employee.
     */
    public function meetingAttendance(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class);
    }

    /**
     * Get meetings created by this employee.
     */
    public function createdMeetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'created_by');
    }

    // ---------- CONTRACTS ----------
    
    /**
     * Get contracts for this employee.
     */
    public function contracts(): HasMany
    {
        return $this->hasMany(Contract::class);
    }

    /**
     * Get the active contract for this employee.
     */
    public function activeContract(): HasOne
    {
        return $this->hasOne(Contract::class)->where('status', 'active');
    }

    // ---------- PROJECTS ----------
    
    /**
     * Get project assignments for this employee.
     */
    public function projectAssignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    /**
     * Get projects where this user is project manager.
     */
    public function managedProjects(): HasMany
    {
        return $this->hasMany(Project::class, 'project_manager_id');
    }

    /**
     * Get active projects for this employee.
     */
    public function activeProjects()
    {
        return $this->projectAssignments()
                    ->where('is_active', true)
                    ->with('project');
    }

    // ---------- RECRUITMENT ----------
    
    /**
     * Get requisitions created by this user.
     */
    public function createdRequisitions(): HasMany
    {
        return $this->hasMany(Requisition::class, 'created_by');
    }

    /**
     * Get requisitions approved by this user.
     */
    public function approvedRequisitions(): HasMany
    {
        return $this->hasMany(Requisition::class, 'approved_by');
    }

    /**
     * Get candidates that were hired by this user (converted to employee).
     */
    public function hiredCandidates(): HasMany
    {
        return $this->hasMany(Candidate::class, 'hired_employee_id');
    }

    /**
     * Get interviews conducted by this user.
     */
    public function conductedInterviews(): HasMany
    {
        return $this->hasMany(Interview::class, 'interviewer_id');
    }

    /**
     * Get offers created by this user.
     */
    public function createdOffers(): HasMany
    {
        return $this->hasMany(Offer::class, 'created_by');
    }

    // ---------- SYSTEM ----------
    
    /**
     * Get delegations where this user is the original approver.
     */
    public function delegationsGiven(): HasMany
    {
        return $this->hasMany(ApprovalDelegation::class, 'original_approver_id');
    }

    /**
     * Get delegations where this user is the delegate.
     */
    public function delegationsReceived(): HasMany
    {
        return $this->hasMany(ApprovalDelegation::class, 'delegate_id');
    }

    /**
     * Get audit logs generated by this user.
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    /**
     * Get notifications for this user.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get unread notifications.
     */
    public function unreadNotifications()
    {
        return $this->notifications()->where('is_read', false);
    }

    /**
     * Get two-factor authentication record for this user.
     */
    public function twoFactorAuth(): HasOne
    {
        return $this->hasOne(TwoFactorAuth::class);
    }

    /**
     * Get passkeys for this user.
     */
    public function passkeys(): HasMany
    {
        return $this->hasMany(Passkey::class);
    }

    // ---------- SPATIE PERMISSION OVERRIDE ----------
    
    /**
     * Override the default role relationship to use our role column.
     */
    public function getRoleNamesAttribute()
    {
        return $this->getRoleNames();
    }

    // ========================================
    // SCOPES
    // ========================================

    /**
     * Scope a query to only include Professional Services employees.
     */
    public function scopePS($query)
    {
        return $query->where('employee_type', 'ps');
    }

    /**
     * Scope a query to only include Projects employees.
     */
    public function scopePR($query)
    {
        return $query->where('employee_type', 'pr');
    }

    /**
     * Scope a query to only include active employees.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include inactive employees.
     */
    public function scopeInactive($query)
    {
        return $query->where('is_active', false);
    }

    /**
     * Scope a query to only include employees with a specific role.
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope a query to only include employees with a specific employee type.
     */
    public function scopeWithEmployeeType($query, string $type)
    {
        return $query->where('employee_type', $type);
    }

    /**
     * Scope a query to only include employees with a specific service type.
     */
    public function scopeWithServiceType($query, string $type)
    {
        return $query->where('service_type', $type);
    }

    /**
     * Scope a query to search employees.
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('first_name', 'LIKE', "%{$search}%")
              ->orWhere('last_name', 'LIKE', "%{$search}%")
              ->orWhere('email', 'LIKE', "%{$search}%")
              ->orWhere('employee_number', 'LIKE', "%{$search}%")
              ->orWhere('phone', 'LIKE', "%{$search}%");
        });
    }

    // ========================================
    // HELPER METHODS - ROLE CHECKS
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
        return (bool) $this->two_factor_enabled && !empty($this->two_factor_secret);
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
     * Check if user is an employee (base role).
     */
    public function isEmployee(): bool
    {
        return $this->role === 'employee';
    }

    /**
     * Check if user has any of the specified roles.
     */
    public function hasAnyRole(array $roles): bool
    {
        return in_array($this->role, $roles);
    }

    // ========================================
    // HELPER METHODS - LEAVE BALANCE
    // ========================================

    /**
     * Get the user's leave balance for a specific type.
     */
    public function getLeaveBalance(string $type): float
    {
        return match ($type) {
            'annual' => (float) $this->leave_balance_annual,
            'sick'   => (float) $this->leave_balance_sick,
            default  => 0.00,
        };
    }

    /**
     * Get all leave balances as an array.
     */
    public function getAllLeaveBalances(): array
    {
        return [
            'annual' => (float) $this->leave_balance_annual,
            'sick'   => (float) $this->leave_balance_sick,
        ];
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
            'sick'   => $this->leave_balance_sick = $newBalance,
            default  => null,
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
            'sick'   => $this->leave_balance_sick += $days,
            default  => null,
        };
        
        $this->save();
    }

    /**
     * Reset leave balances to default values.
     */
    public function resetLeaveBalances(): void
    {
        $this->leave_balance_annual = 0;
        $this->leave_balance_sick = 0;
        $this->save();
    }

    // ========================================
    // HELPER METHODS - AUTHENTICATION
    // ========================================

    /**
     * Get the user's full name (alias for getFullNameAttribute).
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
        return $this->initials;
    }

    /**
     * Update last login timestamp.
     */
    public function updateLastLogin(): void
    {
        $this->last_login_at = now();
        $this->save();
    }

    /**
     * Check if user can access the specified module.
     */
    public function canAccessModule(string $module): bool
    {
        // Admin and Director can access everything
        if (in_array($this->role, ['admin', 'director'])) {
            return true;
        }

        // Module-specific access
        $moduleAccess = [
            'recruitment' => ['recruiter', 'manager', 'admin', 'director'],
            'timesheet'   => ['employee', 'manager', 'admin', 'director'],
            'leave'       => ['employee', 'manager', 'admin', 'director'],
            'assets'      => ['employee', 'manager', 'admin', 'director'],
            'reports'     => ['manager', 'admin', 'director'],
            'audit'       => ['director', 'admin'],
            'payroll'     => ['director', 'admin'],
            'delegation'  => ['director', 'admin'],
        ];

        $allowedRoles = $moduleAccess[$module] ?? ['employee', 'manager', 'admin', 'director'];
        
        return in_array($this->role, $allowedRoles);
    }

    // ========================================
    // HELPER METHODS - EMPLOYEE TYPE
    // ========================================

    /**
     * Get the workflow type for this employee.
     */
    public function getWorkflowType(): ?string
    {
        if ($this->employeeType) {
            return $this->employeeType->workflow_type;
        }
        return null;
    }

    /**
     * Check if employee has external approval (PS workflow).
     */
    public function hasExternalApproval(): bool
    {
        return $this->employeeType && $this->employeeType->has_external_approval;
    }

    /**
     * Check if employee has internal approval (PR workflow).
     */
    public function hasInternalApproval(): bool
    {
        return $this->employeeType && $this->employeeType->has_internal_approval;
    }

    /**
     * Get the employee's hierarchy level.
     */
    public function getHierarchyLevel(): int
    {
        $levels = [
            'employee' => 1,
            'manager' => 2,
            'director' => 3,
            'admin' => 4,
            'recruiter' => 2,
        ];
        return $levels[$this->role] ?? 1;
    }

    /**
     * Check if this user is a superior to another user.
     */
    public function isSuperiorTo(User $user): bool
    {
        if ($this->id === $user->id) {
            return false;
        }

        if ($this->role === 'admin' || $this->role === 'director') {
            return true;
        }

        if ($this->role === 'manager' && $user->manager_id === $this->id) {
            return true;
        }

        return false;
    }
}