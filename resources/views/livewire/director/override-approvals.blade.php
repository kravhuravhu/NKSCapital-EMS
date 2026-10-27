<div class="page-override-approvals">
    @if (!$twoFactorEnabled)
        <div class="nks-notice" style="background: var(--nks-gold-tint); color: #7A6023; margin-bottom: 14px;">
            <i class="fa-solid fa-shield-halved" style="color: #7A6023;"></i>
            <div class="nks-notice-body">
                <strong style="color: #7A6023;">Two-Factor Authentication required for overrides</strong>
                Override actions require 2FA. Enable it on your Security page before overriding rejections.
            </div>
        </div>
    @endif

    @if ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($flash)
        <div class="nks-notice" style="background: var(--nks-mint); color: #1F6B45;">
            <i class="fa-solid fa-circle-check" style="color: #1F6B45;"></i>
            <div class="nks-notice-body">
                <strong style="color: #1F6B45; display: block;">{{ $flash }}</strong>
                @if ($pdfUrl)
                    <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="ov-pdf-link">
                        <i class="fa-solid fa-file-pdf"></i>
                        Download generated PDF
                    </a>
                @endif
            </div>
        </div>
    @endif

    <div class="panel ov-panel">
        <div class="ov-header">
            <i class="fa-solid fa-scale-balanced ov-header-icon"></i>
            <div>
                <h2>Override Approvals</h2>
                <p>Review rejected timesheets and override rejections with a full audit trail</p>
            </div>
        </div>

        <div class="ov-toolbar">
            <div class="ov-toolbar-search">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text"
                       wire:model.live.debounce.500ms="search"
                       class="ov-search-input"
                       placeholder="Search by name, employee number, or reason">
            </div>
            <input type="month" wire:model.live="month" class="ov-month-input">
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading rejected timesheets…" />
        @elseif (count($rows) === 0)
            <div class="ov-empty">
                <i class="fa-solid fa-scale-balanced"></i>
                <strong>No rejected timesheets</strong>
                <span>
                    Rejected timesheets will appear here so you can review the rejection and override it if necessary.
                </span>
            </div>
        @else
            <div class="ov-table-wrapper">
                <table class="ov-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Month</th>
                            <th>Total Hours</th>
                            <th>Rejected At</th>
                            <th>Source</th>
                            <th>Reason</th>
                            <th style="width: 260px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $source = \App\Livewire\Director\OverrideApprovals::rejectionSource($row);
                                $reason = \App\Livewire\Director\OverrideApprovals::rejectionReason($row);
                                $rejectedAt = $row['level2_approved_at']
                                    ?? $row['level1_approved_at']
                                    ?? $row['updated_at']
                                    ?? null;
                            @endphp
                            <tr wire:key="ov-{{ $row['id'] }}">
                                <td>
                                    <div class="ov-emp">
                                        <div class="ov-emp-avatar">
                                            <i class="fa-solid fa-user"></i>
                                        </div>
                                        <div>
                                            <div class="ov-emp-name">
                                                {{ $row['user']['first_name'] ?? '' }}
                                                {{ $row['user']['last_name'] ?? '' }}
                                            </div>
                                            <div class="ov-emp-meta">
                                                {{ $row['user']['employee_number'] ?? '' }}
                                                @if (!empty($row['user']['department']))
                                                    · {{ $row['user']['department'] }}
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ \Carbon\Carbon::parse($row['month_year'])->format('F Y') }}</td>
                                <td class="ov-hours">
                                    {{ \App\Livewire\Employee\Timesheet::formatHours((float) $row['total_regular_hours'] + (float) $row['total_overtime_hours']) }}
                                </td>
                                <td>
                                    {{ $rejectedAt ? \Carbon\Carbon::parse($rejectedAt)->format('d M Y H:i') : '—' }}
                                </td>
                                <td>
                                    <x-nks.badge :tone="$source === 'L2' ? 'red' : 'gold'">{{ $source }}</x-nks.badge>
                                </td>
                                <td>
                                    <span class="ov-reason">{{ \Illuminate\Support\Str::limit($reason, 60) }}</span>
                                </td>
                                <td>
                                    <div class="ov-actions">
                                        <button type="button"
                                                wire:click="view({{ $row['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i>
                                            View
                                        </button>
                                        <button type="button"
                                                wire:click="openOverride({{ $row['id'] }})"
                                                class="nks-btn nks-btn--primary nks-btn--sm"
                                                @if (!$twoFactorEnabled) disabled @endif>
                                            <i class="fa-solid fa-scale-balanced"></i>
                                            Override
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (($pagination['last_page'] ?? 1) > 1)
                <div class="ov-pagination">
                    <button type="button"
                            wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] <= 1) disabled @endif>
                        <i class="fa-solid fa-chevron-left"></i>
                        Previous
                    </button>
                    <span class="ov-page-info">
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

    @if ($showView && $viewData)
        @php $detail = $viewData['timesheet'] ?? null; @endphp
        <div class="ov-modal-backdrop" wire:click.self="closeView">
            <div class="ov-modal ov-modal--lg">
                <header class="ov-modal-head">
                    <h3>Rejected Timesheet Details</h3>
                    <button type="button" wire:click="closeView" class="ov-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ov-modal-body">
                    @if ($detail)
                        <div class="ov-detail-meta">
                            <div>
                                <span class="ov-detail-label">Employee</span>
                                <span class="ov-detail-value">
                                    {{ $detail['user']['first_name'] ?? '' }} {{ $detail['user']['last_name'] ?? '' }}
                                </span>
                            </div>
                            <div>
                                <span class="ov-detail-label">Month</span>
                                <span class="ov-detail-value">
                                    {{ \Carbon\Carbon::parse($detail['month_year'])->format('F Y') }}
                                </span>
                            </div>
                            <div>
                                <span class="ov-detail-label">Status</span>
                                <span class="ov-detail-value">
                                    <x-nks.badge tone="red">{{ ucfirst($detail['status'] ?? '') }}</x-nks.badge>
                                </span>
                            </div>
                            <div>
                                <span class="ov-detail-label">Total Hours</span>
                                <span class="ov-detail-value">
                                    {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($viewData['total_hours'] ?? 0)) }}
                                </span>
                            </div>
                        </div>

                        @if (!empty($detail['level1_rejection_reason']))
                            <div class="ov-rejection-note">
                                <strong>L1 Rejection</strong>
                                <p>{{ $detail['level1_rejection_reason'] }}</p>
                            </div>
                        @endif

                        @if (!empty($detail['level2_rejection_reason']))
                            <div class="ov-rejection-note ov-rejection-note--l2">
                                <strong>L2 Rejection</strong>
                                <p>{{ $detail['level2_rejection_reason'] }}</p>
                            </div>
                        @endif

                        <div class="ov-detail-table-wrapper">
                            <table class="ov-detail-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Day</th>
                                        <th>Project</th>
                                        <th>Description</th>
                                        <th style="text-align: right;">Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (($detail['details'] ?? []) as $entry)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($entry['work_date'])->format('d M Y') }}</td>
                                            <td>{{ \Carbon\Carbon::parse($entry['work_date'])->format('D') }}</td>
                                            <td>{{ $entry['project']['project_code'] ?? '—' }}</td>
                                            <td>{{ $entry['task_description'] ?? '—' }}</td>
                                            <td style="text-align: right;">
                                                {{ \App\Livewire\Employee\Timesheet::formatHours((float) $entry['hours_worked']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <footer class="ov-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                    <button type="button"
                            wire:click="openOverride({{ $detail['id'] ?? 0 }})"
                            class="nks-btn nks-btn--primary"
                            @if (!$twoFactorEnabled) disabled @endif>
                        <i class="fa-solid fa-scale-balanced"></i>
                        Override Rejection
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showOverride)
        <div class="ov-modal-backdrop" wire:click.self="closeOverride">
            <div class="ov-modal">
                <header class="ov-modal-head">
                    <h3>Override Rejection</h3>
                    <button type="button" wire:click="closeOverride" class="ov-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ov-modal-body">
                    <div class="ov-2fa-notice">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>
                            Overriding a rejection is a privileged action — enter your 6-digit 2FA code and provide a written justification.
                        </span>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Two-Factor Code <span class="nks-required">*</span>
                        </label>
                        <input type="text"
                               wire:model="overrideCode"
                               inputmode="numeric"
                               pattern="[0-9]{6}"
                               maxlength="6"
                               autocomplete="one-time-code"
                               placeholder="000000"
                               class="ov-2fa-input">
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Override Justification <span class="nks-required">*</span>
                        </label>
                        <textarea wire:model="overrideComment"
                                  class="nks-textarea"
                                  placeholder="Explain why the rejection is being overridden. This is written to the audit log."></textarea>
                    </div>
                </div>
                <footer class="ov-modal-foot">
                    <button type="button" wire:click="closeOverride" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="submitOverride"
                            wire:loading.attr="disabled"
                            wire:target="submitOverride"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check-double"></i>
                        Confirm Override
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>