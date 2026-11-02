<div class="page-requisitions">
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

    <div class="panel rq-panel">
        <div class="rq-header">
            <i class="fa-solid fa-briefcase rq-header-icon"></i>
            <div>
                <h2>Requisitions</h2>
                <p>Hiring requests awaiting approval, open roles, and closed positions</p>
            </div>
            <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary rq-create-btn">
                <i class="fa-solid fa-plus"></i>
                New Requisition
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
                    <option value="pending_approval">Pending Approval</option>
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
                <label class="nks-label">Type Target</label>
                <select wire:model="employeeTypeFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="ps">PS</option>
                    <option value="pr">PR</option>
                </select>
            </div>
            <div class="rq-filter-actions">
                <button type="button" wire:click="resetFilters" class="nks-btn nks-btn--ghost nks-btn--sm">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </button>
                <button type="button" wire:click="applyFilters" class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-filter"></i> Apply
                </button>
            </div>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading requisitions…" />
        @elseif (count($requisitions) === 0)
            <div class="rq-empty">
                <i class="fa-solid fa-briefcase"></i>
                <strong>No requisitions found</strong>
                <span>Adjust filters, or create your first hiring request.</span>
            </div>
        @else
            <div class="rq-table-wrapper">
                <table class="rq-table">
                    <thead>
                        <tr>
                            <th>Requisition</th>
                            <th>Department</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Candidates</th>
                            <th>Status</th>
                            <th style="width: 260px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($requisitions as $r)
                            @php
                                $status = $r['status'] ?? 'draft';
                                $priority = $r['priority'] ?? 'medium';
                            @endphp
                            <tr wire:key="rq-{{ $r['id'] }}">
                                <td>
                                    <div class="rq-name">{{ $r['title'] ?? '—' }}</div>
                                    <div class="rq-meta">{{ $r['requisition_code'] ?? '' }}</div>
                                </td>
                                <td>{{ $r['department'] ?? '—' }}</td>
                                <td>
                                    @if (!empty($r['employee_type_target']))
                                        <x-nks.badge tone="gold">{{ strtoupper($r['employee_type_target']) }}</x-nks.badge>
                                    @endif
                                    <div class="rq-meta">{{ ucfirst(str_replace('_', ' ', $r['employment_type'] ?? '')) }}</div>
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Recruiter\Requisitions::priorityTone($priority)">
                                        {{ ucfirst($priority) }}
                                    </x-nks.badge>
                                </td>
                                <td>{{ $r['candidates_count'] ?? 0 }}</td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Recruiter\Requisitions::statusTone($status)">
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    <div class="rq-row-actions">
                                        <button type="button" wire:click="openView({{ $r['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>

                                        @if ($this->canUpdate($r))
                                            <button type="button" wire:click="openEdit({{ $r['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-pen"></i> Edit
                                            </button>
                                        @endif

                                        @if ($this->canApprove($r))
                                            <button type="button" wire:click="openApprove({{ $r['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-check"></i> Approve
                                            </button>
                                            <button type="button" wire:click="openReject({{ $r['id'] }})"
                                                    class="nks-btn nks-btn--danger nks-btn--sm">
                                                <i class="fa-solid fa-xmark"></i> Reject
                                            </button>
                                        @endif

                                        @if ($this->canClose($r))
                                            <button type="button" wire:click="openClose({{ $r['id'] }}, 'closed')"
                                                    class="rq-icon-btn" title="Close">
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
                        <i class="fa-solid fa-chevron-left"></i> Previous
                    </button>
                    <span class="rq-page-info">
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

    {{-- view --}}
    @if ($showView && $viewData)
        @php
            $vStatus = $viewData['status'] ?? '';
            $vPriority = $viewData['priority'] ?? 'medium';
        @endphp
        <div class="rq-modal-backdrop" wire:click.self="closeView">
            <div class="rq-modal rq-modal--lg">
                <header class="rq-modal-head">
                    <div>
                        <h3>{{ $viewData['title'] ?? 'Requisition' }}</h3>
                        <span class="rq-modal-sub">{{ $viewData['requisition_code'] ?? '' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="rq-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="rq-modal-body">
                    <div class="rq-detail-badges">
                        <x-nks.badge :tone="\App\Livewire\Recruiter\Requisitions::statusTone($vStatus)">
                            {{ ucfirst(str_replace('_', ' ', $vStatus)) }}
                        </x-nks.badge>
                        <x-nks.badge :tone="\App\Livewire\Recruiter\Requisitions::priorityTone($vPriority)">
                            {{ ucfirst($vPriority) }} priority
                        </x-nks.badge>
                        @if (!empty($viewData['employee_type_target']))
                            <x-nks.badge tone="gold">{{ strtoupper($viewData['employee_type_target']) }} target</x-nks.badge>
                        @endif
                    </div>

                    <div class="rq-detail-grid">
                        <div>
                            <span class="rq-detail-label">Department</span>
                            <span class="rq-detail-value">{{ $viewData['department'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Employment Type</span>
                            <span class="rq-detail-value">{{ ucfirst(str_replace('_', ' ', $viewData['employment_type'] ?? '—')) }}</span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Experience Level</span>
                            <span class="rq-detail-value">{{ $viewData['experience_level'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Salary Range</span>
                            <span class="rq-detail-value">
                                @if (!empty($viewData['salary_range_min']) && !empty($viewData['salary_range_max']))
                                    R {{ number_format((float) $viewData['salary_range_min'], 0) }}
                                    &ndash;
                                    R {{ number_format((float) $viewData['salary_range_max'], 0) }}
                                    {{ $viewData['salary_currency'] ?? 'ZAR' }}
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Target Start</span>
                            <span class="rq-detail-value">
                                {{ !empty($viewData['target_start_date']) ? \Carbon\Carbon::parse($viewData['target_start_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Hiring Manager</span>
                            <span class="rq-detail-value">
                                @if (!empty($viewData['hiring_manager']))
                                    {{ $viewData['hiring_manager']['first_name'] ?? '' }}
                                    {{ $viewData['hiring_manager']['last_name'] ?? '' }}
                                @else — @endif
                            </span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Created By</span>
                            <span class="rq-detail-value">
                                @if (!empty($viewData['created_by']))
                                    {{ $viewData['created_by']['first_name'] ?? '' }}
                                    {{ $viewData['created_by']['last_name'] ?? '' }}
                                @else — @endif
                            </span>
                        </div>
                        <div>
                            <span class="rq-detail-label">Candidates</span>
                            <span class="rq-detail-value">{{ $viewData['candidates_count'] ?? 0 }}</span>
                        </div>
                    </div>

                    @if (!empty($viewData['required_skills']))
                        <div class="rq-detail-section">
                            <span class="rq-detail-label">Required Skills</span>
                            <div class="rq-skill-chips">
                                @foreach ($viewData['required_skills'] as $skill)
                                    <span class="rq-skill-chip">{{ $skill }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if (!empty($viewData['reason_for_hire']))
                        <div class="rq-detail-section">
                            <span class="rq-detail-label">Reason for Hire</span>
                            <p class="rq-detail-text">{{ $viewData['reason_for_hire'] }}</p>
                        </div>
                    @endif

                    @if (!empty($viewData['approved_by']))
                        <div class="rq-detail-section">
                            <span class="rq-detail-label">Approved By</span>
                            <p class="rq-detail-value">
                                {{ $viewData['approved_by']['first_name'] ?? '' }}
                                {{ $viewData['approved_by']['last_name'] ?? '' }}
                                @if (!empty($viewData['approval_date']))
                                    on {{ \Carbon\Carbon::parse($viewData['approval_date'])->format('d M Y') }}
                                @endif
                            </p>
                        </div>
                    @endif

                    @if (!empty($viewData['rejection_reason']))
                        <div class="rq-detail-section">
                            <span class="rq-detail-label">Rejection / Closure Reason</span>
                            <p class="rq-detail-text rq-detail-text--danger">{{ $viewData['rejection_reason'] }}</p>
                        </div>
                    @endif
                </div>
                <footer class="rq-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    @if ($this->canUpdate($viewData))
                        <button type="button" wire:click="openEdit({{ $viewData['id'] }})" class="nks-btn nks-btn--primary">
                            <i class="fa-solid fa-pen"></i> Edit
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif

    {{-- create --}}
    @if ($showCreate)
        <div class="rq-modal-backdrop" wire:click.self="closeCreate">
            <div class="rq-modal rq-modal--lg">
                <header class="rq-modal-head">
                    <h3>New Requisition</h3>
                    <button type="button" wire:click="closeCreate" class="rq-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="rq-modal-body">
                    <div class="rq-form-grid">
                        <div class="nks-field rq-form-full">
                            <label class="nks-label">Title <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.title" class="nks-input"
                                   placeholder="e.g. Senior Laravel Developer">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Department <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.department" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Employment Type <span class="nks-required">*</span></label>
                            <select wire:model="createForm.employment_type" class="nks-select">
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Employee Type Target</label>
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
                            <label class="nks-label">Experience Level</label>
                            <input type="text" wire:model="createForm.experience_level" class="nks-input"
                                   placeholder="e.g. Senior, Mid-level">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Hiring Manager</label>
                            <select wire:model="createForm.hiring_manager_id" class="nks-select">
                                <option value="">Unassigned</option>
                                @foreach ($managers as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }} — {{ ucfirst($m['role']) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Salary Min</label>
                            <input type="number" wire:model="createForm.salary_range_min" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Salary Max</label>
                            <input type="number" wire:model="createForm.salary_range_max" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Target Start Date</label>
                            <input type="date" wire:model="createForm.target_start_date" class="nks-input">
                        </div>
                        <div class="nks-field rq-form-full">
                            <label class="nks-label">Required Skills</label>
                            <input type="text" wire:model="createForm.required_skills" class="nks-input"
                                   placeholder="Comma-separated: PHP, Laravel, MySQL">
                        </div>
                        <div class="nks-field rq-form-full">
                            <label class="nks-label">Reason for Hire</label>
                            <textarea wire:model="createForm.reason_for_hire" class="nks-textarea"></textarea>
                        </div>
                    </div>
                </div>
                <footer class="rq-modal-foot">
                    <button type="button" wire:click="closeCreate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitCreate('draft')" class="nks-btn nks-btn--outline">
                        <i class="fa-solid fa-floppy-disk"></i> Save as Draft
                    </button>
                    <button type="button" wire:click="submitCreate('pending_approval')"
                            wire:loading.attr="disabled" wire:target="submitCreate"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-paper-plane"></i> Submit for Approval
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- edit --}}
    @if ($showEdit && $editId)
        <div class="rq-modal-backdrop" wire:click.self="closeEdit">
            <div class="rq-modal rq-modal--lg">
                <header class="rq-modal-head">
                    <h3>Edit Requisition</h3>
                    <button type="button" wire:click="closeEdit" class="rq-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="rq-modal-body">
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
                            <label class="nks-label">Employment Type</label>
                            <select wire:model="editForm.employment_type" class="nks-select">
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="contract">Contract</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Employee Type Target</label>
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
                            <label class="nks-label">Experience Level</label>
                            <input type="text" wire:model="editForm.experience_level" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Hiring Manager</label>
                            <select wire:model="editForm.hiring_manager_id" class="nks-select">
                                <option value="">Unassigned</option>
                                @foreach ($managers as $m)
                                    <option value="{{ $m['id'] }}">{{ $m['first_name'] }} {{ $m['last_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Salary Min</label>
                            <input type="number" wire:model="editForm.salary_range_min" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Salary Max</label>
                            <input type="number" wire:model="editForm.salary_range_max" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Target Start Date</label>
                            <input type="date" wire:model="editForm.target_start_date" class="nks-input">
                        </div>
                        <div class="nks-field rq-form-full">
                            <label class="nks-label">Required Skills</label>
                            <input type="text" wire:model="editForm.required_skills" class="nks-input">
                        </div>
                        <div class="nks-field rq-form-full">
                            <label class="nks-label">Reason for Hire</label>
                            <textarea wire:model="editForm.reason_for_hire" class="nks-textarea"></textarea>
                        </div>
                    </div>
                </div>
                <footer class="rq-modal-foot">
                    <button type="button" wire:click="closeEdit" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitEdit" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i> Save Changes
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- approve --}}
    @if ($showApprove)
        <div class="rq-modal-backdrop" wire:click.self="closeApprove">
            <div class="rq-modal">
                <header class="rq-modal-head">
                    <h3>Approve Requisition</h3>
                    <button type="button" wire:click="closeApprove" class="rq-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="rq-modal-body">
                    <p class="rq-text">
                        Approving opens this requisition for applications. Recruiters can then add candidates and schedule interviews.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Your Password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="approvePassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="rq-modal-foot">
                    <button type="button" wire:click="closeApprove" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitApprove" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i> Approve
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- reject --}}
    @if ($showReject)
        <div class="rq-modal-backdrop" wire:click.self="closeReject">
            <div class="rq-modal">
                <header class="rq-modal-head">
                    <h3>Reject Requisition</h3>
                    <button type="button" wire:click="closeReject" class="rq-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="rq-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Rejection Reason <span class="nks-required">*</span></label>
                        <textarea wire:model="rejectReason" class="nks-textarea"
                                  placeholder="Explain why this requisition is being rejected."></textarea>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Your Password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="rejectPassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="rq-modal-foot">
                    <button type="button" wire:click="closeReject" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitReject" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-xmark"></i> Reject Requisition
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- close --}}
    @if ($showClose)
        <div class="rq-modal-backdrop" wire:click.self="closeClose">
            <div class="rq-modal">
                <header class="rq-modal-head">
                    <h3>{{ $closeStatus === 'cancelled' ? 'Cancel' : 'Close' }} Requisition</h3>
                    <button type="button" wire:click="closeClose" class="rq-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="rq-modal-body">
                    <p class="rq-text">
                        {{ $closeStatus === 'cancelled'
                            ? 'Cancelling permanently ends this requisition. No further candidates can be added.'
                            : 'Closing marks this requisition as filled. Existing candidates remain visible but no new candidates can be added.' }}
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Reason (optional)</label>
                        <textarea wire:model="closeReason" class="nks-textarea"></textarea>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Your Password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="closePassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="rq-modal-foot">
                    <button type="button" wire:click="closeClose" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitClose" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-check"></i> Confirm
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>