<div class="page-requisitions">
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
        <x-nks.spinner block label="Loading requisitions..." />
    @else
        <div class="rq-stats">
            <div class="rq-stat">
                <div class="rq-stat-icon"><i class="fa-solid fa-briefcase" aria-hidden="true"></i></div>
                <div>
                    <div class="rq-stat-label">Total</div>
                    <div class="rq-stat-value">{{ $stats['total'] }}</div>
                </div>
            </div>
            <div class="rq-stat rq-stat--success">
                <div class="rq-stat-icon"><i class="fa-solid fa-bullhorn" aria-hidden="true"></i></div>
                <div>
                    <div class="rq-stat-label">Open</div>
                    <div class="rq-stat-value">{{ $stats['open'] }}</div>
                </div>
            </div>
            <div class="rq-stat rq-stat--warning">
                <div class="rq-stat-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></div>
                <div>
                    <div class="rq-stat-label">Pending</div>
                    <div class="rq-stat-value">{{ $stats['pending'] }}</div>
                </div>
            </div>
            <div class="rq-stat">
                <div class="rq-stat-icon"><i class="fa-solid fa-lock" aria-hidden="true"></i></div>
                <div>
                    <div class="rq-stat-label">Closed</div>
                    <div class="rq-stat-value">{{ $stats['closed'] }}</div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="page-toolbar">
                <div>
                    <h2>Requisitions</h2>
                    <p>Hiring requests awaiting approval, open roles, and closed positions</p>
                </div>
                <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary">
                    <i class="fa-solid fa-plus"></i>
                    New requisition
                </button>
            </div>

            <div class="rq-filters">
                <div class="nks-field rq-filter-search">
                    <label class="nks-label">Search</label>
                    <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                           class="nks-input" placeholder="Title, code, or department">
                </div>
                <div class="nks-field">
                    <label class="nks-label">Status</label>
                    <select wire:model="statusFilter" class="nks-select">
                        <option value="">Any</option>
                        <option value="draft">Draft</option>
                        <option value="pending_approval">Pending approval</option>
                        <option value="approved">Approved</option>
                        <option value="open">Open</option>
                        <option value="closed">Closed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="nks-field">
                    <label class="nks-label">Priority</label>
                    <select wire:model="priorityFilter" class="nks-select">
                        <option value="">Any</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div class="nks-field">
                    <label class="nks-label">Type target</label>
                    <select wire:model="employeeTypeFilter" class="nks-select">
                        <option value="">Any</option>
                        <option value="ps">PS</option>
                        <option value="pr">PR</option>
                    </select>
                </div>
                <div class="rq-filter-actions">
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

            @if (count($requisitions) === 0)
                <div class="nks-card-empty">
                    <i class="fa-solid fa-briefcase"></i>
                    <p>No requisitions match your filters.</p>
                </div>
            @else
                <div class="nks-table-wrapper">
                    <table class="nks-table">
                        <thead>
                            <tr>
                                <th>Requisition</th>
                                <th>Department</th>
                                <th>Type</th>
                                <th>Priority</th>
                                <th>Candidates</th>
                                <th>Status</th>
                                <th style="width: 220px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requisitions as $r)
                                @php
                                    $status = $r['status'] ?? 'draft';
                                    $priority = $r['priority'] ?? 'medium';
                                @endphp
                                <tr wire:key="rq-{{ $r['id'] }}" wire:click="openView({{ $r['id'] }})" class="rq-row">
                                    <td>
                                        <strong>{{ $r['title'] ?? '—' }}</strong>
                                        <div class="rq-meta">{{ $r['requisition_code'] ?? '' }}</div>
                                    </td>
                                    <td>{{ $r['department'] ?? '—' }}</td>
                                    <td>
                                        @if (! empty($r['employee_type_target']))
                                            <x-nks.badge tone="gold">{{ strtoupper($r['employee_type_target']) }}</x-nks.badge>
                                        @endif
                                        <div class="rq-meta">{{ ucfirst(str_replace('_', ' ', $r['employment_type'] ?? '')) }}</div>
                                    </td>
                                    <td>
                                        <x-nks.badge :tone="self::priorityTone($priority)">
                                            {{ ucfirst($priority) }}
                                        </x-nks.badge>
                                    </td>
                                    <td>{{ $r['candidates_count'] ?? 0 }}</td>
                                    <td>
                                        <x-nks.badge :tone="self::statusTone($status)">
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </x-nks.badge>
                                    </td>
                                    <td @click.stop>
                                        <div class="rq-row-actions">
                                            @if ($this->canUpdate($r))
                                                <button type="button" wire:click="openEdit({{ $r['id'] }})"
                                                        class="nks-btn nks-btn--outline nks-btn--sm" title="Edit">
                                                    <i class="fa-solid fa-pen"></i>
                                                </button>
                                            @endif
                                            @if ($this->canApprove($r))
                                                <button type="button" wire:click="openApprove({{ $r['id'] }})"
                                                        class="nks-btn nks-btn--primary nks-btn--sm">
                                                    <i class="fa-solid fa-check"></i>
                                                    Approve
                                                </button>
                                            @endif
                                            @if ($this->canClose($r))
                                                <button type="button" wire:click="openClose({{ $r['id'] }}, 'closed')"
                                                        class="nks-btn nks-btn--outline nks-btn--sm" title="Close">
                                                    <i class="fa-solid fa-lock"></i>
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
                    <div class="rq-pagination">
                        <button type="button" wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                                class="nks-btn nks-btn--outline nks-btn--sm"
                                @if ($pagination['current_page'] <= 1) disabled @endif>
                            <i class="fa-solid fa-chevron-left"></i>
                            Previous
                        </button>
                        <span class="rq-page-info">
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

    @if ($showView && $viewData)
        @php
            $vStatus = $viewData['status'] ?? '';
            $vPriority = $viewData['priority'] ?? 'medium';
        @endphp
        <div class="nks-modal-backdrop" wire:click.self="closeView" @keydown.escape.window="$wire.closeView()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <div>
                        <h3>{{ $viewData['title'] ?? 'Requisition' }}</h3>
                        <span class="nks-modal-sub">{{ $viewData['requisition_code'] ?? '' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="rq-detail-badges">
                        <x-nks.badge :tone="self::statusTone($vStatus)">
                            {{ ucfirst(str_replace('_', ' ', $vStatus)) }}
                        </x-nks.badge>
                        <x-nks.badge :tone="self::priorityTone($vPriority)">
                            {{ ucfirst($vPriority) }} priority
                        </x-nks.badge>
                        @if (! empty($viewData['employee_type_target']))
                            <x-nks.badge tone="gold">{{ strtoupper($viewData['employee_type_target']) }} target</x-nks.badge>
                        @endif
                    </div>

                    <div class="rq-detail-grid">
                        <div>
                            <span class="rq-detail-label">Department</span>
                            <span class="rq-detail-value">{{ $viewData['department'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Employment type</span>
                            <span class="rq-detail-value">{{ ucfirst(str_replace('_', ' ', $viewData['employment_type'] ?? '—')) }}</span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Experience level</span>
                            <span class="rq-detail-value">{{ $viewData['experience_level'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Salary range</span>
                            <span class="rq-detail-value">
                                @if (! empty($viewData['salary_range_min']) && ! empty($viewData['salary_range_max']))
                                    R {{ number_format((float) $viewData['salary_range_min'], 0) }} to R {{ number_format((float) $viewData['salary_range_max'], 0) }}
                                    {{ $viewData['salary_currency'] ?? 'ZAR' }}
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Target start</span>
                            <span class="rq-detail-value">
                                {{ ! empty($viewData['target_start_date']) ? \Carbon\Carbon::parse($viewData['target_start_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Hiring manager</span>
                            <span class="rq-detail-value">
                                @if (! empty($viewData['hiring_manager']))
                                    {{ $viewData['hiring_manager']['first_name'] ?? '' }}
                                    {{ $viewData['hiring_manager']['last_name'] ?? '' }}
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Created by</span>
                            <span class="rq-detail-value">
                                @if (! empty($viewData['created_by']))
                                    {{ $viewData['created_by']['first_name'] ?? '' }}
                                    {{ $viewData['created_by']['last_name'] ?? '' }}
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Candidates</span>
                            <span class="rq-detail-value">{{ $viewData['candidates_count'] ?? 0 }}</span>
                        </div>
                    </div>

                    @if (! empty($viewData['required_skills']))
                        <div class="rq-detail-section">
                            <span class="rq-detail-label">Required skills</span>
                            <div class="rq-skill-chips">
                                @foreach ($viewData['required_skills'] as $skill)
                                    <span class="rq-skill-chip">{{ $skill }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (! empty($viewData['reason_for_hire']))
                        <div class="rq-detail-section">
                            <span class="rq-detail-label">Reason for hire</span>
                            <p class="rq-detail-text">{{ $viewData['reason_for_hire'] }}</p>
                        </div>
                    @endif

                    @if (! empty($viewData['rejection_reason']))
                        <div class="rq-detail-section">
                            <span class="rq-detail-label">Rejection or closure reason</span>
                            <p class="rq-detail-text rq-detail-text--danger">{{ $viewData['rejection_reason'] }}</p>
                        </div>
                    @endif
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    @if ($this->canUpdate($viewData))
                        <button type="button" wire:click="openEdit({{ $viewData['id'] }})" class="nks-btn nks-btn--primary">
                            <i class="fa-solid fa-pen"></i>
                            Edit
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif

    @if ($showCreate)
        <div class="nks-modal-backdrop" wire:click.self="closeCreate" @keydown.escape.window="$wire.closeCreate()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>New requisition</h3>
                    <button type="button" wire:click="closeCreate" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="rq-form-grid">
                        <div class="nks-field rq-form-full">
                            <label class="nks-label">Title <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.title" class="nks-input" placeholder="Senior Laravel Developer">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Department <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.department" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Employment type <span class="nks-required">*</span></label>
                            <select wire:model="createForm.employment_type" class="nks-select">
                                <option value="full_time">Full time</option>
                                <option value="part_time">Part time</option>
                                <option value="contract">Contract</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Employee type target</label>
                            <select wire:model="createForm.employee_type_target" class="nks-select">
                                <option value="">Not specified</option>
                                <option value="ps">PS</option>
                                <option value="pr">PR</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Priority</label>
                            <select wire:model="createForm.priority" class="nks-select">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Experience level</label>
                            <input type="text" wire:model="createForm.experience_level" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Hiring manager</label>
                            <select wire:model="createForm.hiring_manager_id" class="nks-select">
                                <option value="">Unassigned</option>
                                @foreach ($managers as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Salary min</label>
                            <input type="number" wire:model="createForm.salary_range_min" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Salary max</label>
                            <input type="number" wire:model="createForm.salary_range_max" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Target start date</label>
                            <input type="date" wire:model="createForm.target_start_date" class="nks-input">
                        </div>
                        <div class="nks-field rq-form-full">
                            <label class="nks-label">Required skills</label>
                            <input type="text" wire:model="createForm.required_skills" class="nks-input" placeholder="PHP, Laravel, MySQL">
                        </div>
                        <div class="nks-field rq-form-full">
                            <label class="nks-label">Reason for hire</label>
                            <textarea wire:model="createForm.reason_for_hire" class="nks-textarea"></textarea>
                        </div>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeCreate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitCreate('draft')" class="nks-btn nks-btn--outline">
                        <i class="fa-solid fa-floppy-disk"></i>
                        Save as draft
                    </button>
                    <button type="button" wire:click="submitCreate('pending_approval')"
                            wire:loading.attr="disabled" wire:target="submitCreate"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-paper-plane" wire:loading.remove wire:target="submitCreate"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="submitCreate"></span>
                        Submit for approval
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showEdit && $editId)
        <div class="nks-modal-backdrop" wire:click.self="closeEdit" @keydown.escape.window="$wire.closeEdit()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Edit requisition</h3>
                    <button type="button" wire:click="closeEdit" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="rq-form-grid">
                        <div class="nks-field rq-form-full">
                            <label class="nks-label">Title</label>
                            <input type="text" wire:model="editForm.title" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Department</label>
                            <input type="text" wire:model="editForm.department" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Employment type</label>
                            <select wire:model="editForm.employment_type" class="nks-select">
                                <option value="full_time">Full time</option>
                                <option value="part_time">Part time</option>
                                <option value="contract">Contract</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Employee type target</label>
                            <select wire:model="editForm.employee_type_target" class="nks-select">
                                <option value="">Not specified</option>
                                <option value="ps">PS</option>
                                <option value="pr">PR</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Priority</label>
                            <select wire:model="editForm.priority" class="nks-select">
                                <option value="low">Low</option>
                                <option value="medium">Medium</option>
                                <option value="high">High</option>
                                <option value="critical">Critical</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Experience level</label>
                            <input type="text" wire:model="editForm.experience_level" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Hiring manager</label>
                            <select wire:model="editForm.hiring_manager_id" class="nks-select">
                                <option value="">Unassigned</option>
                                @foreach ($managers as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Salary min</label>
                            <input type="number" wire:model="editForm.salary_range_min" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Salary max</label>
                            <input type="number" wire:model="editForm.salary_range_max" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Target start date</label>
                            <input type="date" wire:model="editForm.target_start_date" class="nks-input">
                        </div>
                        <div class="nks-field rq-form-full">
                            <label class="nks-label">Required skills</label>
                            <input type="text" wire:model="editForm.required_skills" class="nks-input">
                        </div>
                        <div class="nks-field rq-form-full">
                            <label class="nks-label">Reason for hire</label>
                            <textarea wire:model="editForm.reason_for_hire" class="nks-textarea"></textarea>
                        </div>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeEdit" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitEdit" class="nks-btn nks-btn--primary"
                            wire:loading.attr="disabled" wire:target="submitEdit">
                        <i class="fa-solid fa-check" wire:loading.remove wire:target="submitEdit"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="submitEdit"></span>
                        Save changes
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showApprove)
        <div class="nks-modal-backdrop" wire:click.self="closeApprove" @keydown.escape.window="$wire.closeApprove()">
            <div class="nks-modal nks-modal--sm" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Approve requisition</h3>
                    <button type="button" wire:click="closeApprove" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <p>Approving opens this requisition for applications. Recruiters can then add candidates.</p>
                    <div class="nks-field">
                        <label class="nks-label">Your password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="approvePassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeApprove" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitApprove" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Approve
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showReject)
        <div class="nks-modal-backdrop" wire:click.self="closeReject" @keydown.escape.window="$wire.closeReject()">
            <div class="nks-modal nks-modal--sm" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Reject requisition</h3>
                    <button type="button" wire:click="closeReject" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Rejection reason <span class="nks-required">*</span></label>
                        <textarea wire:model="rejectReason" class="nks-textarea"></textarea>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Your password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="rejectPassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeReject" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitReject" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-xmark"></i>
                        Reject requisition
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showClose)
        <div class="nks-modal-backdrop" wire:click.self="closeClose" @keydown.escape.window="$wire.closeClose()">
            <div class="nks-modal nks-modal--sm" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>{{ $closeStatus === 'cancelled' ? 'Cancel' : 'Close' }} requisition</h3>
                    <button type="button" wire:click="closeClose" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <p>{{ $closeStatus === 'cancelled'
                        ? 'Cancelling permanently ends this requisition. No further candidates can be added.'
                        : 'Closing marks this requisition as filled. Existing candidates stay visible.' }}</p>
                    <div class="nks-field">
                        <label class="nks-label">Reason (optional)</label>
                        <textarea wire:model="closeReason" class="nks-textarea"></textarea>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Your password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="closePassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeClose" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitClose" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-check"></i>
                        Confirm
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>