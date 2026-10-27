<div class="page-leave-config">
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

    <div class="panel lc-panel">
        <div class="lc-header">
            <i class="fa-solid fa-sliders lc-header-icon"></i>
            <div>
                <h2>Leave Configuration</h2>
                <p>Entitlements, accrual, approval routing, proof requirements, and notification cadence</p>
            </div>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading leave config…" />
        @elseif (count($configs) === 0)
            <div class="lc-empty">
                <i class="fa-solid fa-sliders"></i>
                <strong>No leave types configured</strong>
                <span>Run the seeders to populate the default leave types before editing them here.</span>
            </div>
        @else
            <div class="lc-grid">
                @foreach ($configs as $cfg)
                    @php
                        $type = $cfg['leave_type'] ?? '';
                        $approval = $cfg['approval_type'] ?? 'manual';
                        $entitlement = (float) ($cfg['default_entitlement'] ?? 0);
                        $accrualRate = (float) ($cfg['accrual_rate'] ?? 0);
                        $accrualFreq = $cfg['accrual_frequency'] ?? 'monthly';
                    @endphp
                    <article class="lc-card" wire:key="lc-{{ $type }}">
                        <header class="lc-card-head">
                            <div class="lc-card-icon">
                                <i class="{{ \App\Livewire\Director\LeaveConfigPage::leaveTypeIcon($type) }}"></i>
                            </div>
                            <div class="lc-card-title">
                                <h3>{{ ucfirst($type) }} Leave</h3>
                                <span class="lc-card-sub">{{ $cfg['description'] ?? 'No description set' }}</span>
                            </div>
                            <x-nks.badge :tone="\App\Livewire\Director\LeaveConfigPage::approvalTone($approval)">
                                {{ ucfirst($approval) }}
                            </x-nks.badge>
                        </header>

                        <dl class="lc-card-meta">
                            <div>
                                <dt>Entitlement</dt>
                                <dd>
                                    @if ($cfg['is_infinite_balance'] ?? false)
                                        Unlimited
                                    @else
                                        {{ number_format($entitlement, 1) }} days
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>Accrual</dt>
                                <dd>
                                    @if (!($cfg['is_accruable'] ?? true))
                                        Not accruable
                                    @else
                                        {{ number_format($accrualRate, 2) }} / {{ $accrualFreq }}
                                    @endif
                                </dd>
                            </div>
                            <div>
                                <dt>Max Carryover</dt>
                                <dd>{{ number_format((float) ($cfg['max_carryover'] ?? 0), 1) }} days</dd>
                            </div>
                            <div>
                                <dt>Min Service</dt>
                                <dd>{{ number_format((float) ($cfg['min_service_months'] ?? 0), 1) }} mo</dd>
                            </div>
                        </dl>

                        <div class="lc-card-badges">
                            @if (($cfg['requires_attachment'] ?? false))
                                <span class="lc-chip">Attachment required</span>
                            @endif
                            @if (($cfg['requires_proof_after'] ?? 0) > 0)
                                <span class="lc-chip">
                                    Proof after {{ (int) $cfg['requires_proof_after'] }} day(s)
                                </span>
                            @endif
                            @if (($cfg['auto_approve_max_days'] ?? 0) > 0 && $approval !== 'manual')
                                <span class="lc-chip">
                                    Auto-approve ≤ {{ (int) $cfg['auto_approve_max_days'] }} d
                                </span>
                            @endif
                            @if (($cfg['partial_approve_threshold'] ?? 0) > 0)
                                <span class="lc-chip">
                                    Partial from {{ (int) $cfg['partial_approve_threshold'] }} d
                                </span>
                            @endif
                            @if (($cfg['is_infinite_balance'] ?? false))
                                <span class="lc-chip lc-chip--muted">Infinite balance</span>
                            @endif
                            @if (($cfg['is_carryover_allowed'] ?? false))
                                <span class="lc-chip lc-chip--muted">Carryover allowed</span>
                            @endif
                        </div>

                        <footer class="lc-card-foot">
                            <button type="button"
                                    wire:click="openEdit('{{ $type }}')"
                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                <i class="fa-solid fa-pen"></i>
                                Edit Config
                            </button>
                        </footer>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    <div class="panel lc-panel">
        <div class="lc-header">
            <i class="fa-solid fa-user-gear lc-header-icon"></i>
            <div>
                <h2>Reset Employee Balance</h2>
                <p>Adjust annual, sick, or family balances for a specific employee, every change is logged</p>
            </div>
            <div class="lc-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text"
                       wire:model.live.debounce.500ms="employeeSearch"
                       class="lc-search-input"
                       placeholder="Search by name, employee number, email">
            </div>
        </div>

        @if ($employeesLoading)
            <x-nks.spinner block label="Loading employees…" />
        @elseif (count($employees) === 0)
            <div class="lc-empty">
                <i class="fa-solid fa-users-slash"></i>
                <strong>No employees found</strong>
                <span>Try a different search term.</span>
            </div>
        @else
            <div class="lc-table-wrapper">
                <table class="lc-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Annual</th>
                            <th>Sick</th>
                            <th>Family</th>
                            <th style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $emp)
                            @php
                                $name = trim(($emp['first_name'] ?? '') . ' ' . ($emp['last_name'] ?? ''));
                            @endphp
                            <tr wire:key="lc-emp-{{ $emp['id'] }}">
                                <td>
                                    <div class="lc-emp-name">{{ $name ?: '|' }}</div>
                                    <div class="lc-emp-meta">
                                        {{ $emp['employee_number'] ?? '' }}
                                        @if (!empty($emp['department']))
                                            · {{ $emp['department'] }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <x-nks.badge tone="blue">
                                        {{ strtoupper($emp['employee_type'] ?? '|') }}
                                    </x-nks.badge>
                                </td>
                                <td>{{ number_format((float) ($emp['leave_balance_annual'] ?? 0), 1) }}</td>
                                <td>{{ number_format((float) ($emp['leave_balance_sick'] ?? 0), 1) }}</td>
                                <td>{{ number_format((float) ($emp['leave_balance_family'] ?? 0), 1) }}</td>
                                <td>
                                    <button type="button"
                                            wire:click="openReset({{ $emp['id'] }}, '{{ addslashes($name) }}')"
                                            class="nks-btn nks-btn--outline nks-btn--sm">
                                        <i class="fa-solid fa-rotate-left"></i>
                                        Reset Balance
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($showEdit)
        <div class="lc-modal-backdrop" wire:click.self="closeEdit">
            <div class="lc-modal lc-modal--lg">
                <header class="lc-modal-head">
                    <h3>Edit {{ ucfirst($editingType ?? '') }} Leave</h3>
                    <button type="button" wire:click="closeEdit" class="lc-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="lc-modal-body">
                    <div class="lc-section">
                        <h4 class="lc-section-title">Entitlement & Accrual</h4>
                        <div class="lc-form-grid">
                            <div class="nks-field">
                                <label class="nks-label">Default Entitlement (days)</label>
                                <input type="number" step="0.5" min="0" wire:model="form.default_entitlement" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Accrual Rate</label>
                                <input type="number" step="0.01" min="0" wire:model="form.accrual_rate" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Accrual Frequency</label>
                                <select wire:model="form.accrual_frequency" class="nks-select">
                                    <option value="daily">Daily</option>
                                    <option value="weekly">Weekly</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                    <option value="none">None</option>
                                </select>
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Max Carryover (days)</label>
                                <input type="number" step="0.5" min="0" wire:model="form.max_carryover" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Minimum Service (months)</label>
                                <input type="number" step="0.5" min="0" wire:model="form.min_service_months" class="nks-input">
                            </div>
                        </div>

                        <div class="lc-checkboxes">
                            <label class="lc-checkbox">
                                <input type="checkbox" wire:model="form.is_accruable">
                                <span>Accruable</span>
                            </label>
                            <label class="lc-checkbox">
                                <input type="checkbox" wire:model="form.is_carryover_allowed">
                                <span>Carryover allowed</span>
                            </label>
                            <label class="lc-checkbox">
                                <input type="checkbox" wire:model="form.is_infinite_balance">
                                <span>Infinite balance</span>
                            </label>
                        </div>
                    </div>

                    <div class="lc-section">
                        <h4 class="lc-section-title">Approval Routing</h4>
                        <div class="lc-form-grid">
                            <div class="nks-field">
                                <label class="nks-label">Approval Type</label>
                                <select wire:model="form.approval_type" class="nks-select">
                                    <option value="auto">Auto</option>
                                    <option value="hybrid">Hybrid</option>
                                    <option value="manual">Manual</option>
                                </select>
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Auto-Approve Max Days</label>
                                <input type="number" min="0" wire:model="form.auto_approve_max_days" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Partial Approve Threshold (days)</label>
                                <input type="number" min="0" wire:model="form.partial_approve_threshold" class="nks-input">
                            </div>
                        </div>

                        <label class="lc-checkbox">
                            <input type="checkbox" wire:model="form.auto_approve">
                            <span>Auto-approve all requests of this type</span>
                        </label>
                    </div>

                    <div class="lc-section">
                        <h4 class="lc-section-title">Proof of Absence</h4>
                        <div class="lc-form-grid">
                            <div class="nks-field">
                                <label class="nks-label">Requires Proof After (days)</label>
                                <input type="number" min="0" wire:model="form.requires_proof_after" class="nks-input">
                                <span class="nks-help">0 = no proof required</span>
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Proof Deadline (working days)</label>
                                <input type="number" min="0" wire:model="form.proof_upload_deadline_days" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Notification Frequency (hours)</label>
                                <input type="number" min="0" wire:model="form.notification_frequency_hours" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Notify From (hour)</label>
                                <input type="number" min="0" max="23" wire:model="form.notification_start_hour" class="nks-input">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Notify Until (hour)</label>
                                <input type="number" min="0" max="23" wire:model="form.notification_end_hour" class="nks-input">
                            </div>
                        </div>

                        <div class="lc-checkboxes">
                            <label class="lc-checkbox">
                                <input type="checkbox" wire:model="form.requires_attachment">
                                <span>Attachment required on application</span>
                            </label>
                            <label class="lc-checkbox">
                                <input type="checkbox" wire:model="form.convert_to_unpaid_after_deadline">
                                <span>Convert to unpaid if proof missing by deadline</span>
                            </label>
                        </div>

                        <div class="nks-field">
                            <label class="nks-label">Min Days for Attachment</label>
                            <input type="number" min="0" wire:model="form.min_days_attachment" class="nks-input">
                        </div>
                    </div>

                    <div class="lc-section">
                        <h4 class="lc-section-title">Description</h4>
                        <div class="nks-field">
                            <label class="nks-label">Employee-facing description</label>
                            <textarea wire:model="form.description"
                                      class="nks-textarea"
                                      placeholder="Shown on the Leave Request page."></textarea>
                        </div>
                    </div>
                </div>
                <footer class="lc-modal-foot">
                    <button type="button" wire:click="closeEdit" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="save"
                            wire:loading.attr="disabled"
                            wire:target="save"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Save Config
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showReset)
        <div class="lc-modal-backdrop" wire:click.self="closeReset">
            <div class="lc-modal">
                <header class="lc-modal-head">
                    <h3>Reset Balance | {{ $resetUserName }}</h3>
                    <button type="button" wire:click="closeReset" class="lc-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="lc-modal-body">
                    <p class="lc-hint">
                        Leave fields blank to keep the existing balance. Provide a reason, it is written to the audit trail.
                    </p>

                    <div class="lc-form-grid">
                        <div class="nks-field">
                            <label class="nks-label">Annual (days)</label>
                            <input type="number" step="0.5" min="0" wire:model="resetForm.annual_balance" class="nks-input" placeholder="Leave blank to keep">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Sick (days)</label>
                            <input type="number" step="0.5" min="0" wire:model="resetForm.sick_balance" class="nks-input" placeholder="Leave blank to keep">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Family (days)</label>
                            <input type="number" step="0.5" min="0" wire:model="resetForm.family_balance" class="nks-input" placeholder="Leave blank to keep">
                        </div>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Reason <span class="nks-required">*</span>
                        </label>
                        <textarea wire:model="resetForm.reason"
                                  class="nks-textarea"
                                  placeholder="Explain why the balance is being reset."></textarea>
                    </div>
                </div>
                <footer class="lc-modal-foot">
                    <button type="button" wire:click="closeReset" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitReset" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-rotate-left"></i>
                        Confirm Reset
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>