<div class="page-admin-projects">
    @if ($error)
        <div class="nks-notice" style="background: rgba(194, 91, 91, 0.1); color: var(--nks-danger);">
            <i class="fa-solid fa-circle-exclamation" style="color: var(--nks-danger);"></i>
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
        <x-nks.spinner block label="Loading projects..." />
    @else
        <div class="ap-stats">
            <div class="ap-stat">
                <div class="ap-stat-icon"><i class="fa-solid fa-diagram-project" aria-hidden="true"></i></div>
                <div>
                    <div class="ap-stat-label">Total</div>
                    <div class="ap-stat-value">{{ $stats['total'] }}</div>
                </div>
            </div>
            <div class="ap-stat ap-stat--success">
                <div class="ap-stat-icon"><i class="fa-solid fa-circle-play" aria-hidden="true"></i></div>
                <div>
                    <div class="ap-stat-label">Active</div>
                    <div class="ap-stat-value">{{ $stats['active'] }}</div>
                </div>
            </div>
            <div class="ap-stat ap-stat--info">
                <div class="ap-stat-icon"><i class="fa-solid fa-hourglass-start" aria-hidden="true"></i></div>
                <div>
                    <div class="ap-stat-label">Planning</div>
                    <div class="ap-stat-value">{{ $stats['planning'] }}</div>
                </div>
            </div>
            <div class="ap-stat">
                <div class="ap-stat-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                <div>
                    <div class="ap-stat-label">Completed</div>
                    <div class="ap-stat-value">{{ $stats['completed'] }}</div>
                </div>
            </div>
            <div class="ap-stat ap-stat--gold">
                <div class="ap-stat-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></div>
                <div>
                    <div class="ap-stat-label">Budgeted hours</div>
                    <div class="ap-stat-value">{{ number_format($stats['total_budget'], 0) }}</div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="page-toolbar">
                <div>
                    <h2>Projects</h2>
                    <p>Create and manage client projects, teams, and hours</p>
                </div>
                <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary">
                    <i class="fa-solid fa-plus"></i>
                    Create project
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
                        <option value="on_hold">On hold</option>
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

            @if (count($projects) === 0)
                <div class="nks-card-empty">
                    <i class="fa-solid fa-diagram-project"></i>
                    <p>No projects match your filters.</p>
                </div>
            @else
                <div class="ap-grid">
                    @foreach ($projects as $p)
                        @php
                            $status = $p['status'] ?? 'planning';
                            $type = $p['project_type'] ?? 'client';
                        @endphp
                        <article class="ap-card ap-card--{{ self::statusTone($status) }}">
                            <header class="ap-card-head">
                                <div class="ap-card-icon">
                                    <i class="fa-solid fa-diagram-project" aria-hidden="true"></i>
                                </div>
                                <div class="ap-card-title">
                                    <h3>{{ $p['name'] ?? '—' }}</h3>
                                    <span>{{ $p['project_code'] ?? '' }}</span>
                                </div>
                                <x-nks.badge :tone="self::statusTone($status)">
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </x-nks.badge>
                            </header>

                            <div class="ap-card-client">
                                <i class="fa-solid fa-building" aria-hidden="true"></i>
                                {{ $p['client']['company_name'] ?? '—' }}
                            </div>

                            <div class="ap-card-badges">
                                <x-nks.badge :tone="self::typeTone($type)">
                                    {{ ucfirst($type) }}
                                </x-nks.badge>
                                @if (! empty($p['project_manager']))
                                    <span class="ap-card-manager">
                                        {{ $p['project_manager']['first_name'] ?? '' }}
                                        {{ $p['project_manager']['last_name'] ?? '' }}
                                    </span>
                                @endif
                            </div>

                            <dl class="ap-card-meta">
                                <div>
                                    <dt>Budget</dt>
                                    <dd>{{ \App\Livewire\Employee\Timesheet::formatHours((float) ($p['budgeted_hours'] ?? 0)) }}</dd>
                                </div>
                                <div>
                                    <dt>Team</dt>
                                    <dd>{{ $p['assignments_count'] ?? 0 }}</dd>
                                </div>
                                <div>
                                    <dt>Start</dt>
                                    <dd>{{ ! empty($p['start_date']) ? \Carbon\Carbon::parse($p['start_date'])->format('d M Y') : '—' }}</dd>
                                </div>
                                <div>
                                    <dt>End</dt>
                                    <dd>{{ ! empty($p['end_date']) ? \Carbon\Carbon::parse($p['end_date'])->format('d M Y') : '—' }}</dd>
                                </div>
                            </dl>

                            <footer class="ap-card-foot">
                                <button type="button" wire:click="openView({{ $p['id'] }})" class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-eye"></i>
                                    View
                                </button>
                                <button type="button" wire:click="openEdit({{ $p['id'] }})" class="nks-btn nks-btn--primary nks-btn--sm">
                                    <i class="fa-solid fa-pen"></i>
                                    Edit
                                </button>
                            </footer>
                        </article>
                    @endforeach
                </div>

                @if (($pagination['last_page'] ?? 1) > 1)
                    <div class="ap-pagination">
                        <button type="button" wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                                class="nks-btn nks-btn--outline nks-btn--sm"
                                @if ($pagination['current_page'] <= 1) disabled @endif>
                            <i class="fa-solid fa-chevron-left"></i>
                            Previous
                        </button>
                        <span class="ap-page-info">
                            Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                            &middot; {{ $pagination['total'] }} total
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
    @endif

    {{-- View modal --}}
    @if ($showView && $viewProject)
        @php
            $p = $viewProject['project'] ?? [];
            $team = $viewProject['assigned_employees'] ?? [];
            $budgeted = (float) ($p['budgeted_hours'] ?? 0);
            $logged = (float) ($viewProject['total_hours_logged'] ?? 0);
            $usage = $this->budgetUsage($p, $logged);
        @endphp
        <div class="nks-modal-backdrop" wire:click.self="closeView" @keydown.escape.window="$wire.closeView()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <div>
                        <h3>{{ $p['name'] ?? 'Project' }}</h3>
                        <span class="nks-modal-sub">{{ $p['project_code'] ?? '' }} &middot; {{ $p['client']['company_name'] ?? '—' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="ap-detail-grid">
                        <div><span class="ap-detail-label">Status</span><span class="ap-detail-value">
                            <x-nks.badge :tone="self::statusTone($p['status'] ?? '')">
                                {{ ucfirst(str_replace('_', ' ', $p['status'] ?? '')) }}
                            </x-nks.badge>
                        </span></div>
                        <div><span class="ap-detail-label">Type</span><span class="ap-detail-value">{{ ucfirst($p['project_type'] ?? '—') }}</span></div>
                        <div><span class="ap-detail-label">Start</span><span class="ap-detail-value">
                            {{ ! empty($p['start_date']) ? \Carbon\Carbon::parse($p['start_date'])->format('d M Y') : '—' }}
                        </span></div>
                        <div><span class="ap-detail-label">End</span><span class="ap-detail-value">
                            {{ ! empty($p['end_date']) ? \Carbon\Carbon::parse($p['end_date'])->format('d M Y') : '—' }}
                        </span></div>
                    </div>

                    <div class="ap-budget-block">
                        <div class="ap-budget-head">
                            <span class="ap-budget-label">Budget usage</span>
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
                            <h4 class="ap-section-title">Project manager</h4>
                            @if (! empty($p['project_manager']))
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
                            {{ ! empty($p['project_manager']) ? 'Change' : 'Assign' }}
                        </button>
                    </div>

                    <div class="ap-section-head">
                        <div>
                            <h4 class="ap-section-title">Team ({{ count($team) }})</h4>
                        </div>
                        <button type="button" wire:click="openAssignEmployee({{ $p['id'] }})"
                                class="nks-btn nks-btn--primary nks-btn--sm">
                            <i class="fa-solid fa-user-plus"></i>
                            Assign
                        </button>
                    </div>

                    @if (count($team) === 0)
                        <div class="nks-card-empty" style="padding: 20px 12px;">
                            <i class="fa-solid fa-users-slash"></i>
                            <p>No active team members. Assign employees to start tracking hours.</p>
                        </div>
                    @else
                        <div class="nks-table-wrapper">
                            <table class="nks-table">
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
                                        <tr>
                                            <td>
                                                <strong>{{ $fullName ?: '—' }}</strong>
                                                <div class="ap-muted" style="font-size: 11px; margin-top: 2px;">
                                                    {{ $u['employee_number'] ?? '' }}
                                                </div>
                                            </td>
                                            <td>{{ ucfirst(str_replace('_', ' ', $row['role'] ?? '—')) }}</td>
                                            <td>
                                                <x-nks.badge :tone="self::allocationTone($pct)">
                                                    {{ (int) $pct }}%
                                                </x-nks.badge>
                                            </td>
                                            <td>{{ ! empty($row['assigned_date']) ? \Carbon\Carbon::parse($row['assigned_date'])->format('d M Y') : '—' }}</td>
                                            <td>
                                                <button type="button"
                                                        wire:click="openRemoveEmployee({{ $p['id'] }}, {{ $u['id'] ?? 0 }}, '{{ addslashes($fullName) }}')"
                                                        class="nks-btn nks-btn--danger nks-btn--sm">
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
                            <h4 class="ap-section-title">Hours breakdown</h4>
                        </div>
                        @if (! $hoursData)
                            <button type="button" wire:click="loadHours" wire:loading.attr="disabled" wire:target="loadHours"
                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                <i class="fa-solid fa-clock" wire:loading.remove wire:target="loadHours"></i>
                                <span class="nks-btn-spinner" wire:loading wire:target="loadHours"></span>
                                Load hours
                            </button>
                        @endif
                    </div>

                    @if ($hoursLoading)
                        <x-nks.spinner label="Loading hours..." />
                    @elseif ($hoursData && isset($hoursData['error']))
                        <div class="nks-card-empty" style="padding: 20px 12px;">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <p>{{ $hoursData['error'] }}</p>
                        </div>
                    @elseif ($hoursData)
                        @php
                            $byEmp = $hoursData['hours_by_employee'] ?? [];
                            $byMonth = $hoursData['hours_by_month'] ?? [];
                        @endphp
                        <div class="ap-hours-grid">
                            <div>
                                <div class="ap-hours-title">By employee</div>
                                @if (empty($byEmp))
                                    <div class="ap-muted">No hours logged yet.</div>
                                @else
                                    <table class="nks-table">
                                        <thead><tr><th>Employee</th><th style="text-align: right;">Hours</th></tr></thead>
                                        <tbody>
                                            @foreach ($byEmp as $row)
                                                <tr>
                                                    <td>{{ $row['user']['first_name'] ?? '' }} {{ $row['user']['last_name'] ?? '' }}</td>
                                                    <td style="text-align: right;">{{ \App\Livewire\Employee\Timesheet::formatHours((float) ($row['total_hours'] ?? 0)) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                            <div>
                                <div class="ap-hours-title">By month</div>
                                @if (empty($byMonth))
                                    <div class="ap-muted">No monthly data.</div>
                                @else
                                    <table class="nks-table">
                                        <thead><tr><th>Month</th><th style="text-align: right;">Hours</th></tr></thead>
                                        <tbody>
                                            @foreach ($byMonth as $row)
                                                <tr>
                                                    <td>{{ $row['month'] ?? '—' }}</td>
                                                    <td style="text-align: right;">{{ \App\Livewire\Employee\Timesheet::formatHours((float) ($row['total_hours'] ?? 0)) }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    <button type="button" wire:click="openEdit({{ $p['id'] }})" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-pen"></i>
                        Edit project
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Create modal --}}
    @if ($showCreate)
        <div class="nks-modal-backdrop" wire:click.self="closeCreate" @keydown.escape.window="$wire.closeCreate()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Create project</h3>
                    <button type="button" wire:click="closeCreate" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
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
                            <label class="nks-label">Project code <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.project_code" class="nks-input" placeholder="NKS-2026-001">
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
                            <label class="nks-label">Project manager</label>
                            <select wire:model="createForm.project_manager_id" class="nks-select">
                                <option value="">Assign later</option>
                                @foreach ($managers as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Budgeted hours</label>
                            <input type="number" wire:model="createForm.budgeted_hours" class="nks-input" min="0" step="0.5">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Hourly rate (R)</label>
                            <input type="number" wire:model="createForm.hourly_rate" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Start date</label>
                            <input type="date" wire:model="createForm.start_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">End date</label>
                            <input type="date" wire:model="createForm.end_date" class="nks-input">
                        </div>
                        <div class="nks-field ap-form-full">
                            <label class="nks-label">Description</label>
                            <textarea wire:model="createForm.description" class="nks-textarea"></textarea>
                        </div>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeCreate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitCreate" class="nks-btn nks-btn--primary"
                            wire:loading.attr="disabled" wire:target="submitCreate">
                        <i class="fa-solid fa-check" wire:loading.remove wire:target="submitCreate"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="submitCreate"></span>
                        Create project
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Edit modal --}}
    @if ($showEdit && $editId)
        <div class="nks-modal-backdrop" wire:click.self="closeEdit" @keydown.escape.window="$wire.closeEdit()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Edit project</h3>
                    <button type="button" wire:click="closeEdit" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
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
                            <label class="nks-label">Project code</label>
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
                                <option value="on_hold">On hold</option>
                                <option value="completed">Completed</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Project manager</label>
                            <select wire:model="editForm.project_manager_id" class="nks-select">
                                <option value="">Unassigned</option>
                                @foreach ($managers as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Budgeted hours</label>
                            <input type="number" wire:model="editForm.budgeted_hours" class="nks-input" min="0" step="0.5">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Hourly rate (R)</label>
                            <input type="number" wire:model="editForm.hourly_rate" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Start date</label>
                            <input type="date" wire:model="editForm.start_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">End date</label>
                            <input type="date" wire:model="editForm.end_date" class="nks-input">
                        </div>
                        <div class="nks-field ap-form-full">
                            <label class="nks-label">Description</label>
                            <textarea wire:model="editForm.description" class="nks-textarea"></textarea>
                        </div>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeEdit" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitEdit" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Save changes
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Assign manager --}}
    @if ($showAssignManager)
        <div class="nks-modal-backdrop" wire:click.self="closeAssignManager" @keydown.escape.window="$wire.closeAssignManager()">
            <div class="nks-modal nks-modal--sm" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Assign project manager</h3>
                    <button type="button" wire:click="closeAssignManager" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Manager <span class="nks-required">*</span></label>
                        <select wire:model="assignManagerId" class="nks-select">
                            <option value="">Select a manager</option>
                            @foreach ($managers as $m)
                                <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }} &middot; {{ ucfirst(str_replace('_', ' ', $m['role'])) }}</option>
                            @endforeach
                        </select>
                        <span class="nks-help">The assigned manager becomes the L1 approver for PR timesheets on this project.</span>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeAssignManager" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitAssignManager" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Assign
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Assign employee --}}
    @if ($showAssignEmployee)
        <div class="nks-modal-backdrop" wire:click.self="closeAssignEmployee" @keydown.escape.window="$wire.closeAssignEmployee()">
            <div class="nks-modal nks-modal--md" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Assign employee</h3>
                    <button type="button" wire:click="closeAssignEmployee" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Employee <span class="nks-required">*</span></label>
                        <select wire:model="assignEmpForm.user_id" class="nks-select">
                            <option value="">Select an employee</option>
                            @foreach ($assignableUsers as $u)
                                <option value="{{ $u['id'] }}">{{ $u['first_name'] }} {{ $u['last_name'] }} &middot; {{ $u['employee_number'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Project role</label>
                        <select wire:model="assignEmpForm.role" class="nks-select">
                            <option value="team_member">Team member</option>
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
                            <label class="nks-label">Assigned date</label>
                            <input type="date" wire:model="assignEmpForm.assigned_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Until (optional)</label>
                            <input type="date" wire:model="assignEmpForm.end_date" class="nks-input">
                        </div>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeAssignEmployee" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitAssignEmployee" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-user-plus"></i>
                        Assign
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Remove employee --}}
    @if ($showRemoveEmployee)
        <div class="nks-modal-backdrop" wire:click.self="closeRemoveEmployee" @keydown.escape.window="$wire.closeRemoveEmployee()">
            <div class="nks-modal nks-modal--sm" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Remove employee</h3>
                    <button type="button" wire:click="closeRemoveEmployee" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <p>Remove <strong>{{ $removeName }}</strong> from this project? Their historical timesheet entries stay attached to the project.</p>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeRemoveEmployee" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitRemoveEmployee" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-user-minus"></i>
                        Remove
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Archive --}}
    @if ($showArchive)
        <div class="nks-modal-backdrop" wire:click.self="closeArchive" @keydown.escape.window="$wire.closeArchive()">
            <div class="nks-modal nks-modal--sm" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Archive project</h3>
                    <button type="button" wire:click="closeArchive" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <p>Archive <strong>{{ $archiveName }}</strong>? The project remains visible in historical reports but cannot accept new timesheets or assignments.</p>
                    <div class="nks-field">
                        <label class="nks-label">Admin password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="archivePassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
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