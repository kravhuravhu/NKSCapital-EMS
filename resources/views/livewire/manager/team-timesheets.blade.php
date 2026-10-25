<div class="page-team-timesheets">
    @if ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($loading)
        <x-nks.spinner block label="Loading team timesheets…" />
    @else
        <div class="panel tt-panel">
            <div class="tt-header">
                <i class="fa-solid fa-clipboard-list tt-header-icon"></i>
                <div>
                    <h2>Team Timesheets</h2>
                    <p>All PR timesheets submitted across your team</p>
                </div>
                <div class="tt-header-filters">
                    <input type="month"
                           wire:model.live="month"
                           class="tt-month-input">
                </div>
            </div>

            <nav class="nks-tabs tt-tabs">
                @foreach (['all' => 'All', 'draft' => 'Draft', 'pending_l1' => 'Pending L1', 'pending_l2' => 'Pending L2', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $key => $label)
                    <button type="button"
                            wire:click="selectStatus('{{ $key }}')"
                            class="nks-tab {{ $status === $key ? 'is-active' : '' }}">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>

            @if (count($timesheets) === 0)
                <div class="tt-empty">
                    <i class="fa-solid fa-clipboard-check"></i>
                    <strong>No timesheets found</strong>
                    <span>Try adjusting the filters or month above.</span>
                </div>
            @else
                <div class="tt-table-wrapper">
                    <table class="tt-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Month</th>
                                <th>Regular</th>
                                <th>Overtime</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>L1 Approver</th>
                                <th>L2 Approver</th>
                                <th style="width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($timesheets as $ts)
                                <tr wire:key="tt-{{ $ts['id'] }}">
                                    <td>
                                        <div class="tt-employee">
                                            <div class="tt-employee-avatar">
                                                <i class="fa-solid fa-user"></i>
                                            </div>
                                            <div>
                                                <div class="tt-employee-name">
                                                    {{ $ts['user']['first_name'] ?? '' }} {{ $ts['user']['last_name'] ?? '' }}
                                                </div>
                                                <div class="tt-employee-number">
                                                    {{ $ts['user']['employee_number'] ?? '' }}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ \Carbon\Carbon::parse($ts['month_year'])->format('F Y') }}</td>
                                    <td>{{ \App\Livewire\Employee\Timesheet::formatHours((float) $ts['total_regular_hours']) }}</td>
                                    <td>{{ \App\Livewire\Employee\Timesheet::formatHours((float) $ts['total_overtime_hours']) }}</td>
                                    <td class="tt-total">
                                        {{ \App\Livewire\Employee\Timesheet::formatHours((float) $ts['total_regular_hours'] + (float) $ts['total_overtime_hours']) }}
                                    </td>
                                    <td>
                                        @php
                                            $current = $ts['status'] ?? 'draft';
                                            $tone = match ($current) {
                                                'approved' => 'success',
                                                'pending_l1', 'pending_l2' => 'gold',
                                                'rejected' => 'red',
                                                default => 'muted',
                                            };
                                        @endphp
                                        <x-nks.badge :tone="$tone">
                                            {{ ucfirst(str_replace('_', ' ', $current)) }}
                                        </x-nks.badge>
                                    </td>
                                    <td>
                                        @if (!empty($ts['level1_approver']))
                                            <span class="tt-approver">
                                                {{ $ts['level1_approver']['first_name'] ?? '' }}
                                                {{ $ts['level1_approver']['last_name'] ?? '' }}
                                            </span>
                                        @else
                                            <span class="tt-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if (!empty($ts['level2_approver']))
                                            <span class="tt-approver">
                                                {{ $ts['level2_approver']['first_name'] ?? '' }}
                                                {{ $ts['level2_approver']['last_name'] ?? '' }}
                                            </span>
                                        @else
                                            <span class="tt-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button"
                                                wire:click="view({{ $ts['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i>
                                            View
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (($pagination['last_page'] ?? 1) > 1)
                    <div class="tt-pagination">
                        <button type="button"
                                wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                                class="nks-btn nks-btn--outline nks-btn--sm"
                                @if ($pagination['current_page'] <= 1) disabled @endif>
                            <i class="fa-solid fa-chevron-left"></i>
                            Previous
                        </button>
                        <span class="tt-page-info">
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
        @php $detail = $viewData['timesheet'] ?? null; @endphp
        <div class="tt-modal-backdrop" wire:click.self="closeView">
            <div class="tt-modal tt-modal--lg">
                <header class="tt-modal-head">
                    <h3>Timesheet Details</h3>
                    <button type="button" wire:click="closeView" class="tt-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="tt-modal-body">
                    @if ($detail)
                        <div class="tt-detail-meta">
                            <div>
                                <span class="tt-detail-label">Employee</span>
                                <span class="tt-detail-value">
                                    {{ $detail['user']['first_name'] ?? '' }} {{ $detail['user']['last_name'] ?? '' }}
                                </span>
                            </div>
                            <div>
                                <span class="tt-detail-label">Month</span>
                                <span class="tt-detail-value">
                                    {{ \Carbon\Carbon::parse($detail['month_year'])->format('F Y') }}
                                </span>
                            </div>
                            <div>
                                <span class="tt-detail-label">Status</span>
                                <span class="tt-detail-value">
                                    {{ ucfirst(str_replace('_', ' ', $detail['status'] ?? '')) }}
                                </span>
                            </div>
                            <div>
                                <span class="tt-detail-label">Total Hours</span>
                                <span class="tt-detail-value">
                                    {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($viewData['total_hours'] ?? 0)) }}
                                </span>
                            </div>
                        </div>

                        <div class="tt-detail-table-wrapper">
                            <table class="tt-detail-table">
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
                <footer class="tt-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>