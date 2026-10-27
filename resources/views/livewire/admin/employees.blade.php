<div class="page-admin-employees">
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

    <div class="panel emp-panel">
        <div class="emp-header">
            <i class="fa-solid fa-users-gear emp-header-icon"></i>
            <div>
                <h2>Employees</h2>
                <p>Manage employee accounts, roles, and assignments</p>
            </div>
            <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary emp-create-btn">
                <i class="fa-solid fa-user-plus"></i>
                New Employee
            </button>
        </div>

        <div class="emp-toolbar">
            <div class="emp-toolbar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text"
                       wire:model.live.debounce.500ms="search"
                       class="emp-search-input"
                       placeholder="Search by name, email, employee number">
            </div>

            <select wire:model.live="roleFilter" class="emp-select">
                <option value="">All roles</option>
                <option value="employee">Employee</option>
                <option value="manager">Manager</option>
                <option value="director">Director</option>
                <option value="admin">Admin</option>
                <option value="recruiter">Recruiter</option>
            </select>

            <select wire:model.live="typeFilter" class="emp-select">
                <option value="">All types</option>
                <option value="ps">PS</option>
                <option value="pr">PR</option>
            </select>

            <select wire:model.live="statusFilter" class="emp-select">
                <option value="">All status</option>
                <option value="1">Active</option>
                <option value="0">Inactive</option>
            </select>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading employees…" />
        @elseif (count($employees) === 0)
            <div class="emp-empty">
                <i class="fa-solid fa-users-slash"></i>
                <strong>No employees found</strong>
                <span>Try adjusting your filters or create your first employee.</span>
            </div>
        @else
            <div class="emp-table-wrapper">
                <table class="emp-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Role</th>
                            <th>Type</th>
                            <th>Department</th>
                            <th>Manager</th>
                            <th>Status</th>
                            <th style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $e)
                            @php
                                $fullName = trim(($e['first_name'] ?? '') . ' ' . ($e['last_name'] ?? ''));
                                $active = (bool) ($e['is_active'] ?? false);
                            @endphp
                            <tr wire:key="emp-{{ $e['id'] }}">
                                <td>
                                    <div class="emp-person">
                                        <div class="emp-person-avatar">
                                            <i class="fa-solid fa-user"></i>
                                        </div>
                                        <div>
                                            <div class="emp-person-name">{{ $fullName ?: '—' }}</div>
                                            <div class="emp-person-meta">
                                                {{ $e['employee_number'] ?? '' }}
                                                @if (!empty($e['email']))
                                                    · {{ $e['email'] }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Admin\Employees::roleTone($e['role'] ?? '')">
                                        {{ ucfirst($e['role'] ?? '—') }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    @if (!empty($e['employee_type']))
                                        <x-nks.badge :tone="\App\Livewire\Admin\Employees::typeTone($e['employee_type'])">
                                            {{ strtoupper($e['employee_type']) }}
                                        </x-nks.badge>
                                    @else
                                        <span class="emp-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $e['department'] ?? '—' }}</td>
                                <td>
                                    @if (!empty($e['manager']['first_name']))
                                        {{ $e['manager']['first_name'] }} {{ $e['manager']['last_name'] ?? '' }}
                                    @else
                                        <span class="emp-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($active)
                                        <x-nks.badge tone="success">Active</x-nks.badge>
                                    @else
                                        <x-nks.badge tone="red">Inactive</x-nks.badge>
                                    @endif
                                </td>
                                <td>
                                    <div class="emp-actions">
                                        <button type="button"
                                                wire:click="openEdit({{ $e['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-pen"></i>
                                            Edit
                                        </button>
                                        @if ($active)
                                            <button type="button"
                                                    wire:click="openDeactivate({{ $e['id'] }}, '{{ addslashes($fullName) }}')"
                                                    class="nks-btn nks-btn--danger nks-btn--sm">
                                                <i class="fa-solid fa-user-slash"></i>
                                                Deactivate
                                            </button>
                                        @else
                                            <button type="button"
                                                    wire:click="reactivate({{ $e['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-user-check"></i>
                                                Reactivate
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (($pagination['last_page'] ?? 1) > 1)
                <div class="emp-pagination">
                    <button type="button"
                            wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] <= 1) disabled @endif>
                        <i class="fa-solid fa-chevron-left"></i>
                        Previous
                    </button>
                    <span class="emp-page-info">
                        Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                        ({{ $pagination['total'] }} total)
                    </span>
                    <button type="button"
                            wire:click="gotoPage({{ min($pagination['last_page'], $pagination['current_page'] + 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] >= $pagination['last_page']) disabled @endif>
                        Next
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            @endif
        @endif
    </div>

    @if ($showForm)
        <div class="emp-modal-backdrop" wire:click.self="closeForm">
            <div class="emp-modal emp-modal--lg">
                <header class="emp-modal-head">
                    <h3>{{ $editing ? 'Edit Employee' : 'Create Employee' }}</h3>
                    <button type="button" wire:click="closeForm" class="emp-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="emp-modal-body">
                    <div class="emp-section">
                        <h4 class="emp-section-title">Identity</h4>
                        <div class="emp-form-grid">
                            <div class="nks-field">
                                <label class="nks-label">First Name <span class="nks-required">*</span></label>
                                <input type="text" wire:model="form.first_name" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Last Name <span class="nks-required">*</span></label>
                                <input type="text" wire:model="form.last_name" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Email <span class="nks-required">*</span></label>
                                <input type="email" wire:model="form.email" class="nks-input" @if($editing) disabled @endif>
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Phone</label>
                                <input type="text" wire:model="form.phone" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">ID Number</label>
                                <input type="text" wire:model="form.id_number" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Hire Date</label>
                                <input type="date" wire:model="form.hire_date" class="nks-input">
                            </div>
                        </div>
                    </div>

                    <div class="emp-section">
                        <h4 class="emp-section-title">Role & Assignment</h4>
                        <div class="emp-form-grid">
                            <div class="nks-field">
                                <label class="nks-label">Role <span class="nks-required">*</span></label>
                                <select wire:model.live="form.role" class="nks-select">
                                    <option value="employee">Employee</option>
                                    <option value="manager">Manager</option>
                                    <option value="director">Director</option>
                                    <option value="admin">Admin</option>
                                    <option value="recruiter">Recruiter</option>
                                </select>
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Employee Type <span class="nks-required">*</span></label>
                                <select wire:model.live="form.employee_type" class="nks-select">
                                    <option value="ps">PS — Professional Services</option>
                                    <option value="pr">PR — Projects</option>
                                </select>
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Service Type <span class="nks-required">*</span></label>
                                <select wire:model="form.service_type" class="nks-select">
                                    <option value="permanent">Permanent</option>
                                    <option value="contractor">Contractor</option>
                                    <option value="temporary">Temporary</option>
                                    <option value="intern">Intern</option>
                                </select>
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Manager</label>
                                <select wire:model="form.manager_id" class="nks-select">
                                    <option value="">No manager</option>
                                    @foreach ($managers as $m)
                                        <option value="{{ $m['id'] }}">
                                            {{ $m['first_name'] }} {{ $m['last_name'] }} — {{ ucfirst($m['role']) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Department</label>
                                <input type="text" wire:model="form.department" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Position</label>
                                <input type="text" wire:model="form.position" class="nks-input">
                            </div>
                            @if ($form['employee_type'] === 'ps')
                                <div class="nks-field">
                                    <label class="nks-label">Client <span class="nks-required">*</span></label>
                                    <select wire:model="form.client_id" class="nks-select">
                                        <option value="">Select a client</option>
                                        @foreach ($clients as $c)
                                            <option value="{{ $c['id'] }}">{{ $c['company_name'] }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                            @if ($form['employee_type'] === 'pr')
                                <div class="nks-field">
                                    <label class="nks-label">Project <span class="nks-required">*</span></label>
                                    <select wire:model="form.project_id" class="nks-select">
                                        <option value="">Select a project</option>
                                        @foreach ($projects as $p)
                                            <option value="{{ $p['id'] }}">
                                                {{ $p['name'] }} — {{ $p['project_code'] }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="emp-section">
                        <h4 class="emp-section-title">Emergency Contact</h4>
                        <div class="emp-form-grid">
                            <div class="nks-field">
                                <label class="nks-label">Name</label>
                                <input type="text" wire:model="form.emergency_contact_name" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Phone</label>
                                <input type="text" wire:model="form.emergency_contact_phone" class="nks-input">
                            </div>
                            <div class="nks-field emp-form-full">
                                <label class="nks-label">Address</label>
                                <textarea wire:model="form.address" class="nks-textarea"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <footer class="emp-modal-foot">
                    <button type="button" wire:click="closeForm" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="save"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        {{ $editing ? 'Save Changes' : 'Create Employee' }}
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showDeactivate)
        <div class="emp-modal-backdrop" wire:click.self="closeDeactivate">
            <div class="emp-modal">
                <header class="emp-modal-head">
                    <h3>Deactivate Employee</h3>
                    <button type="button" wire:click="closeDeactivate" class="emp-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="emp-modal-body">
                    <p class="emp-deactivate-text">
                        Deactivate <strong>{{ $deactivateName }}</strong>?
                        Their login will stop working immediately, and their termination date will be set to today.
                        Historical data (timesheets, leave, contracts) is preserved.
                    </p>
                </div>
                <footer class="emp-modal-foot">
                    <button type="button" wire:click="closeDeactivate" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitDeactivate" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-user-slash"></i>
                        Confirm Deactivate
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showSuccess)
        <div class="emp-modal-backdrop" wire:click.self="closeSuccess">
            <div class="emp-modal">
                <header class="emp-modal-head">
                    <h3>Employee Created</h3>
                    <button type="button" wire:click="closeSuccess" class="emp-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="emp-modal-body">
                    <div class="emp-success-notice">
                        <i class="fa-solid fa-circle-check"></i>
                        <div>
                            <strong>{{ $successData['name'] ?? '' }} created successfully.</strong>
                            <span>Share the temporary password with them securely — it will not be shown again.</span>
                        </div>
                    </div>

                    <dl class="emp-success-dl">
                        <div>
                            <dt>Employee Number</dt>
                            <dd>{{ $successData['employee_number'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Email</dt>
                            <dd>{{ $successData['email'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Temporary Password</dt>
                            <dd><code class="emp-password">{{ $successData['temporary_password'] ?? '—' }}</code></dd>
                        </div>
                    </dl>
                </div>
                <footer class="emp-modal-foot">
                    <button type="button" wire:click="closeSuccess" class="nks-btn nks-btn--primary">
                        Done
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>