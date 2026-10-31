<div class="page-admin-projects">
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

    <div class="panel ap-panel">
        <div class="ap-header">
            <i class="fa-solid fa-diagram-project ap-header-icon"></i>
            <div>
                <h2>Projects</h2>
                <p>Create and manage client projects, teams, and hours</p>
            </div>
            <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary ap-create-btn">
                <i class="fa-solid fa-plus"></i>
                Create Project
            </button>
        </div>

        <div class="ap-filters">
            <div class="nks-field ap-filter-search">
                <label class="nks-label">Search</label>
                <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                       class="nks-input" placeholder="Name or project code">
            </div>
            <div class="nks-field">
                <label class="nks-label">Status</label>
                <select wire:model="statusFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="planning">Planning</option>
                    <option value="active">Active</option>
                    <option value="on_hold">On Hold</option>
                    <option value="completed">Completed</option>
                    <option value="archived">Archived</option>
                </select>
            </div>
            <div class="nks-field">
                <label class="nks-label">Type</label>
                <select wire:model="typeFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="client">Client</option>
                    <option value="internal">Internal</option>
                    <option value="hybrid">Hybrid</option>
                </select>
            </div>
            <div class="nks-field">
                <label class="nks-label">Client</label>
                <select wire:model="clientFilter" class="nks-select">
                    <option value="">Any</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c['id'] }}">{{ $c['company_name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="ap-filter-actions">
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
            <x-nks.spinner block label="Loading projects…" />
        @elseif (count($projects) === 0)
            <div class="ap-empty">
                <i class="fa-solid fa-diagram-project"></i>
                <strong>No projects found</strong>
                <span>Adjust filters or create your first project.</span>
            </div>
        @else
            <div class="ap-table-wrapper">
                <table class="ap-table">
                    <thead>
                        <tr>
                            <th>Project</th>
                            <th>Client</th>
                            <th>Type</th>
                            <th>Manager</th>
                            <th>Team</th>
                            <th>Budget</th>
                            <th>Status</th>
                            <th style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($projects as $p)
                            <tr wire:key="ap-{{ $p['id'] }}">
                                <td>
                                    <div class="ap-name">{{ $p['name'] ?? '—' }}</div>
                                    <div class="ap-meta">{{ $p['project_code'] ?? '' }}</div>
                                </td>
                                <td>{{ $p['client']['company_name'] ?? '—' }}</td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Admin\Projects::typeTone($p['project_type'] ?? '')">
                                        {{ ucfirst($p['project_type'] ?? '—') }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    @if (!empty($p['project_manager']))
                                        {{ $p['project_manager']['first_name'] ?? '' }}
                                        {{ $p['project_manager']['last_name'] ?? '' }}
                                    @else
                                        <span class="ap-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $p['assignments_count'] ?? 0 }}</td>
                                <td>
                                    {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($p['budgeted_hours'] ?? 0)) }}
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Admin\Projects::statusTone($p['status'] ?? '')">
                                        {{ ucfirst(str_replace('_', ' ', $p['status'] ?? '')) }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    <div class="ap-row-actions">
                                        <button type="button" wire:click="openView({{ $p['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i>
                                            View
                                        </button>
                                        <button type="button" wire:click="openEdit({{ $p['id'] }})"
                                                class="nks-btn nks-btn--primary nks-btn--sm">
                                            <i class="fa-solid fa-pen"></i>
                                            Edit
                                        </button>
                                        @if (($p['status'] ?? '') !== 'archived')
                                            <button type="button"
                                                    wire:click="openArchive({{ $p['id'] }})"
                                                    class="ap-icon-btn ap-icon-btn--danger"
                                                    title="Archive">
                                                <i class="fa-solid fa-box-archive"></i>
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
                <div class="ap-pagination">
                    <button type="button" wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] <= 1) disabled @endif>
                        <i class="fa-solid fa-chevron-left"></i> Previous
                    </button>
                    <span class="ap-page-info">
                        Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                        ({{ $pagination['total'] }} total)
                    </span>
                    <button type="button" wire:click="gotoPage({{ min($pagination['last_page'], $pagination['current_page'] + 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] >= $pagination['last_page']) disabled @endif>
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            @endif
        @endif
    </div>

    @if ($showView && $viewProject)
        @php
            $p = $viewProject['project'] ?? [];
            $team = $viewProject['assigned_employees'] ?? [];
            $budgeted = (float) ($p['budgeted_hours'] ?? 0);
            $logged = (float) ($viewProject['total_hours_logged'] ?? 0);
            $usage = $budgeted > 0 ? min(100, round(($logged / $budgeted) * 100)) : 0;
        @endphp
        <div class="ap-modal-backdrop" wire:click.self="closeView">
            <div class="ap-modal ap-modal--lg">
                <header class="ap-modal-head">
                    <div>
                        <h3>{{ $p['name'] ?? 'Project' }}</h3>
                        <span class="ap-modal-sub">{{ $p['project_code'] ?? '' }} · {{ $p['client']['company_name'] ?? '—' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="ap-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ap-modal-body">
                    <div class="ap-detail-grid">
                        <div><span class="ap-detail-label">Status</span><span class="ap-detail-value">
                            <x-nks.badge :tone="\App\Livewire\Admin\Projects::statusTone($p['status'] ?? '')">
                                {{ ucfirst(str_replace('_', ' ', $p['status'] ?? '')) }}
                            </x-nks.badge>
                        </span></div>
                        <div><span class="ap-detail-label">Type</span><span class="ap-detail-value">{{ ucfirst($p['project_type'] ?? '—') }}</span></div>
                        <div><span class="ap-detail-label">Start</span><span class="ap-detail-value">
                            {{ !empty($p['start_date']) ? \Carbon\Carbon::parse($p['start_date'])->format('d M Y') : '—' }}
                        </span></div>
                        <div><span class="ap-detail-label">End</span><span class="ap-detail-value">
                            {{ !empty($p['end_date']) ? \Carbon\Carbon::parse($p['end_date'])->format('d M Y') : '—' }}
                        </span></div>
                    </div>

                    <div class="ap-budget-block">
                        <div class="ap-budget-head">
                            <span class="ap-budget-label">Budget Usage</span>
                            <span class="ap-budget-value">{{ $usage }}%</span>
                        </div>
                        <div class="ap-budget-bar">
                            <div class="ap-budget-fill" style="width: {{ $usage }}%"></div>
                        </div>
                        <div class="ap-budget-meta">
                            <span><strong>{{ \App\Livewire\Employee\Timesheet::formatHours($logged) }}</strong> logged</span>
                            <span><strong>{{ \App\Livewire\Employee\Timesheet::formatHours((float) ($viewProject['remaining_hours'] ?? 0)) }}</strong> remaining</span>
                            <span>Budget: <strong>{{ \App\Livewire\Employee\Timesheet::formatHours($budgeted) }}</strong></span>
                        </div>
                    </div>

                    <div class="ap-section-head">
                        <div>
                            <h4 class="ap-section-title">Project Manager</h4>
                            @if (!empty($p['project_manager']))
                                <span class="ap-section-value">
                                    {{ $p['project_manager']['first_name'] ?? '' }}
                                    {{ $p['project_manager']['last_name'] ?? '' }}
                                </span>
                            @else
                                <span class="ap-muted">Not assigned</span>
                            @endif
                        </div>
                        <button type="button"
                                wire:click="openAssignManager({{ $p['id'] }}, {{ $p['project_manager_id'] ?? 'null' }})"
                                class="nks-btn nks-btn--outline nks-btn--sm">
                            <i class="fa-solid fa-user-shield"></i>
                            {{ !empty($p['project_manager']) ? 'Change Manager' : 'Assign Manager' }}
                        </button>
                    </div>

                    <div class="ap-section-head">
                        <div>
                            <h4 class="ap-section-title">Team ({{ count($team) }})</h4>
                        </div>
                        <button type="button"
                                wire:click="openAssignEmployee({{ $p['id'] }})"
                                class="nks-btn nks-btn--primary nks-btn--sm">
                            <i class="fa-solid fa-user-plus"></i>
                            Assign Employee
                        </button>
                    </div>

                    @if (count($team) === 0)
                        <div class="ap-inline-empty">
                            <i class="fa-solid fa-users-slash"></i>
                            No active team members. Assign employees to start tracking hours against this project.
                        </div>
                    @else
                        <div class="ap-team-wrapper">
                            <table class="ap-team-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Role</th>
                                        <th>Allocation</th>
                                        <th>Since</th>
                                        <th style="width: 60px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($team as $row)
                                        @php
                                            $u = $row['user'] ?? [];
                                            $fullName = trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''));
                                            $pct = (float) ($row['allocation'] ?? 0);
                                        @endphp
                                        <tr wire:key="team-{{ $u['id'] ?? loop.index }}">
                                            <td>
                                                <div class="ap-team-name">{{ $fullName ?: '—' }}</div>
                                                <div class="ap-meta">{{ $u['employee_number'] ?? '' }}</div>
                                            </td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $row['role'] ?? '—')) }}</td>
                                            <td>
                                                <x-nks.badge :tone="\App\Livewire\Admin\Projects::allocationTone($pct)">
                                                    {{ (int) $pct }}%
                                                </x-nks.badge>
                                            </td>
                                            <td>
                                                {{ !empty($row['assigned_date']) ? \Carbon\Carbon::parse($row['assigned_date'])->format('d M Y') : '—' }}
                                            </td>
                                            <td>
                                                <button type="button"
                                                        wire:click="openRemoveEmployee({{ $p['id'] }}, {{ $u['id'] ?? 0 }}, '{{ addslashes($fullName) }}')"
                                                        class="ap-icon-btn ap-icon-btn--danger"
                                                        title="Remove">
                                                    <i class="fa-solid fa-user-minus"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <div class="ap-section-head">
                        <div>
                            <h4 class="ap-section-title">Hours Breakdown</h4>
                        </div>
                        @if (!$hoursData)
                            <button type="button"
                                    wire:click="loadHours"
                                    wire:loading.attr="disabled"
                                    wire:target="loadHours"
                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                <i class="fa-solid fa-clock"></i>
                                Load Hours
                            </button>
                        @endif
                    </div>

                    @if ($hoursLoading)
                        <x-nks.spinner label="Loading hours…" />
                    @elseif ($hoursData && isset($hoursData['error']))
                        <div class="ap-inline-empty">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            {{ $hoursData['error'] }}
                        </div>
                    @elseif ($hoursData)
                        @php
                            $byEmp = $hoursData['hours_by_employee'] ?? [];
                            $byMonth = $hoursData['hours_by_month'] ?? [];
                        @endphp

                        <div class="ap-hours-grid">
                            <div>
                                <div class="ap-hours-title">By Employee</div>
                                @if (empty($byEmp))
                                    <div class="ap-inline-empty"><i class="fa-solid fa-circle-info"></i>No hours logged yet.</div>
                                @else
                                    <table class="ap-team-table">
                                        <thead>
                                            <tr>
                                                <th>Employee</th>
                                                <th style="text-align:right;">Hours</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($byEmp as $row)
                                                <tr>
                                                    <td>{{ $row['user']['first_name'] ?? '' }} {{ $row['user']['last_name'] ?? '' }}</td>
                                                    <td style="text-align:right;">
                                                        {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($row['total_hours'] ?? 0)) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                            <div>
                                <div class="ap-hours-title">By Month</div>
                                @if (empty($byMonth))
                                    <div class="ap-inline-empty"><i class="fa-solid fa-circle-info"></i>No monthly data.</div>
                                @else
                                    <table class="ap-team-table">
                                        <thead>
                                            <tr>
                                                <th>Month</th>
                                                <th style="text-align:right;">Hours</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($byMonth as $row)
                                                <tr>
                                                    <td>{{ $row['month'] ?? '—' }}</td>
                                                    <td style="text-align:right;">
                                                        {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($row['total_hours'] ?? 0)) }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                <footer class="ap-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    <button type="button" wire:click="openEdit({{ $p['id'] }})" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-pen"></i>
                        Edit Project
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showCreate)
        <div class="ap-modal-backdrop" wire:click.self="closeCreate">
            <div class="ap-modal ap-modal--lg">
                <header class="ap-modal-head">
                    <h3>Create Project</h3>
                    <button type="button" wire:click="closeCreate" class="ap-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ap-modal-body">
                    <div class="ap-form-grid">
                        <div class="nks-field">
                            <label class="nks-label">Client <span class="nks-required">*</span></label>
                            <select wire:model="createForm.client_id" class="nks-select">
                                <option value="">Select client</option>
                                @foreach ($clients as $c)
                                    <option value="{{ $c['id'] }}">{{ $c['company_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Project Code <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.project_code" class="nks-input"
                                   placeholder="e.g. NKS-2026-001">
                        </div>
                        <div class="nks-field ap-form-full">
                            <label class="nks-label">Name <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Type <span class="nks-required">*</span></label>
                            <select wire:model="createForm.project_type" class="nks-select">
                                <option value="client">Client</option>
                                <option value="internal">Internal</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Project Manager</label>
                            <select wire:model="createForm.project_manager_id" class="nks-select">
                                <option value="">Assign later</option>
                                @foreach ($managers as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Budgeted Hours</label>
                            <input type="number" wire:model="createForm.budgeted_hours" class="nks-input" min="0" step="0.5">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Hourly Rate (R)</label>
                            <input type="number" wire:model="createForm.hourly_rate" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Start Date</label>
                            <input type="date" wire:model="createForm.start_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">End Date</label>
                            <input type="date" wire:model="createForm.end_date" class="nks-input">
                        </div>
                        <div class="nks-field ap-form-full">
                            <label class="nks-label">Description</label>
                            <textarea wire:model="createForm.description" class="nks-textarea"></textarea>
                        </div>
                    </div>
                </div>
                <footer class="ap-modal-foot">
                    <button type="button" wire:click="closeCreate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitCreate" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Create Project
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showEdit && $editId)
        <div class="ap-modal-backdrop" wire:click.self="closeEdit">
            <div class="ap-modal ap-modal--lg">
                <header class="ap-modal-head">
                    <h3>Edit Project</h3>
                    <button type="button" wire:click="closeEdit" class="ap-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ap-modal-body">
                    <div class="ap-form-grid">
                        <div class="nks-field">
                            <label class="nks-label">Client</label>
                            <select wire:model="editForm.client_id" class="nks-select">
                                @foreach ($clients as $c)
                                    <option value="{{ $c['id'] }}">{{ $c['company_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Project Code</label>
                            <input type="text" wire:model="editForm.project_code" class="nks-input">
                        </div>
                        <div class="nks-field ap-form-full">
                            <label class="nks-label">Name</label>
                            <input type="text" wire:model="editForm.name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Type</label>
                            <select wire:model="editForm.project_type" class="nks-select">
                                <option value="client">Client</option>
                                <option value="internal">Internal</option>
                                <option value="hybrid">Hybrid</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Status</label>
                            <select wire:model="editForm.status" class="nks-select">
                                <option value="planning">Planning</option>
                                <option value="active">Active</option>
                                <option value="on_hold">On Hold</option>
                                <option value="completed">Completed</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Project Manager</label>
                            <select wire:model="editForm.project_manager_id" class="nks-select">
                                <option value="">Unassigned</option>
                                @foreach ($managers as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Budgeted Hours</label>
                            <input type="number" wire:model="editForm.budgeted_hours" class="nks-input" min="0" step="0.5">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Hourly Rate (R)</label>
                            <input type="number" wire:model="editForm.hourly_rate" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Start Date</label>
                            <input type="date" wire:model="editForm.start_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">End Date</label>
                            <input type="date" wire:model="editForm.end_date" class="nks-input">
                        </div>
                        <div class="nks-field ap-form-full">
                            <label class="nks-label">Description</label>
                            <textarea wire:model="editForm.description" class="nks-textarea"></textarea>
                        </div>
                    </div>
                </div>
                <footer class="ap-modal-foot">
                    <button type="button" wire:click="closeEdit" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitEdit" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Save Changes
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showAssignManager)
        <div class="ap-modal-backdrop" wire:click.self="closeAssignManager">
            <div class="ap-modal">
                <header class="ap-modal-head">
                    <h3>Assign Project Manager</h3>
                    <button type="button" wire:click="closeAssignManager" class="ap-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ap-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Manager <span class="nks-required">*</span></label>
                        <select wire:model="assignManagerId" class="nks-select">
                            <option value="">Select a manager</option>
                            @foreach ($managers as $m)
                                <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }} — {{ ucfirst($m['role']) }}</option>
                            @endforeach
                        </select>
                        <span class="nks-help">The assigned manager becomes the L1 approver for PR timesheets on this project.</span>
                    </div>
                </div>
                <footer class="ap-modal-foot">
                    <button type="button" wire:click="closeAssignManager" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitAssignManager" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Assign
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showAssignEmployee)
        <div class="ap-modal-backdrop" wire:click.self="closeAssignEmployee">
            <div class="ap-modal">
                <header class="ap-modal-head">
                    <h3>Assign Employee</h3>
                    <button type="button" wire:click="closeAssignEmployee" class="ap-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ap-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Employee <span class="nks-required">*</span></label>
                        <select wire:model="assignEmpForm.user_id" class="nks-select">
                            <option value="">Select an employee</option>
                            @foreach ($assignableUsers as $u)
                                <option value="{{ $u['id'] }}">{{ $u['first_name'] }} {{ $u['last_name'] }} — {{ $u['employee_number'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Project Role</label>
                        <select wire:model="assignEmpForm.role" class="nks-select">
                            <option value="team_member">Team Member</option>
                            <option value="lead">Lead</option>
                            <option value="senior">Senior</option>
                            <option value="junior">Junior</option>
                        </select>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Allocation %</label>
                        <input type="number" wire:model="assignEmpForm.allocation_percentage" class="nks-input" min="1" max="100">
                    </div>
                    <div class="ap-form-row">
                        <div class="nks-field">
                            <label class="nks-label">Assigned Date</label>
                            <input type="date" wire:model="assignEmpForm.assigned_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Until (optional)</label>
                            <input type="date" wire:model="assignEmpForm.end_date" class="nks-input">
                        </div>
                    </div>
                </div>
                <footer class="ap-modal-foot">
                    <button type="button" wire:click="closeAssignEmployee" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitAssignEmployee" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-user-plus"></i>
                        Assign
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showRemoveEmployee)
        <div class="ap-modal-backdrop" wire:click.self="closeRemoveEmployee">
            <div class="ap-modal">
                <header class="ap-modal-head">
                    <h3>Remove Employee</h3>
                    <button type="button" wire:click="closeRemoveEmployee" class="ap-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ap-modal-body">
                    <p class="ap-text">
                        Remove <strong>{{ $removeName }}</strong> from this project?
                        Their historical timesheet entries stay attached to the project.
                    </p>
                </div>
                <footer class="ap-modal-foot">
                    <button type="button" wire:click="closeRemoveEmployee" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitRemoveEmployee" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-user-minus"></i>
                        Remove
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showArchive)
        <div class="ap-modal-backdrop" wire:click.self="closeArchive">
            <div class="ap-modal">
                <header class="ap-modal-head">
                    <h3>Archive Project</h3>
                    <button type="button" wire:click="closeArchive" class="ap-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ap-modal-body">
                    <p class="ap-text">
                        Archive <strong>{{ $archiveName }}</strong>?
                        The project remains visible in historical reports but cannot accept new timesheets or assignments.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">
                            Enter your admin password to confirm <span class="nks-required">*</span>
                        </label>
                        <input type="password" wire:model="archivePassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="ap-modal-foot">
                    <button type="button" wire:click="closeArchive" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitArchive" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-box-archive"></i>
                        Archive
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>