<div class="page-employees">
    @if ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($flash)
        <div class="nks-notice" style="background: var(--nks-mint); color: #1F6B45;">
            <i class="fa-solid fa-circle-check" style="color: #1F6B45;"></i>
            <div class="nks-notice-body">{{ $flash }}</div>
        </div>
    @endif

    <div class="panel em-panel">
        <div class="em-header">
            <i class="fa-solid fa-users-cog em-header-icon"></i>
            <div>
                <h2>Employees</h2>
                <p>Create, edit, view, and manage employee accounts</p>
            </div>
            <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary em-create-btn">
                <i class="fa-solid fa-user-plus"></i>
                Create Employee
            </button>
        </div>

        <div class="em-filters">
            <div class="nks-field em-filter-search">
                <label class="nks-label">Search</label>
                <input type="text"
                       wire:model="search"
                       wire:keydown.enter="applyFilters"
                       class="nks-input"
                       placeholder="Name, email, or employee number">
            </div>
            <div class="nks-field">
                <label class="nks-label">Role</label>
                <select wire:model="roleFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="employee">Employee</option>
                    <option value="manager">Manager</option>
                    <option value="director">Director</option>
                    <option value="admin">Admin</option>
                    <option value="recruiter">Recruiter</option>
                </select>
            </div>
            <div class="nks-field">
                <label class="nks-label">Type</label>
                <select wire:model="employeeTypeFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="ps">PS</option>
                    <option value="pr">PR</option>
                </select>
            </div>
            <div class="nks-field">
                <label class="nks-label">Service</label>
                <select wire:model="serviceTypeFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="permanent">Permanent</option>
                    <option value="contractor">Contractor</option>
                    <option value="temporary">Temporary</option>
                    <option value="intern">Intern</option>
                </select>
            </div>
            <div class="nks-field">
                <label class="nks-label">Status</label>
                <select wire:model="activeFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="em-filter-actions">
                <button type="button" wire:click="resetFilters" class="nks-btn nks-btn--ghost nks-btn--sm">
                    <i class="fa-solid fa-rotate-left"></i>
                    Reset
                </button>
                <button type="button" wire:click="applyFilters" class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-filter"></i>
                    Apply
                </button>
            </div>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading employees…" />
        @elseif (count($employees) === 0)
            <div class="em-empty">
                <i class="fa-solid fa-users-slash"></i>
                <strong>No employees found</strong>
                <span>Try adjusting your filters or create a new employee.</span>
            </div>
        @else
            <div class="em-table-wrapper">
                <table class="em-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Type</th>
                            <th>Department</th>
                            <th>Hired</th>
                            <th>Status</th>
                            <th style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $emp)
                            <tr wire:key="emp-{{ $emp['id'] }}">
                                <td>
                                    <div class="em-name">{{ $emp['first_name'] ?? '' }} {{ $emp['last_name'] ?? '' }}</div>
                                    <div class="em-meta">{{ $emp['employee_number'] ?? '' }} · {{ $emp['email'] ?? '' }}</div>
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Admin\EmployeeManagement::roleTone($emp['role'] ?? '')">
                                        {{ ucfirst($emp['role'] ?? 'employee') }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    @if (!empty($emp['employee_type']))
                                        <x-nks.badge tone="gold">{{ strtoupper($emp['employee_type']) }}</x-nks.badge>
                                    @endif
                                    @if (!empty($emp['service_type']))
                                        <div class="em-meta">{{ ucfirst($emp['service_type']) }}</div>
                                    @endif
                                </td>
                                <td>{{ $emp['department'] ?? '—' }}</td>
                                <td>
                                    {{ !empty($emp['hire_date']) ? \Carbon\Carbon::parse($emp['hire_date'])->format('d M Y') : '—' }}
                                </td>
                                <td>
                                    @if (!empty($emp['is_active']))
                                        <x-nks.badge tone="success">Active</x-nks.badge>
                                    @else
                                        <x-nks.badge tone="muted">Inactive</x-nks.badge>
                                    @endif
                                </td>
                                <td>
                                    <div class="em-row-actions">
                                        <button type="button" wire:click="openView({{ $emp['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i>
                                            View
                                        </button>
                                        <button type="button" wire:click="openEdit({{ $emp['id'] }})"
                                                class="nks-btn nks-btn--primary nks-btn--sm">
                                            <i class="fa-solid fa-pen"></i>
                                            Edit
                                        </button>
                                        <div class="em-more">
                                            <button type="button" class="em-more-btn" aria-label="More actions">
                                                <i class="fa-solid fa-ellipsis-vertical"></i>
                                            </button>
                                            <div class="em-more-menu">
                                                <button type="button" wire:click="openResetPassword({{ $emp['id'] }})"
                                                        class="em-more-item">
                                                    <i class="fa-solid fa-key"></i>
                                                    Reset Password
                                                </button>
                                                @if (!empty($emp['is_active']))
                                                    <button type="button" wire:click="openDeactivate({{ $emp['id'] }})"
                                                            class="em-more-item em-more-item--danger">
                                                        <i class="fa-solid fa-user-slash"></i>
                                                        Deactivate
                                                    </button>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (($pagination['last_page'] ?? 1) > 1)
                <div class="em-pagination">
                    <button type="button" wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] <= 1) disabled @endif>
                        <i class="fa-solid fa-chevron-left"></i>
                        Previous
                    </button>
                    <span class="em-page-info">
                        Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                        ({{ $pagination['total'] }} total)
                    </span>
                    <button type="button" wire:click="gotoPage({{ min($pagination['last_page'], $pagination['current_page'] + 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] >= $pagination['last_page']) disabled @endif>
                        Next
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            @endif
        @endif
    </div>

    @if ($showView && $viewProfile)
        <div class="em-modal-backdrop" wire:click.self="closeView">
            <div class="em-modal em-modal--lg">
                <header class="em-modal-head">
                    <div>
                        <h3>{{ $viewProfile['full_name'] ?? 'Employee' }}</h3>
                        <span class="em-modal-sub">
                            {{ $viewProfile['employee_number'] ?? '' }} · {{ $viewProfile['email'] ?? '' }}
                        </span>
                    </div>
                    <button type="button" wire:click="closeView" class="em-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="em-modal-body">
                    <nav class="nks-tabs em-view-tabs">
                        <button type="button" wire:click="switchViewTab('profile')"
                                class="nks-tab {{ $viewTab === 'profile' ? 'is-active' : '' }}">
                            Profile
                        </button>
                        <button type="button" wire:click="switchViewTab('leave')"
                                class="nks-tab {{ $viewTab === 'leave' ? 'is-active' : '' }}">
                            Leave Balances
                        </button>
                        <button type="button" wire:click="switchViewTab('contracts')"
                                class="nks-tab {{ $viewTab === 'contracts' ? 'is-active' : '' }}">
                            Contracts
                        </button>
                        <button type="button" wire:click="switchViewTab('activity')"
                                class="nks-tab {{ $viewTab === 'activity' ? 'is-active' : '' }}">
                            History
                        </button>
                    </nav>

                    @if ($viewTab === 'profile')
                        <div class="em-detail-grid">
                            <div><span class="em-detail-label">Full Name</span><span class="em-detail-value">{{ $viewProfile['full_name'] ?? '—' }}</span></div>
                            <div><span class="em-detail-label">Employee Number</span><span class="em-detail-value">{{ $viewProfile['employee_number'] ?? '—' }}</span></div>
                            <div><span class="em-detail-label">Email</span><span class="em-detail-value">{{ $viewProfile['email'] ?? '—' }}</span></div>
                            <div><span class="em-detail-label">Phone</span><span class="em-detail-value">{{ $viewProfile['phone'] ?? '—' }}</span></div>
                            <div><span class="em-detail-label">Role</span><span class="em-detail-value">{{ ucfirst($viewProfile['role'] ?? '—') }}</span></div>
                            <div><span class="em-detail-label">Employee Type</span><span class="em-detail-value">{{ strtoupper($viewProfile['employee_type'] ?? '—') }}</span></div>
                            <div><span class="em-detail-label">Service Type</span><span class="em-detail-value">{{ ucfirst($viewProfile['service_type'] ?? '—') }}</span></div>
                            <div><span class="em-detail-label">Department</span><span class="em-detail-value">{{ $viewProfile['department'] ?? '—' }}</span></div>
                            <div><span class="em-detail-label">Position</span><span class="em-detail-value">{{ $viewProfile['position'] ?? '—' }}</span></div>
                            <div><span class="em-detail-label">Manager</span><span class="em-detail-value">{{ $viewProfile['manager'] ?? '—' }}</span></div>
                            <div><span class="em-detail-label">Client</span><span class="em-detail-value">{{ $viewProfile['client'] ?? '—' }}</span></div>
                            <div><span class="em-detail-label">Project</span><span class="em-detail-value">{{ $viewProfile['project'] ?? '—' }}</span></div>
                            <div><span class="em-detail-label">Hire Date</span><span class="em-detail-value">{{ !empty($viewProfile['hire_date']) ? \Carbon\Carbon::parse($viewProfile['hire_date'])->format('d M Y') : '—' }}</span></div>
                            <div><span class="em-detail-label">Status</span><span class="em-detail-value">
                                @if (!empty($viewProfile['is_active']))
                                    <x-nks.badge tone="success">Active</x-nks.badge>
                                @else
                                    <x-nks.badge tone="muted">Inactive</x-nks.badge>
                                @endif
                            </span></div>
                        </div>
                    @elseif ($viewTab === 'leave')
                        <div class="em-leave-grid">
                            @foreach ([
                                'annual' => 'Annual',
                                'sick' => 'Sick',
                                'family' => 'Family',
                                'unpaid' => 'Unpaid',
                                'study' => 'Study',
                                'maternity' => 'Maternity',
                                'paternity' => 'Paternity',
                            ] as $key => $label)
                                @php
                                    $balance = (float) ($viewProfile['leave_balance_' . $key] ?? 0);
                                @endphp
                                <div class="em-leave-tile">
                                    <div class="em-leave-label">{{ $label }}</div>
                                    <div class="em-leave-value">{{ number_format($balance, 1) }}</div>
                                    <div class="em-leave-sub">days remaining</div>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($viewTab === 'contracts')
                        @if (empty($viewHistory['contracts']))
                            <div class="em-inline-empty">
                                <i class="fa-solid fa-circle-info"></i>
                                No contracts on record.
                            </div>
                        @else
                            <div class="em-inline-table-wrapper">
                                <table class="em-inline-table">
                                    <thead>
                                        <tr>
                                            <th>Version</th>
                                            <th>Type</th>
                                            <th>Effective</th>
                                            <th>Expiry</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($viewHistory['contracts'] as $c)
                                            <tr>
                                                <td>v{{ $c['version'] ?? '—' }}</td>
                                                <td>{{ ucfirst($c['contract_type'] ?? '—') }}</td>
                                                <td>{{ !empty($c['effective_date']) ? \Carbon\Carbon::parse($c['effective_date'])->format('d M Y') : '—' }}</td>
                                                <td>{{ !empty($c['expiry_date']) ? \Carbon\Carbon::parse($c['expiry_date'])->format('d M Y') : '—' }}</td>
                                                <td>
                                                    <x-nks.badge :tone="($c['status'] ?? '') === 'active' ? 'success' : 'muted'">
                                                        {{ ucfirst($c['status'] ?? '—') }}
                                                    </x-nks.badge>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @elseif ($viewTab === 'activity')
                        @if (empty($viewHistory['project_history']))
                            <div class="em-inline-empty">
                                <i class="fa-solid fa-circle-info"></i>
                                No project history.
                            </div>
                        @else
                            <div class="em-inline-table-wrapper">
                                <table class="em-inline-table">
                                    <thead>
                                        <tr>
                                            <th>Project</th>
                                            <th>Role</th>
                                            <th>Assigned</th>
                                            <th>End</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($viewHistory['project_history'] as $ph)
                                            <tr>
                                                <td>{{ $ph['project']['name'] ?? '—' }}</td>
                                                <td>{{ ucfirst($ph['role'] ?? '—') }}</td>
                                                <td>{{ !empty($ph['assigned_date']) ? \Carbon\Carbon::parse($ph['assigned_date'])->format('d M Y') : '—' }}</td>
                                                <td>{{ !empty($ph['end_date']) ? \Carbon\Carbon::parse($ph['end_date'])->format('d M Y') : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </div>
                <footer class="em-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    <button type="button" wire:click="openEdit({{ $viewProfile['id'] ?? 0 }})" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-pen"></i>
                        Edit Employee
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showCreate)
        <div class="em-modal-backdrop" wire:click.self="closeCreate">
            <div class="em-modal em-modal--lg">
                <header class="em-modal-head">
                    <h3>Create Employee</h3>
                    <button type="button" wire:click="closeCreate" class="em-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="em-modal-body">
                    <h4 class="em-form-section">Identity</h4>
                    <div class="em-form-grid">
                        <div class="nks-field">
                            <label class="nks-label">First Name <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.first_name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Last Name <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.last_name" class="nks-input">
                        </div>
                        <div class="nks-field em-form-full">
                            <label class="nks-label">Email <span class="nks-required">*</span></label>
                            <input type="email" wire:model="createForm.email" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">ID Number</label>
                            <input type="text" wire:model="createForm.id_number" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Phone</label>
                            <input type="text" wire:model="createForm.phone" class="nks-input">
                        </div>
                    </div>

                    <h4 class="em-form-section">Employment</h4>
                    <div class="em-form-grid">
                        <div class="nks-field">
                            <label class="nks-label">Employee Type <span class="nks-required">*</span></label>
                            <select wire:model.live="createForm.employee_type" class="nks-select">
                                <option value="ps">Professional Services (PS)</option>
                                <option value="pr">Projects (PR)</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Service Type <span class="nks-required">*</span></label>
                            <select wire:model="createForm.service_type" class="nks-select">
                                <option value="permanent">Permanent</option>
                                <option value="contractor">Contractor</option>
                                <option value="temporary">Temporary</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Role <span class="nks-required">*</span></label>
                            <select wire:model="createForm.role" class="nks-select">
                                <option value="employee">Employee</option>
                                <option value="manager">Manager</option>
                                <option value="director">Director</option>
                                <option value="admin">Admin</option>
                                <option value="recruiter">Recruiter</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Employee Number</label>
                            <input type="text" wire:model="createForm.employee_number" class="nks-input"
                                   placeholder="Leave blank for auto-generated">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Department</label>
                            <input type="text" wire:model="createForm.department" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Position</label>
                            <input type="text" wire:model="createForm.position" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Manager</label>
                            <select wire:model="createForm.manager_id" class="nks-select">
                                <option value="">No manager</option>
                                @foreach ($managers as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Hire Date</label>
                            <input type="date" wire:model="createForm.hire_date" class="nks-input">
                        </div>

                        @if ($createForm['employee_type'] === 'ps')
                            <div class="nks-field">
                                <label class="nks-label">Client <span class="nks-required">*</span></label>
                                <select wire:model="createForm.client_id" class="nks-select">
                                    <option value="">Select client</option>
                                    @foreach ($clients as $c)
                                        <option value="{{ $c['id'] }}">{{ $c['company_name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @else
                            <div class="nks-field">
                                <label class="nks-label">Project <span class="nks-required">*</span></label>
                                <select wire:model="createForm.project_id" class="nks-select">
                                    <option value="">Select project</option>
                                    @foreach ($projects as $p)
                                        <option value="{{ $p['id'] }}">{{ $p['name'] }} ({{ $p['project_code'] }})</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>

                    <h4 class="em-form-section">Contact & Address</h4>
                    <div class="em-form-grid">
                        <div class="nks-field em-form-full">
                            <label class="nks-label">Home Address</label>
                            <textarea wire:model="createForm.address" class="nks-textarea"></textarea>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Emergency Contact Name</label>
                            <input type="text" wire:model="createForm.emergency_contact_name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Emergency Contact Phone</label>
                            <input type="text" wire:model="createForm.emergency_contact_phone" class="nks-input">
                        </div>
                    </div>
                </div>
                <footer class="em-modal-foot">
                    <button type="button" wire:click="closeCreate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitCreate" wire:loading.attr="disabled" wire:target="submitCreate"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-user-plus"></i>
                        Create Employee
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showCredentials && $createdUser)
        <div class="em-modal-backdrop">
            <div class="em-modal">
                <header class="em-modal-head">
                    <h3>Employee Created Successfully</h3>
                </header>
                <div class="em-modal-body">
                    <p class="em-cred-text">
                        {{ $createdUser['full_name'] ?? 'New employee' }} has been created.
                        Share the temporary password below, or send them a password reset email.
                    </p>

                    <div class="em-cred-meta">
                        <div><span class="em-detail-label">Employee Number</span><span class="em-detail-value">{{ $createdUser['employee_number'] ?? '—' }}</span></div>
                        <div><span class="em-detail-label">Email</span><span class="em-detail-value">{{ $createdUser['email'] ?? '—' }}</span></div>
                    </div>

                    <div class="em-cred-password">
                        <label class="em-cred-label">Temporary Password</label>
                        <div class="em-cred-value">
                            <code>{{ $createdPassword }}</code>
                        </div>
                        <div class="em-cred-actions">
                            <button type="button" class="nks-btn nks-btn--outline nks-btn--sm"
                                    onclick="navigator.clipboard.writeText('{{ $createdPassword }}')">
                                <i class="fa-solid fa-copy"></i>
                                Copy Password
                            </button>
                            @if (!$resetEmailSent)
                                <button type="button" wire:click="sendResetEmailFromCredentials"
                                        class="nks-btn nks-btn--primary nks-btn--sm">
                                    <i class="fa-solid fa-envelope"></i>
                                    Send Reset Email
                                </button>
                            @else
                                <span class="em-cred-sent">
                                    <i class="fa-solid fa-circle-check"></i>
                                    Reset email sent
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="em-cred-warning">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>
                            The temporary password will not be shown again after you close this dialog.
                            If the employee does not receive it, use the Reset Password action from the employee list.
                        </span>
                    </div>
                </div>
                <footer class="em-modal-foot">
                    <button type="button" wire:click="closeCredentials" class="nks-btn nks-btn--primary">
                        Done
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showEdit && $editId)
        <div class="em-modal-backdrop" wire:click.self="closeEdit">
            <div class="em-modal em-modal--lg">
                <header class="em-modal-head">
                    <h3>Edit Employee</h3>
                    <button type="button" wire:click="closeEdit" class="em-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="em-modal-body">
                    <div class="em-form-grid">
                        <div class="nks-field">
                            <label class="nks-label">First Name</label>
                            <input type="text" wire:model="editForm.first_name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Last Name</label>
                            <input type="text" wire:model="editForm.last_name" class="nks-input">
                        </div>
                        <div class="nks-field em-form-full">
                            <label class="nks-label">Email</label>
                            <input type="email" wire:model="editForm.email" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">ID Number</label>
                            <input type="text" wire:model="editForm.id_number" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Phone</label>
                            <input type="text" wire:model="editForm.phone" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Employee Type</label>
                            <select wire:model.live="editForm.employee_type" class="nks-select">
                                <option value="ps">PS</option>
                                <option value="pr">PR</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Service Type</label>
                            <select wire:model="editForm.service_type" class="nks-select">
                                <option value="permanent">Permanent</option>
                                <option value="contractor">Contractor</option>
                                <option value="temporary">Temporary</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Role</label>
                            <select wire:model="editForm.role" class="nks-select">
                                <option value="employee">Employee</option>
                                <option value="manager">Manager</option>
                                <option value="director">Director</option>
                                <option value="admin">Admin</option>
                                <option value="recruiter">Recruiter</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Department</label>
                            <input type="text" wire:model="editForm.department" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Position</label>
                            <input type="text" wire:model="editForm.position" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Manager</label>
                            <select wire:model="editForm.manager_id" class="nks-select">
                                <option value="">No manager</option>
                                @foreach ($managers as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Hire Date</label>
                            <input type="date" wire:model="editForm.hire_date" class="nks-input">
                        </div>

                        @if ($editForm['employee_type'] === 'ps')
                            <div class="nks-field">
                                <label class="nks-label">Client</label>
                                <select wire:model="editForm.client_id" class="nks-select">
                                    <option value="">No client</option>
                                    @foreach ($clients as $c)
                                        <option value="{{ $c['id'] }}">{{ $c['company_name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif ($editForm['employee_type'] === 'pr')
                            <div class="nks-field">
                                <label class="nks-label">Project</label>
                                <select wire:model="editForm.project_id" class="nks-select">
                                    <option value="">No project</option>
                                    @foreach ($projects as $p)
                                        <option value="{{ $p['id'] }}">{{ $p['name'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif

                        <div class="nks-field em-form-full">
                            <label class="nks-label">Home Address</label>
                            <textarea wire:model="editForm.address" class="nks-textarea"></textarea>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Emergency Contact Name</label>
                            <input type="text" wire:model="editForm.emergency_contact_name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Emergency Contact Phone</label>
                            <input type="text" wire:model="editForm.emergency_contact_phone" class="nks-input">
                        </div>
                        <div class="nks-field em-form-full">
                            <label class="em-checkbox">
                                <input type="checkbox" wire:model="editForm.is_active">
                                <span>Account is active</span>
                            </label>
                        </div>
                    </div>
                </div>
                <footer class="em-modal-foot">
                    <button type="button" wire:click="closeEdit" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitEdit" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Save Changes
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showResetPassword)
        <div class="em-modal-backdrop" wire:click.self="closeResetPassword">
            <div class="em-modal">
                <header class="em-modal-head">
                    <h3>Reset Password</h3>
                    <button type="button" wire:click="closeResetPassword" class="em-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="em-modal-body">
                    <p class="em-cred-text">
                        Send a password reset link to <strong>{{ $resetEmail }}</strong>.
                        The employee will receive an email with a link to set a new password.
                    </p>
                    @if ($resetSent)
                        <div class="nks-notice" style="background: var(--nks-mint); color: #1F6B45;">
                            <i class="fa-solid fa-circle-check" style="color: #1F6B45;"></i>
                            <div class="nks-notice-body">Reset link sent successfully.</div>
                        </div>
                    @endif
                </div>
                <footer class="em-modal-foot">
                    <button type="button" wire:click="closeResetPassword" class="nks-btn nks-btn--outline">Close</button>
                    @if (!$resetSent)
                        <button type="button" wire:click="submitResetPassword" class="nks-btn nks-btn--primary">
                            <i class="fa-solid fa-paper-plane"></i>
                            Send Reset Email
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif

    @if ($showDeactivate)
        <div class="em-modal-backdrop" wire:click.self="closeDeactivate">
            <div class="em-modal">
                <header class="em-modal-head">
                    <h3>Deactivate Employee</h3>
                    <button type="button" wire:click="closeDeactivate" class="em-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="em-modal-body">
                    <p class="em-cred-text">
                        You are about to deactivate <strong>{{ $deactivateName }}</strong>.
                        They will no longer be able to sign in. Their data is preserved and the account can be reactivated later.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">
                            Enter your admin password to confirm <span class="nks-required">*</span>
                        </label>
                        <input type="password"
                               wire:model="deactivatePassword"
                               class="nks-input"
                               autocomplete="current-password">
                    </div>
                </div>
                <footer class="em-modal-foot">
                    <button type="button" wire:click="closeDeactivate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitDeactivate" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-user-slash"></i>
                        Deactivate
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>