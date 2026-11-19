<div class="page-pending-l1" wire:init="load">
    <x-nks.loading-overlay :loading="$loading" targets="load" />
    <x-nks.notice-stack :error="$error" :success="$flash" />

    @if ($loading)
        <x-nks.spinner block label="Loading pending timesheets…" />
    @else
        <div class="panel l1-panel">
            <div class="l1-header">
                <i class="fa-solid fa-check-double l1-header-icon"></i>
                <div>
                    <h2>Pending L1 Approvals</h2>
                    <p>Timesheets submitted by your team awaiting first-level approval</p>
                </div>
            </div>

            @if (count($timesheets) === 0)
                <div class="l1-empty">
                    <i class="fa-solid fa-clipboard-check"></i>
                    <strong>No timesheets pending your approval</strong>
                    <span>When a team member submits a PR timesheet, it will appear here.</span>
                </div>
            @else
                <div class="l1-table-wrapper">
                    <table class="l1-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Month</th>
                                <th>Regular</th>
                                <th>Overtime</th>
                                <th>Total</th>
                                <th>Submitted</th>
                                <th style="width: 220px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($timesheets as $ts)
                                <tr wire:key="l1-{{ $ts['id'] }}">
                                    <td>
                                        <div class="l1-employee">
                                            <div class="l1-employee-avatar">
                                                <i class="fa-solid fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="l1-employee-name">
                                                    {{ $ts['user']['first_name'] ?? '' }} {{ $ts['user']['last_name'] ?? '' }}
                                                </div>
                                                <div class="l1-employee-number">
                                                    {{ $ts['user']['employee_number'] ?? '' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($ts['month_year'])->format('F Y') }}</td>
                                    <td>{{ \App\Livewire\Employee\Timesheet::formatHours((float) $ts['total_regular_hours']) }}</td>
                                    <td>{{ \App\Livewire\Employee\Timesheet::formatHours((float) $ts['total_overtime_hours']) }}</td>
                                    <td class="l1-total">
                                        {{ \App\Livewire\Employee\Timesheet::formatHours((float) $ts['total_regular_hours'] + (float) $ts['total_overtime_hours']) }}
                                    </td>
                                    <td>
                                        {{ isset($ts['created_at']) ? \Carbon\Carbon::parse($ts['created_at'])->format('d M Y') : '—' }}
                                    </td>
                                    <td>
                                        <div class="l1-actions">
                                            <button type="button"
                                                    wire:click="view({{ $ts['id'] }})"
                                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                                <i class="fa-solid fa-eye"></i>
                                                View
                                            </button> 
                                            <button type="button"
                                                    wire:click="openApprove({{ $ts['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-check"></i>
                                                Approve
                                            </button>
                                            <button type="button"
                                                    wire:click="openOnboarding({{ $ts['user']['id'] }})"
                                                    class="nks-btn nks-btn--outline nks-btn--sm"
                                                    title="Onboarding">
                                                <i class="fa-solid fa-list-check"></i>
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
        <div class="l1-modal-backdrop" wire:click.self="closeView" @keydown.escape.window="$wire.closeView()">
            <div class="l1-modal l1-modal--lg"
                role="dialog"
                aria-modal="true"
                aria-labelledby="l1-detail-title"
                x-trap>
                <header class="l1-modal-head">
                    <h3 id="l1-detail-title">Timesheet Details</h3>
                    <button type="button"
                            wire:click="closeView"
                            class="l1-modal-close"
                            aria-label="Close dialog">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </header>
                <div class="l1-modal-body">
                    @if ($detail)
                        <div class="l1-detail-meta">
                            <div>
                                <span class="l1-detail-label">Employee</span>
                                <span class="l1-detail-value">
                                    {{ $detail['user']['first_name'] ?? '' }} {{ $detail['user']['last_name'] ?? '' }}
                                </span>
                            </div>
                            <div>
                                <span class="l1-detail-label">Month</span>
                                <span class="l1-detail-value">
                                    {{ \Carbon\Carbon::parse($detail['month_year'])->format('F Y') }}
                                </span>
                            </div>
                            <div>
                                <span class="l1-detail-label">Status</span>
                                <span class="l1-detail-value">
                                    {{ ucfirst(str_replace('_', ' ', $detail['status'] ?? '')) }}
                                </span>
                            </div>
                            <div>
                                <span class="l1-detail-label">Total Hours</span>
                                <span class="l1-detail-value">
                                    {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($viewData['total_hours'] ?? 0)) }}
                                </span>
                            </div>
                        </div>

                        <div class="l1-detail-table-wrapper">
                            <table class="l1-detail-table">
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
                <footer class="l1-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showReject)
        <div class="l1-modal-backdrop" wire:click.self="closeReject">
            <div class="l1-modal">
                <header class="l1-modal-head">
                    <h3>Reject Timesheet</h3>
                    <button type="button" wire:click="closeReject" class="l1-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="l1-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">
                            Reason for rejection <span class="nks-required">*</span>
                        </label>
                        <textarea wire:model="rejectReason"
                                  class="nks-textarea"
                                  placeholder="Provide a clear reason so the employee can correct and resubmit."></textarea>
                    </div>
                </div>
                <footer class="l1-modal-foot">
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

    @if ($showApprove)
        <div class="l1-modal-backdrop" wire:click.self="closeApprove">
            <div class="l1-modal">
                <header class="l1-modal-head">
                    <h3>Approve Timesheet</h3>
                    <button type="button" wire:click="closeApprove" class="l1-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="l1-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Comment to employee (optional)</label>
                        <textarea wire:model="approveComment"
                                  class="nks-textarea"
                                  placeholder="Any note for the employee or L2 reviewer."></textarea>
                    </div>
                </div>
                <footer class="l1-modal-foot">
                    <button type="button" wire:click="closeApprove" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitApprove"
                            wire:loading.attr="disabled" wire:target="submitApprove"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check" wire:loading.remove wire:target="submitApprove"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="submitApprove"></span>
                        Confirm Approval
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @include('partials.onboarding-modal')
</div>