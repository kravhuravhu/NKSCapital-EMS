<div class="page-delegations">
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

    <div class="dl-stats">
        <div class="dl-stat dl-stat--success">
            <div class="dl-stat-head"><i class="fa-solid fa-circle-check"></i><span>Active</span></div>
            <div class="dl-stat-value">{{ $stats['active'] }}</div>
            <div class="dl-stat-note">Currently delegating</div>
        </div>
        <div class="dl-stat dl-stat--blue">
            <div class="dl-stat-head"><i class="fa-solid fa-hourglass-start"></i><span>Upcoming</span></div>
            <div class="dl-stat-value">{{ $stats['upcoming'] }}</div>
            <div class="dl-stat-note">Scheduled to start</div>
        </div>
        <div class="dl-stat">
            <div class="dl-stat-head"><i class="fa-solid fa-calendar-xmark"></i><span>Expired</span></div>
            <div class="dl-stat-value">{{ $stats['expired'] }}</div>
            <div class="dl-stat-note">Past end date</div>
        </div>
        <div class="dl-stat dl-stat--danger">
            <div class="dl-stat-head"><i class="fa-solid fa-ban"></i><span>Revoked</span></div>
            <div class="dl-stat-value">{{ $stats['revoked'] }}</div>
            <div class="dl-stat-note">Manually ended</div>
        </div>
    </div>

    <div class="panel dl-panel">
        <div class="dl-header">
            <i class="fa-solid fa-user-cog dl-header-icon"></i>
            <div>
                <h2>Delegations</h2>
                <p>Assign your L2 approvals to a trusted peer while you are away</p>
            </div>
            <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary dl-create-btn">
                <i class="fa-solid fa-plus"></i>
                New Delegation
            </button>
        </div>

        <div class="dl-views">
            <button type="button"
                    wire:click="selectView('mine')"
                    class="dl-view {{ $view === 'mine' ? 'is-active' : '' }}">
                <i class="fa-solid fa-arrow-up-from-bracket"></i>
                Given by me
            </button>
            <button type="button"
                    wire:click="selectView('received')"
                    class="dl-view {{ $view === 'received' ? 'is-active' : '' }}">
                <i class="fa-solid fa-arrow-down-to-bracket"></i>
                Received by me
            </button>
            <button type="button"
                    wire:click="selectView('all')"
                    class="dl-view {{ $view === 'all' ? 'is-active' : '' }}">
                <i class="fa-solid fa-globe"></i>
                All delegations
            </button>
        </div>

        <nav class="nks-tabs dl-tabs">
            @foreach (['active' => 'Active', 'upcoming' => 'Upcoming', 'expired' => 'Expired', 'revoked' => 'Revoked', 'all' => 'All'] as $key => $label)
                <button type="button"
                        wire:click="selectStatus('{{ $key }}')"
                        class="nks-tab {{ $status === $key ? 'is-active' : '' }}">
                    {{ $label }}
                </button>
            @endforeach
        </nav>

        @if ($loading)
            <x-nks.spinner block label="Loading delegations…" />
        @elseif (count($delegations) === 0)
            <div class="dl-empty">
                <i class="fa-solid fa-user-cog"></i>
                <strong>No {{ $status === 'all' ? '' : $status }} delegations</strong>
                <span>
                    @if ($status === 'active' && $view === 'mine')
                        You have not delegated your approvals. Click <strong>New Delegation</strong> to assign them to a peer.
                    @else
                        Try switching the view, status, or creating a new delegation.
                    @endif
                </span>
            </div>
        @else
            <div class="dl-table-wrapper">
                <table class="dl-table">
                    <thead>
                        <tr>
                            <th>Delegate</th>
                            <th>Original Approver</th>
                            <th>Type</th>
                            <th>Period</th>
                            <th>Status</th>
                            <th style="width: 220px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($delegations as $d)
                            @php $rowStatus = \App\Livewire\Director\Delegations::statusOf($d); @endphp
                            <tr wire:key="dl-{{ $d['id'] }}">
                                <td>
                                    <div class="dl-person">
                                        <div class="dl-person-avatar">
                                            <i class="fa-solid fa-user"></i>
                                        </div>
                                        <div>
                                            <div class="dl-person-name">
                                                {{ $d['delegate']['first_name'] ?? '' }}
                                                {{ $d['delegate']['last_name'] ?? '' }}
                                            </div>
                                            <div class="dl-person-role">{{ ucfirst($d['delegate']['role'] ?? '') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="dl-person-name">
                                        {{ $d['original_approver']['first_name'] ?? '' }}
                                        {{ $d['original_approver']['last_name'] ?? '' }}
                                    </div>
                                    <div class="dl-person-role">{{ ucfirst($d['original_approver']['role'] ?? '') }}</div>
                                </td>
                                <td>
                                    <x-nks.badge tone="gold">
                                        {{ ucfirst($d['delegation_type'] ?? 'both') }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    <div class="dl-period">
                                        {{ \Carbon\Carbon::parse($d['start_date'])->format('d M Y') }}
                                        <span class="dl-period-sep">→</span>
                                        {{ \Carbon\Carbon::parse($d['end_date'])->format('d M Y') }}
                                    </div>
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Director\Delegations::statusTone($rowStatus)">
                                        {{ ucfirst($rowStatus) }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    <div class="dl-actions">
                                        <button type="button"
                                                wire:click="view({{ $d['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i>
                                            View
                                        </button>
                                        @if ($this->canActivate($d))
                                            <button type="button"
                                                    wire:click="activate({{ $d['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-play"></i>
                                                Activate
                                            </button>
                                        @endif
                                        @if ($this->canRevoke($d))
                                            <button type="button"
                                                    wire:click="openRevoke({{ $d['id'] }})"
                                                    class="nks-btn nks-btn--danger nks-btn--sm">
                                                <i class="fa-solid fa-ban"></i>
                                                Revoke
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
                <div class="dl-pagination">
                    <button type="button"
                            wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] <= 1) disabled @endif>
                        <i class="fa-solid fa-chevron-left"></i>
                        Previous
                    </button>
                    <span class="dl-page-info">
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

    @if ($showCreate)
        <div class="dl-modal-backdrop" wire:click.self="closeCreate">
            <div class="dl-modal">
                <header class="dl-modal-head">
                    <h3>New Delegation</h3>
                    <button type="button" wire:click="closeCreate" class="dl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="dl-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">
                            Original Approver <span class="nks-required">*</span>
                        </label>
                        <select wire:model.live="createForm.original_approver_id" class="nks-select">
                            @foreach ($approvers as $u)
                                <option value="{{ $u['id'] }}">
                                    {{ $u['first_name'] }} {{ $u['last_name'] }}
                                    @if ((int) $u['id'] === (int) auth()->id()) — you @endif
                                </option>
                            @endforeach
                        </select>
                        <span class="nks-help">The person whose approvals are being delegated. Defaults to yourself.</span>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Delegate <span class="nks-required">*</span>
                        </label>
                        <select wire:model="createForm.delegate_id" class="nks-select">
                            <option value="">Select who will receive your approvals</option>
                            @foreach ($delegates as $u)
                                @continue((int) $u['id'] === (int) $createForm['original_approver_id'])
                                <option value="{{ $u['id'] }}">
                                    {{ $u['first_name'] }} {{ $u['last_name'] }} — {{ ucfirst($u['role']) }}
                                </option>
                            @endforeach
                        </select>
                        <span class="nks-help">Must be a manager, director, or admin. Employees cannot be delegates.</span>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Delegation Type <span class="nks-required">*</span>
                        </label>
                        <select wire:model="createForm.delegation_type" class="nks-select">
                            <option value="both">Both — Timesheets and Leave</option>
                            <option value="timesheet">Timesheet approvals only</option>
                            <option value="leave">Leave approvals only</option>
                        </select>
                    </div>

                    <div class="dl-form-row">
                        <div class="nks-field">
                            <label class="nks-label">
                                Start Date <span class="nks-required">*</span>
                            </label>
                            <input type="date" wire:model="createForm.start_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">
                                End Date <span class="nks-required">*</span>
                            </label>
                            <input type="date" wire:model="createForm.end_date" class="nks-input">
                        </div>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Title (optional)</label>
                        <input type="text"
                               wire:model="createForm.title"
                               class="nks-input"
                               placeholder="Annual leave — 15 to 22 November">
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Reason (optional)</label>
                        <textarea wire:model="createForm.reason"
                                  class="nks-textarea"
                                  placeholder="Reason for delegating, visible in the audit trail."></textarea>
                    </div>

                    <label class="dl-checkbox">
                        <input type="checkbox" wire:model="createForm.auto_activate">
                        <span>Auto-activate on the start date</span>
                    </label>
                </div>
                <footer class="dl-modal-foot">
                    <button type="button" wire:click="closeCreate" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitCreate" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Create Delegation
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showRevoke)
        <div class="dl-modal-backdrop" wire:click.self="closeRevoke">
            <div class="dl-modal">
                <header class="dl-modal-head">
                    <h3>Revoke Delegation</h3>
                    <button type="button" wire:click="closeRevoke" class="dl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="dl-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">
                            Reason <span class="nks-required">*</span>
                        </label>
                        <textarea wire:model="revokeReason"
                                  class="nks-textarea"
                                  placeholder="Explain why the delegation is being revoked."></textarea>
                    </div>
                </div>
                <footer class="dl-modal-foot">
                    <button type="button" wire:click="closeRevoke" class="nks-btn nks-btn--outline">
                        Keep Delegation
                    </button>
                    <button type="button" wire:click="submitRevoke" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-ban"></i>
                        Confirm Revoke
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showView && $viewData)
        @php $rowStatus = \App\Livewire\Director\Delegations::statusOf($viewData); @endphp
        <div class="dl-modal-backdrop" wire:click.self="closeView">
            <div class="dl-modal dl-modal--lg">
                <header class="dl-modal-head">
                    <div>
                        <h3>Delegation Details</h3>
                        <span class="dl-modal-sub">
                            {{ $viewData['title'] ?? 'Untitled delegation' }}
                        </span>
                    </div>
                    <button type="button" wire:click="closeView" class="dl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="dl-modal-body">
                    <div class="dl-detail-grid">
                        <div>
                            <span class="dl-detail-label">Status</span>
                            <span class="dl-detail-value">
                                <x-nks.badge :tone="\App\Livewire\Director\Delegations::statusTone($rowStatus)">
                                    {{ ucfirst($rowStatus) }}
                                </x-nks.badge>
                            </span>
                        </div>
                        <div>
                            <span class="dl-detail-label">Type</span>
                            <span class="dl-detail-value">{{ ucfirst($viewData['delegation_type'] ?? '—') }}</span>
                        </div>
                        <div>
                            <span class="dl-detail-label">Start</span>
                            <span class="dl-detail-value">
                                {{ !empty($viewData['start_date']) ? \Carbon\Carbon::parse($viewData['start_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="dl-detail-label">End</span>
                            <span class="dl-detail-value">
                                {{ !empty($viewData['end_date']) ? \Carbon\Carbon::parse($viewData['end_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="dl-detail-label">Original Approver</span>
                            <span class="dl-detail-value">
                                {{ $viewData['original_approver']['first_name'] ?? '' }}
                                {{ $viewData['original_approver']['last_name'] ?? '' }}
                            </span>
                        </div>
                        <div>
                            <span class="dl-detail-label">Delegate</span>
                            <span class="dl-detail-value">
                                {{ $viewData['delegate']['first_name'] ?? '' }}
                                {{ $viewData['delegate']['last_name'] ?? '' }}
                            </span>
                        </div>
                        <div>
                            <span class="dl-detail-label">Created By</span>
                            <span class="dl-detail-value">
                                {{ $viewData['created_by']['first_name'] ?? '—' }}
                                {{ $viewData['created_by']['last_name'] ?? '' }}
                            </span>
                        </div>
                        <div>
                            <span class="dl-detail-label">Auto-Activate</span>
                            <span class="dl-detail-value">{{ !empty($viewData['auto_activate']) ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>

                    @if (!empty($viewData['reason']))
                        <div class="dl-detail-section">
                            <span class="dl-detail-label">Reason</span>
                            <p class="dl-detail-text">{{ $viewData['reason'] }}</p>
                        </div>
                    @endif

                    @if (!empty($viewData['revoked_at']))
                        <div class="dl-detail-section">
                            <span class="dl-detail-label">Revoked</span>
                            <p class="dl-detail-text">
                                Revoked on
                                {{ \Carbon\Carbon::parse($viewData['revoked_at'])->format('d M Y H:i') }}
                                @if (!empty($viewData['revoked_by']))
                                    by
                                    {{ $viewData['revoked_by']['first_name'] ?? '' }}
                                    {{ $viewData['revoked_by']['last_name'] ?? '' }}
                                @endif
                            </p>
                            @if (!empty($viewData['revoke_reason']))
                                <p class="dl-detail-text">{{ $viewData['revoke_reason'] }}</p>
                            @endif
                        </div>
                    @endif
                </div>
                <footer class="dl-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                    @if ($this->canRevoke($viewData))
                        <button type="button"
                                wire:click="openRevoke({{ $viewData['id'] }})"
                                class="nks-btn nks-btn--danger">
                            <i class="fa-solid fa-ban"></i>
                            Revoke
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif
</div>