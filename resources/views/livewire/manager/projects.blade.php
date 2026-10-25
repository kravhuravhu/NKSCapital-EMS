<div class="page-manager-projects">
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

    @if ($loading)
        <x-nks.spinner block label="Loading projects…" />
    @else
        <div class="panel mp-panel">
            <div class="mp-header">
                <i class="fa-solid fa-diagram-project mp-header-icon"></i>
                <div>
                    <h2>Projects</h2>
                    <p>Manage your project teams and assignments</p>
                </div>
                <div class="mp-header-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text"
                           wire:model.live.debounce.500ms="search"
                           class="mp-search-input"
                           placeholder="Search by name or code">
                </div>
            </div>

            <div class="mp-toolbar">
                <nav class="nks-tabs mp-tabs">
                    <button type="button"
                            wire:click="selectTab('my')"
                            class="nks-tab {{ $tab === 'my' ? 'is-active' : '' }}">
                        My Projects
                    </button>
                    <button type="button"
                            wire:click="selectTab('all')"
                            class="nks-tab {{ $tab === 'all' ? 'is-active' : '' }}">
                        All Projects
                    </button>
                </nav>

                <select wire:model.live="statusFilter" class="mp-select">
                    <option value="">All statuses</option>
                    <option value="planning">Planning</option>
                    <option value="active">Active</option>
                    <option value="on_hold">On Hold</option>
                    <option value="completed">Completed</option>
                    <option value="archived">Archived</option>
                </select>
            </div>

            @if (count($projects) === 0)
                <div class="mp-empty">
                    <i class="fa-solid fa-diagram-project"></i>
                    <strong>
                        @if ($tab === 'my')
                            No projects assigned to you
                        @else
                            No projects found
                        @endif
                    </strong>
                    <span>
                        @if ($tab === 'my')
                            You are not the manager of any projects yet. Ask an admin to assign you as project manager.
                        @else
                            Try adjusting the search or status filter.
                        @endif
                    </span>
                </div>
            @else
                <div class="mp-grid">
                    @foreach ($projects as $project)
                        @php
                            $isManager = (int) ($project['project_manager_id'] ?? 0) === (int) auth()->id();
                        @endphp
                        <article class="mp-card" wire:key="mp-{{ $project['id'] }}">
                            <header class="mp-card-head">
                                <div class="mp-card-icon">
                                    <i class="fa-solid fa-diagram-project"></i>
                                </div>
                                <div class="mp-card-title">
                                    <h3>{{ $project['name'] ?? '—' }}</h3>
                                    <span class="mp-card-code">{{ $project['project_code'] ?? '' }}</span>
                                </div>
                                <x-nks.badge :tone="\App\Livewire\Manager\Projects::statusTone($project['status'] ?? '')">
                                    {{ ucfirst(str_replace('_', ' ', $project['status'] ?? '')) }}
                                </x-nks.badge>
                            </header>

                            <div class="mp-card-body">
                                <div class="mp-card-client">
                                    <i class="fa-solid fa-building"></i>
                                    {{ $project['client']['company_name'] ?? '—' }}
                                </div>

                                <div class="mp-card-badges">
                                    <x-nks.badge :tone="\App\Livewire\Manager\Projects::projectTypeTone($project['project_type'] ?? '')">
                                        {{ ucfirst($project['project_type'] ?? '—') }}
                                    </x-nks.badge>
                                    @if ($isManager)
                                        <x-nks.badge tone="gold">You manage this</x-nks.badge>
                                    @endif
                                </div>

                                <dl class="mp-card-meta">
                                    <div>
                                        <dt>Budget</dt>
                                        <dd>
                                            {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($project['budgeted_hours'] ?? 0)) }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Team</dt>
                                        <dd>{{ $project['assignments_count'] ?? 0 }}</dd>
                                    </div>
                                    <div>
                                        <dt>Start</dt>
                                        <dd>
                                            {{ !empty($project['start_date']) ? \Carbon\Carbon::parse($project['start_date'])->format('d M Y') : '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>End</dt>
                                        <dd>
                                            {{ !empty($project['end_date']) ? \Carbon\Carbon::parse($project['end_date'])->format('d M Y') : '—' }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>

                            <footer class="mp-card-foot">
                                <button type="button"
                                        wire:click="openDetail({{ $project['id'] }})"
                                        class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-eye"></i>
                                    View & Manage
                                </button>
                            </footer>
                        </article>
                    @endforeach
                </div>

                @if (($pagination['last_page'] ?? 1) > 1)
                    <div class="mp-pagination">
                        <span class="mp-page-info">
                            Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                            ({{ $pagination['total'] }} total)
                        </span>
                    </div>
                @endif
            @endif
        </div>
    @endif

    @if ($showDetail && $detail)
        @php
            $project = $detail['project'] ?? [];
            $assigned = $detail['assigned_employees'] ?? [];
            $isProjectManager = (int) ($project['project_manager_id'] ?? 0) === (int) auth()->id();
            $budgeted = (float) ($project['budgeted_hours'] ?? 0);
            $logged = (float) ($detail['total_hours_logged'] ?? 0);
            $usage = $budgeted > 0 ? min(100, round(($logged / $budgeted) * 100)) : 0;
        @endphp
        <div class="mp-modal-backdrop" wire:click.self="closeDetail">
            <div class="mp-modal mp-modal--lg">
                <header class="mp-modal-head">
                    <div>
                        <h3>{{ $project['name'] ?? 'Project' }}</h3>
                        <span class="mp-modal-sub">{{ $project['project_code'] ?? '' }}</span>
                    </div>
                    <button type="button" wire:click="closeDetail" class="mp-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="mp-modal-body">
                    <div class="mp-detail-meta">
                        <div>
                            <span class="mp-detail-label">Client</span>
                            <span class="mp-detail-value">{{ $project['client']['company_name'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="mp-detail-label">Type</span>
                            <span class="mp-detail-value">{{ ucfirst($project['project_type'] ?? '—') }}</span>
                        </div>
                        <div>
                            <span class="mp-detail-label">Status</span>
                            <span class="mp-detail-value">
                                <x-nks.badge :tone="\App\Livewire\Manager\Projects::statusTone($project['status'] ?? '')">
                                    {{ ucfirst(str_replace('_', ' ', $project['status'] ?? '')) }}
                                </x-nks.badge>
                            </span>
                        </div>
                        <div>
                            <span class="mp-detail-label">Project Manager</span>
                            <span class="mp-detail-value">
                                {{ $project['project_manager']['first_name'] ?? '—' }}
                                {{ $project['project_manager']['last_name'] ?? '' }}
                            </span>
                        </div>
                    </div>

                    <div class="mp-budget-block">
                        <div class="mp-budget-head">
                            <span class="mp-budget-label">Budget Usage</span>
                            <span class="mp-budget-value">{{ $usage }}%</span>
                        </div>
                        <div class="mp-budget-bar">
                            <div class="mp-budget-fill" style="width: {{ $usage }}%"></div>
                        </div>
                        <div class="mp-budget-meta">
                            <span>
                                <strong>{{ \App\Livewire\Employee\Timesheet::formatHours($logged) }}</strong> logged
                            </span>
                            <span>
                                <strong>{{ \App\Livewire\Employee\Timesheet::formatHours((float) ($detail['remaining_hours'] ?? 0)) }}</strong> remaining
                            </span>
                            <span>
                                Budget: <strong>{{ \App\Livewire\Employee\Timesheet::formatHours($budgeted) }}</strong>
                            </span>
                        </div>
                    </div>

                    <div class="mp-assignments-head">
                        <h4>Assigned Team ({{ count($assigned) }})</h4>
                        @if ($isProjectManager)
                            <button type="button"
                                    wire:click="openAssign"
                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                <i class="fa-solid fa-user-plus"></i>
                                Assign Employee
                            </button>
                        @endif
                    </div>

                    @if (count($assigned) === 0)
                        <div class="mp-detail-empty">
                            <i class="fa-solid fa-users-slash"></i>
                            <span>No employees assigned yet.</span>
                        </div>
                    @else
                        <div class="mp-assign-table-wrapper">
                            <table class="mp-assign-table">
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
                                    @foreach ($assigned as $row)
                                        @php
                                            $user = $row['user'] ?? [];
                                            $allocation = (float) ($row['allocation'] ?? 0);
                                            $fullName = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
                                        @endphp
                                        <tr wire:key="mp-assign-{{ $user['id'] ?? loop.index }}">
                                            <td>
                                                <div class="mp-assign-name">{{ $fullName ?: '—' }}</div>
                                                <div class="mp-assign-meta">
                                                    {{ $user['employee_number'] ?? '' }}
                                                    @if (!empty($user['department']))
                                                        · {{ $user['department'] }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $row['role'] ?? '—')) }}</td>
                                            <td>
                                                <x-nks.badge :tone="\App\Livewire\Manager\Projects::allocationTone($allocation)">
                                                    {{ (int) $allocation }}%
                                                </x-nks.badge>
                                            </td>
                                            <td>
                                                {{ !empty($row['assigned_date']) ? \Carbon\Carbon::parse($row['assigned_date'])->format('d M Y') : '—' }}
                                            </td>
                                            <td>
                                                @if ($isProjectManager)
                                                    <button type="button"
                                                            wire:click="openRemove({{ $user['id'] ?? 0 }}, '{{ addslashes($fullName) }}')"
                                                            class="mp-remove-btn"
                                                            title="Remove from project">
                                                        <i class="fa-solid fa-user-minus"></i>
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <footer class="mp-modal-foot">
                    <button type="button" wire:click="closeDetail" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showAssign)
        <div class="mp-modal-backdrop" wire:click.self="closeAssign">
            <div class="mp-modal">
                <header class="mp-modal-head">
                    <h3>Assign Employee</h3>
                    <button type="button" wire:click="closeAssign" class="mp-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="mp-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">
                            Employee <span class="nks-required">*</span>
                        </label>
                        <select wire:model="assignForm.user_id" class="nks-select">
                            <option value="">Select an employee</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp['id'] }}">
                                    {{ $emp['first_name'] }} {{ $emp['last_name'] }}
                                    @if (!empty($emp['employee_number']))
                                        — {{ $emp['employee_number'] }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                        @if (count($employees) === 0)
                            <span class="nks-help">
                                @if ($allEmployeesAssigned)
                                    All eligible employees are already assigned to this project.
                                @else
                                    No employees with the employee role found. Ask an admin to create employees first.
                                @endif
                            </span>
                        @endif
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Project Role</label>
                        <select wire:model="assignForm.role" class="nks-select">
                            <option value="team_member">Team Member</option>
                            <option value="lead">Lead</option>
                            <option value="senior">Senior</option>
                            <option value="junior">Junior</option>
                        </select>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Allocation %</label>
                        <input type="number"
                               wire:model="assignForm.allocation_percentage"
                               min="1"
                               max="100"
                               class="nks-input">
                        <span class="nks-help">1-100% of the employee's working time.</span>
                    </div>

                    <div class="mp-form-row">
                        <div class="nks-field">
                            <label class="nks-label">
                                Assigned From <span class="nks-required">*</span>
                            </label>
                            <input type="date"
                                   wire:model="assignForm.assigned_date"
                                   class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Until (optional)</label>
                            <input type="date"
                                   wire:model="assignForm.end_date"
                                   class="nks-input">
                        </div>
                    </div>
                </div>
                <footer class="mp-modal-foot">
                    <button type="button" wire:click="closeAssign" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitAssign" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-user-plus"></i>
                        Assign
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showRemove)
        <div class="mp-modal-backdrop" wire:click.self="closeRemove">
            <div class="mp-modal">
                <header class="mp-modal-head">
                    <h3>Remove from Project</h3>
                    <button type="button" wire:click="closeRemove" class="mp-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="mp-modal-body">
                    <p class="mp-remove-text">
                        Remove <strong>{{ $removeName }}</strong> from this project?
                        Their historical timesheet entries will stay attached to the project.
                    </p>
                </div>
                <footer class="mp-modal-foot">
                    <button type="button" wire:click="closeRemove" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitRemove" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-user-minus"></i>
                        Remove
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>