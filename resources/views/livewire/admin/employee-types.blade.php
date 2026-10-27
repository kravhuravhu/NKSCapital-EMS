<div class="page-employee-types">
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

    <div class="panel et-panel">
        <div class="et-header">
            <i class="fa-solid fa-user-tag et-header-icon"></i>
            <div>
                <h2>Employee Types & Service Types</h2>
                <p>Workflow definitions and employment classifications</p>
            </div>
            <div class="et-header-actions">
                <button type="button" wire:click="openWorkflowTest" class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-diagram-project"></i>
                    Test Workflow
                </button>
                @if ($tab === 'employee_types')
                    <button type="button"
                            wire:click="openCreate"
                            class="nks-btn nks-btn--primary nks-btn--sm"
                            @if (count($employeeTypes) >= 2) disabled @endif>
                        <i class="fa-solid fa-plus"></i>
                        Create Employee Type
                    </button>
                @endif
            </div>
        </div>

        <nav class="nks-tabs et-tabs">
            <button type="button"
                    wire:click="selectTab('employee_types')"
                    class="nks-tab {{ $tab === 'employee_types' ? 'is-active' : '' }}">
                Employee Types ({{ count($employeeTypes) }})
            </button>
            <button type="button"
                    wire:click="selectTab('service_types')"
                    class="nks-tab {{ $tab === 'service_types' ? 'is-active' : '' }}">
                Service Types ({{ count($serviceTypes) }})
            </button>
        </nav>

        @if ($loading)
            <x-nks.spinner block label="Loading reference data…" />
        @elseif ($tab === 'employee_types')
            @if (count($employeeTypes) === 0)
                <div class="et-empty">
                    <i class="fa-solid fa-user-tag"></i>
                    <strong>No employee types configured</strong>
                    <span>This is unusual — PS and PR types are seeded by default.</span>
                </div>
            @else
                <div class="et-grid">
                    @foreach ($employeeTypes as $type)
                        <article class="et-card" wire:key="et-{{ $type['id'] ?? $type['type_code'] }}">
                            <header class="et-card-head">
                                <div class="et-card-icon et-card-icon--{{ \App\Livewire\Admin\EmployeeTypes::workflowTone($type['workflow_type'] ?? '') }}">
                                    <i class="fa-solid fa-fingerprint"></i>
                                </div>
                                <div class="et-card-title">
                                    <h3>{{ $type['type_name'] ?? '—' }}</h3>
                                    <span class="et-card-code">{{ $type['type_code'] ?? '' }}</span>
                                </div>
                                <div class="et-card-actions">
                                    <button type="button" wire:click="openEdit({{ $type['id'] }})" class="et-icon-btn" title="Edit">
                                        <i class="fa-solid fa-pen"></i>
                                    </button>
                                    <button type="button"
                                            wire:click="openDelete({{ $type['id'] }}, '{{ addslashes($type['type_name'] ?? '') }}')"
                                            class="et-icon-btn et-icon-btn--danger"
                                            title="Delete">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </header>
                            <div class="et-card-body">
                                <div class="et-card-row">
                                    <span class="et-card-label">Workflow Type</span>
                                    <x-nks.badge :tone="\App\Livewire\Admin\EmployeeTypes::workflowTone($type['workflow_type'] ?? '')">
                                        {{ $type['workflow_type'] ?? '—' }}
                                    </x-nks.badge>
                                </div>

                                <div class="et-card-row">
                                    <span class="et-card-label">External Approval</span>
                                    <span class="et-card-value">
                                        @if (!empty($type['has_external_approval']))
                                            <i class="fa-solid fa-circle-check" style="color: #4CAF7A;"></i>
                                            Yes — client signs off
                                        @else
                                            <i class="fa-solid fa-circle-xmark" style="color: var(--nks-muted);"></i>
                                            No
                                        @endif
                                    </span>
                                </div>

                                <div class="et-card-row">
                                    <span class="et-card-label">Internal Approval</span>
                                    <span class="et-card-value">
                                        @if (!empty($type['has_internal_approval']))
                                            <i class="fa-solid fa-circle-check" style="color: #4CAF7A;"></i>
                                            Yes — L1 + L2 chain
                                        @else
                                            <i class="fa-solid fa-circle-xmark" style="color: var(--nks-muted);"></i>
                                            No
                                        @endif
                                    </span>
                                </div>

                                @if (!empty($type['description']))
                                    <div class="et-card-desc">
                                        {{ $type['description'] }}
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="et-note">
                    <i class="fa-solid fa-lock"></i>
                    <span>
                        Only PS and PR employee types are currently supported by the workflow engine.
                        The Create button is disabled once both exist.
                    </span>
                </div>
            @endif
        @else
            @if (count($serviceTypes) === 0)
                <div class="et-empty">
                    <i class="fa-solid fa-briefcase"></i>
                    <strong>No service types configured</strong>
                    <span>Service types are seeded by the system. Contact a developer if this is unexpected.</span>
                </div>
            @else
                <div class="et-grid">
                    @foreach ($serviceTypes as $svc)
                        <article class="et-card" wire:key="st-{{ $svc['id'] ?? $svc['service_code'] }}">
                            <header class="et-card-head">
                                <div class="et-card-icon et-card-icon--blue">
                                    <i class="{{ \App\Livewire\Admin\EmployeeTypes::serviceIcon($svc['service_code'] ?? '') }}"></i>
                                </div>
                                <div class="et-card-title">
                                    <h3>{{ $svc['service_name'] ?? '—' }}</h3>
                                    <span class="et-card-code">{{ $svc['service_code'] ?? '' }}</span>
                                </div>
                            </header>

                            <div class="et-card-body">
                                <div class="et-card-row">
                                    <span class="et-card-label">Benefits</span>
                                    <span class="et-card-value">
                                        @if (!empty($svc['has_benefits']))
                                            <x-nks.badge tone="success">Included</x-nks.badge>
                                        @else
                                            <x-nks.badge tone="muted">Not included</x-nks.badge>
                                        @endif
                                    </span>
                                </div>

                                <div class="et-card-row">
                                    <span class="et-card-label">Leave Accrual</span>
                                    <span class="et-card-value">
                                        @if (!empty($svc['has_leave_accrual']))
                                            <x-nks.badge tone="success">
                                                {{ number_format((float) ($svc['leave_accrual_rate'] ?? 0), 2) }} days / month
                                            </x-nks.badge>
                                        @else
                                            <x-nks.badge tone="muted">No accrual</x-nks.badge>
                                        @endif
                                    </span>
                                </div>

                                <div class="et-card-row">
                                    <span class="et-card-label">Probation</span>
                                    <span class="et-card-value">
                                        @if (!empty($svc['has_probation_period']))
                                            <x-nks.badge tone="gold">
                                                {{ (int) ($svc['probation_days'] ?? 0) }} days
                                            </x-nks.badge>
                                        @else
                                            <x-nks.badge tone="muted">None</x-nks.badge>
                                        @endif
                                    </span>
                                </div>

                                @if (!empty($svc['description']))
                                    <div class="et-card-desc">{{ $svc['description'] }}</div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>

                <div class="et-note">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>
                        Service types are seeded by the system and cannot be created or edited from the portal.
                        Contact a developer to add new service types.
                    </span>
                </div>
            @endif
        @endif
    </div>

    @if ($showCreate)
        <div class="et-modal-backdrop" wire:click.self="closeCreate">
            <div class="et-modal">
                <header class="et-modal-head">
                    <h3>Create Employee Type</h3>
                    <button type="button" wire:click="closeCreate" class="et-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="et-modal-body">
                    <div class="et-warning">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <span>
                            The backend restricts type codes to <strong>PS</strong> and <strong>PR</strong> only.
                            Any other value will be rejected by the API. Only use this form if the standard types
                            have been removed.
                        </span>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Type Code <span class="nks-required">*</span>
                        </label>
                        <select wire:model="createForm.type_code" class="nks-select">
                            <option value="">Select a code</option>
                            <option value="PS">PS — Professional Services</option>
                            <option value="PR">PR — Projects</option>
                        </select>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Type Name <span class="nks-required">*</span>
                        </label>
                        <input type="text"
                               wire:model="createForm.type_name"
                               class="nks-input"
                               placeholder="Professional Services">
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Workflow Type <span class="nks-required">*</span>
                        </label>
                        <select wire:model="createForm.workflow_type" class="nks-select">
                            <option value="PS_EXTERNAL">PS_EXTERNAL — client sign-off, no internal chain</option>
                            <option value="PR_INTERNAL">PR_INTERNAL — internal L1 + L2 approval</option>
                        </select>
                    </div>

                    <div class="et-checkbox-row">
                        <label class="et-checkbox">
                            <input type="checkbox" wire:model="createForm.has_external_approval">
                            <span>Requires external approval</span>
                        </label>
                        <label class="et-checkbox">
                            <input type="checkbox" wire:model="createForm.has_internal_approval">
                            <span>Requires internal approval</span>
                        </label>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Description</label>
                        <textarea wire:model="createForm.description"
                                  class="nks-textarea"
                                  placeholder="Short description of this employee type."></textarea>
                    </div>
                </div>
                <footer class="et-modal-foot">
                    <button type="button" wire:click="closeCreate" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitCreate" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Create Type
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showWorkflowTest)
        <div class="et-modal-backdrop" wire:click.self="closeWorkflowTest">
            <div class="et-modal et-modal--lg">
                <header class="et-modal-head">
                    <h3>Workflow Tester</h3>
                    <button type="button" wire:click="closeWorkflowTest" class="et-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="et-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Employee to Simulate</label>
                        <select wire:model="testEmployeeId" class="nks-select">
                            <option value="">Select an employee</option>
                            @foreach ($testEmployees as $emp)
                                <option value="{{ $emp['id'] }}">
                                    {{ $emp['first_name'] }} {{ $emp['last_name'] }}
                                    — {{ strtoupper($emp['employee_type'] ?? '?') }}
                                    {{ $emp['employee_number'] ? '(' . $emp['employee_number'] . ')' : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <button type="button"
                            wire:click="runWorkflowTest"
                            wire:loading.attr="disabled"
                            wire:target="runWorkflowTest"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-play"></i>
                        Run Simulation
                    </button>

                    @if ($workflowResult)
                        @php
                            $sim = $workflowResult['simulation'] ?? [];
                            $steps = $sim['workflow_steps'] ?? [];
                        @endphp

                        <div class="et-workflow-result">
                            <div class="et-workflow-meta">
                                <div>
                                    <span class="et-card-label">Employee</span>
                                    <span class="et-card-value">
                                        {{ $sim['employee']['name'] ?? '—' }}
                                    </span>
                                </div>
                                <div>
                                    <span class="et-card-label">Type</span>
                                    <span class="et-card-value">
                                        {{ strtoupper($sim['employee']['employee_type'] ?? '—') }}
                                        · {{ ucfirst($sim['employee']['service_type'] ?? '—') }}
                                    </span>
                                </div>
                                <div>
                                    <span class="et-card-label">Workflow</span>
                                    <x-nks.badge :tone="\App\Livewire\Admin\EmployeeTypes::workflowTone($sim['workflow_type'] ?? '')">
                                        {{ $sim['workflow_type'] ?? '—' }}
                                    </x-nks.badge>
                                </div>
                            </div>

                            @if (!empty($sim['assigned_client']))
                                <div class="et-workflow-meta">
                                    <div>
                                        <span class="et-card-label">Client</span>
                                        <span class="et-card-value">{{ $sim['assigned_client'] }}</span>
                                    </div>
                                </div>
                            @endif

                            @if (!empty($sim['assigned_project']))
                                <div class="et-workflow-meta">
                                    <div>
                                        <span class="et-card-label">Project</span>
                                        <span class="et-card-value">{{ $sim['assigned_project'] }}</span>
                                    </div>
                                </div>
                            @endif

                            <h4 class="et-steps-title">Approval Chain</h4>
                            <ol class="et-steps">
                                @foreach ($steps as $step)
                                    <li class="et-step">
                                        <div class="et-step-num">{{ $step['step'] ?? '—' }}</div>
                                        <div class="et-step-body">
                                            <div class="et-step-name">{{ $step['name'] ?? '—' }}</div>
                                            <div class="et-step-role">
                                                {{ $step['role'] ?? '—' }}
                                                @if (!empty($step['approver']))
                                                    — <strong>{{ $step['approver'] }}</strong>
                                                @endif
                                            </div>
                                        </div>
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @else
                        <div class="et-workflow-empty">
                            <i class="fa-solid fa-circle-info"></i>
                            Select an employee and run the simulation to see the approval chain.
                        </div>
                    @endif
                </div>
                <footer class="et-modal-foot">
                    <button type="button" wire:click="closeWorkflowTest" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>