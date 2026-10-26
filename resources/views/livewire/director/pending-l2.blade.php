<div class="page-pending-l2">
    @if (!$twoFactorEnabled)
        <div class="nks-notice" style="background: var(--nks-gold-tint); color: #7A6023; margin-bottom: 14px;">
            <i class="fa-solid fa-shield-halved" style="color: #7A6023;"></i>
            <div class="nks-notice-body">
                <strong style="color: #7A6023;">Two-Factor Authentication required</strong>
                L2 approval requires 2FA.
                @if (\Illuminate\Support\Facades\Route::has('portal.employee.security'))
                    <a href="{{ route('portal.employee.security') }}" class="l2-2fa-link">
                        Enable 2FA on your Security page
                    </a>
                    before approving timesheets.
                @else
                    Contact your administrator to enable 2FA on your account before approving timesheets.
                @endif
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
                    <a href="{{ $pdfUrl }}" target="_blank" rel="noopener" class="l2-pdf-link">
                        <i class="fa-solid fa-file-pdf"></i>
                        Download generated PDF
                    </a>
                @endif
            </div>
        </div>
    @endif

    @if ($loading)
        <x-nks.spinner block label="Loading pending L2 approvals…" />
    @else
        <div class="panel l2-panel">
            <div class="l2-header">
                <i class="fa-solid fa-certificate l2-header-icon"></i>
                <div>
                    <h2>Pending L2 Approvals</h2>
                    <p>Timesheets approved by L1 awaiting your final approval</p>
                </div>
            </div>

            @if (count($timesheets) === 0)
                <div class="l2-empty">
                    <i class="fa-solid fa-clipboard-check"></i>
                    <strong>No timesheets pending your approval</strong>
                    <span>When a Project Manager approves a timesheet at L1, it will appear here for final sign-off.</span>
                </div>
            @else
                <div class="l2-table-wrapper">
                    <table class="l2-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Month</th>
                                <th>Regular</th>
                                <th>Overtime</th>
                                <th>Total</th>
                                <th>L1 Approved</th>
                                <th style="width: 220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($timesheets as $ts)
                                <tr wire:key="l2-{{ $ts['id'] }}">
                                    <td>
                                        <div class="l2-employee">
                                            <div class="l2-employee-avatar">
                                                <i class="fa-solid fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="l2-employee-name">
                                                    {{ $ts['user']['first_name'] ?? '' }} {{ $ts['user']['last_name'] ?? '' }}
                                                </div>
                                                <div class="l2-employee-number">
                                                    {{ $ts['user']['employee_number'] ?? '' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($ts['month_year'])->format('F Y') }}</td>
                                    <td>{{ \App\Livewire\Employee\Timesheet::formatHours((float) $ts['total_regular_hours']) }}</td>
                                    <td>{{ \App\Livewire\Employee\Timesheet::formatHours((float) $ts['total_overtime_hours']) }}</td>
                                    <td class="l2-total">
                                        {{ \App\Livewire\Employee\Timesheet::formatHours((float) $ts['total_regular_hours'] + (float) $ts['total_overtime_hours']) }}
                                    </td>
                                    <td>
                                        @if (!empty($ts['level1_approver']))
                                            <div class="l2-approver">
                                                {{ $ts['level1_approver']['first_name'] ?? '' }}
                                                {{ $ts['level1_approver']['last_name'] ?? '' }}
                                            </div>
                                            @if (!empty($ts['level1_approved_at']))
                                                <div class="l2-approver-date">
                                                    {{ \Carbon\Carbon::parse($ts['level1_approved_at'])->format('d M Y H:i') }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="l2-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="l2-actions">
                                            <button type="button"
                                                    wire:click="view({{ $ts['id'] }})"
                                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                                <i class="fa-solid fa-eye"></i>
                                                View
                                            </button>
                                            <button type="button"
                                                    wire:click="openApprove({{ $ts['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm"
                                                    @if (!$twoFactorEnabled) disabled @endif>
                                                <i class="fa-solid fa-check-double"></i>
                                                Approve
                                            </button>
                                            <button type="button"
                                                    wire:click="openReject({{ $ts['id'] }})"
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
            @endif
        </div>
    @endif

    @if ($showView && $viewData)
        @php $detail = $viewData['timesheet'] ?? null; @endphp
        <div class="l2-modal-backdrop" wire:click.self="closeView">
            <div class="l2-modal l2-modal--lg">
                <header class="l2-modal-head">
                    <h3>Timesheet Details</h3>
                    <button type="button" wire:click="closeView" class="l2-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="l2-modal-body">
                    @if ($detail)
                        <div class="l2-detail-meta">
                            <div>
                                <span class="l2-detail-label">Employee</span>
                                <span class="l2-detail-value">
                                    {{ $detail['user']['first_name'] ?? '' }} {{ $detail['user']['last_name'] ?? '' }}
                                </span>
                            </div>
                            <div>
                                <span class="l2-detail-label">Month</span>
                                <span class="l2-detail-value">
                                    {{ \Carbon\Carbon::parse($detail['month_year'])->format('F Y') }}
                                </span>
                            </div>
                            <div>
                                <span class="l2-detail-label">Status</span>
                                <span class="l2-detail-value">
                                    {{ ucfirst(str_replace('_', ' ', $detail['status'] ?? '')) }}
                                </span>
                            </div>
                            <div>
                                <span class="l2-detail-label">Total Hours</span>
                                <span class="l2-detail-value">
                                    {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($viewData['total_hours'] ?? 0)) }}
                                </span>
                            </div>
                        </div>

                        <div class="l2-detail-table-wrapper">
                            <table class="l2-detail-table">
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
                                    @foreach (($detail['details'] ?? []) as $row)
                                        <tr>
                                            <td>{{ \Carbon\Carbon::parse($row['work_date'])->format('d M Y') }}</td>
                                            <td>{{ \Carbon\Carbon::parse($row['work_date'])->format('D') }}</td>
                                            <td>{{ $row['project']['project_code'] ?? '—' }}</td>
                                            <td>{{ $row['task_description'] ?? '—' }}</td>
                                            <td style="text-align: right;">
                                                {{ \App\Livewire\Employee\Timesheet::formatHours((float) $row['hours_worked']) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <footer class="l2-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                    <button type="button"
                            wire:click="openApprove({{ $detail['id'] ?? 0 }})"
                            class="nks-btn nks-btn--primary"
                            @if (!$twoFactorEnabled) disabled @endif>
                        <i class="fa-solid fa-check-double"></i>
                        Approve
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showApprove)
        <div class="l2-modal-backdrop" wire:click.self="closeApprove">
            <div class="l2-modal">
                <header class="l2-modal-head">
                    <h3>Confirm L2 Approval</h3>
                    <button type="button" wire:click="closeApprove" class="l2-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="l2-modal-body">
                    <div class="l2-2fa-notice">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>
                            Enter the 6-digit code from your authenticator app to complete this approval.
                        </span>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Two-Factor Code <span class="nks-required">*</span>
                        </label>
                        <input type="text"
                               wire:model="twoFactorCode"
                               inputmode="numeric"
                               pattern="[0-9]{6}"
                               maxlength="6"
                               autocomplete="one-time-code"
                               placeholder="000000"
                               class="l2-2fa-input">
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Comment (optional)</label>
                        <textarea wire:model="approveComment"
                                  class="nks-textarea"
                                  placeholder="Add a note for the employee or payroll."></textarea>
                    </div>
                </div>
                <footer class="l2-modal-foot">
                    <button type="button" wire:click="closeApprove" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitApprove" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check-double"></i>
                        Confirm Approval
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showReject)
        <div class="l2-modal-backdrop" wire:click.self="closeReject">
            <div class="l2-modal">
                <header class="l2-modal-head">
                    <h3>Reject Timesheet</h3>
                    <button type="button" wire:click="closeReject" class="l2-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="l2-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">
                            Reason <span class="nks-required">*</span>
                        </label>
                        <textarea wire:model="rejectReason"
                                  class="nks-textarea"
                                  placeholder="Explain why this timesheet is being rejected."></textarea>
                    </div>
                </div>
                <footer class="l2-modal-foot">
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
</div>