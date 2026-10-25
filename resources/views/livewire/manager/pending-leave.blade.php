<div class="page-pending-leave">
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
        <x-nks.spinner block label="Loading pending leave requests…" />
    @else
        <div class="panel pl-panel">
            <div class="pl-header">
                <i class="fa-solid fa-umbrella-beach pl-header-icon"></i>
                <div>
                    <h2>Pending Leave Requests</h2>
                    <p>Leave requests from your team awaiting your decision</p>
                </div>
            </div>

            @if (count($requests) === 0)
                <div class="pl-empty">
                    <i class="fa-solid fa-calendar-check"></i>
                    <strong>No pending leave requests</strong>
                    <span>When your team members request leave, it will appear here for review.</span>
                </div>
            @else
                <div class="pl-table-wrapper">
                    <table class="pl-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Leave Type</th>
                                <th>Dates</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Reason</th>
                                <th style="width: 220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($requests as $row)
                                <tr wire:key="pl-{{ $row['id'] }}">
                                    <td>
                                        <div class="pl-employee">
                                            <div class="pl-employee-avatar">
                                                <i class="fa-solid fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="pl-employee-name">
                                                    {{ $row['user']['first_name'] ?? '' }} {{ $row['user']['last_name'] ?? '' }}
                                                </div>
                                                <div class="pl-employee-meta">
                                                    {{ $row['user']['employee_number'] ?? '' }}
                                                    @if (!empty($row['user']['department']))
                                                        · {{ $row['user']['department'] }}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="pl-type">
                                            <i class="{{ \App\Livewire\Manager\PendingLeave::leaveIcon($row['leave_type'] ?? '') }}"></i>
                                            <span>{{ ucfirst($row['leave_type'] ?? '—') }}</span>
                                            @if ($this->requiresProofAfter($row))
                                                <span class="pl-proof-hint" title="Proof upload required after approval">
                                                    <i class="fa-solid fa-paperclip"></i>
                                                </span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        {{ \Carbon\Carbon::parse($row['start_date'])->format('d M Y') }}
                                        <span class="pl-date-sep">→</span>
                                        {{ \Carbon\Carbon::parse($row['end_date'])->format('d M Y') }}
                                    </td>
                                    <td class="pl-days">{{ (float) ($row['days_taken'] ?? 0) }}</td>
                                    <td>
                                        <x-nks.badge :tone="\App\Livewire\Manager\PendingLeave::statusTone($row['status'] ?? 'pending')">
                                            {{ ucfirst($row['status'] ?? 'pending') }}
                                        </x-nks.badge>
                                    </td>
                                    <td>
                                        <span class="pl-reason">{{ \Illuminate\Support\Str::limit($row['reason'] ?? '—', 60) }}</span>
                                    </td>$type = $row['leave_type'] ?? null;
                                    <td>
                                        <div class="pl-actions">
                                            <button type="button"
                                                    wire:click="view({{ $row['id'] }})"
                                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                                <i class="fa-solid fa-eye"></i>
                                                View
                                            </button>
                                            <button type="button"
                                                    wire:click="approve({{ $row['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-check"></i>
                                                Approve
                                            </button>
                                            @if ($this->canPartial($row))
                                                <button type="button"
                                                        wire:click="openPartial({{ $row['id'] }})"
                                                        class="nks-btn nks-btn--outline nks-btn--sm">
                                                    <i class="fa-solid fa-scissors"></i>
                                                    Partial
                                                </button>
                                            @endif
                                            <button type="button"
                                                    wire:click="openReject({{ $row['id'] }})"
                                                    class="nks-btn nks-btn--danger nks-btn--sm">
                                                <i class="fa-solid fa-xmark"></i>
                                                Reject
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (($pagination['last_page'] ?? 1) > 1)
                    <div class="pl-pagination">
                        <button type="button"
                                wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                                class="nks-btn nks-btn--outline nks-btn--sm"
                                @if ($pagination['current_page'] <= 1) disabled @endif>
                            <i class="fa-solid fa-chevron-left"></i>
                            Previous
                        </button>
                        <span class="pl-page-info">
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
    @endif

    @if ($showView && $viewData)
        <div class="pl-modal-backdrop" wire:click.self="closeView">
            <div class="pl-modal pl-modal--lg">
                <header class="pl-modal-head">
                    <h3>Leave Request Details</h3>
                    <button type="button" wire:click="closeView" class="pl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="pl-modal-body">
                    <div class="pl-detail-meta">
                        <div>
                            <span class="pl-detail-label">Employee</span>
                            <span class="pl-detail-value">
                                {{ $viewData['user']['first_name'] ?? '' }} {{ $viewData['user']['last_name'] ?? '' }}
                            </span>
                        </div>
                        <div>
                            <span class="pl-detail-label">Leave Type</span>
                            <span class="pl-detail-value">
                                {{ ucfirst($viewData['leave_type'] ?? '—') }}
                            </span>
                        </div>
                        <div>
                            <span class="pl-detail-label">Dates</span>
                            <span class="pl-detail-value">
                                {{ \Carbon\Carbon::parse($viewData['start_date'])->format('d M Y') }}
                                →
                                {{ \Carbon\Carbon::parse($viewData['end_date'])->format('d M Y') }}
                            </span>
                        </div>
                        <div>
                            <span class="pl-detail-label">Days</span>
                            <span class="pl-detail-value">{{ (float) ($viewData['days_taken'] ?? 0) }}</span>
                        </div>
                    </div>

                    <div class="pl-detail-section">
                        <span class="pl-detail-label">Reason</span>
                        <p class="pl-detail-text">{{ $viewData['reason'] ?? '—' }}</p>
                    </div>

                    @if (!empty($viewData['attachment_path']))
                        <div class="pl-detail-section">
                            <span class="pl-detail-label">Attachment</span>
                            <a href="{{ asset('storage/' . $viewData['attachment_path']) }}"
                               target="_blank"
                               class="nks-btn nks-btn--outline nks-btn--sm">
                                <i class="fa-solid fa-paperclip"></i>
                                View Attachment
                            </a>
                        </div>
                    @endif
                </div>
                <footer class="pl-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                    <button type="button"
                            wire:click="approve({{ $viewData['id'] }})"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Approve
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showReject)
        <div class="pl-modal-backdrop" wire:click.self="closeReject">
            <div class="pl-modal">
                <header class="pl-modal-head">
                    <h3>Reject Leave Request</h3>
                    <button type="button" wire:click="closeReject" class="pl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="pl-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">
                            Reason <span class="nks-required">*</span>
                        </label>
                        <textarea wire:model="rejectReason"
                                  class="nks-textarea"
                                  placeholder="Explain why this request is being rejected."></textarea>
                    </div>
                </div>
                <footer class="pl-modal-foot">
                    <button type="button" wire:click="closeReject" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitReject" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-xmark"></i>
                        Confirm Rejection
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showPartial)
        <div class="pl-modal-backdrop" wire:click.self="closePartial">
            <div class="pl-modal">
                <header class="pl-modal-head">
                    <h3>Partially Approve Leave</h3>
                    <button type="button" wire:click="closePartial" class="pl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="pl-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">
                            Approved Days <span class="nks-required">*</span>
                        </label>
                        <input type="number"
                               wire:model="partialDays"
                               min="0.5"
                               max="{{ $partialMax - 1 }}"
                               step="0.5"
                               class="nks-input">
                        <span class="nks-help">Max {{ $partialMax }} — use Approve for the full request.</span>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Comment (optional)</label>
                        <textarea wire:model="partialComment"
                                  class="nks-textarea"
                                  placeholder="Explain the partial approval."></textarea>
                    </div>
                </div>
                <footer class="pl-modal-foot">
                    <button type="button" wire:click="closePartial" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitPartial" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-scissors"></i>
                        Confirm Partial
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>