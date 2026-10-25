<div class="page-team-reports">
    @if ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    <div class="panel rp-panel">
        <div class="rp-header">
            <i class="fa-solid fa-chart-column rp-header-icon"></i>
            <div>
                <h2>Team Reports</h2>
                <p>
                    {{ $report['manager']['first_name'] ?? '' }}
                    {{ $report['manager']['last_name'] ?? '' }}&#39;s team
                    @if (!empty($report['team_size']))
                        — {{ $report['team_size'] }} {{ \Illuminate\Support\Str::plural('employee', (int) $report['team_size']) }}
                    @endif
                </p>
            </div>
        </div>

        <div class="rp-filters">
            <div class="rp-presets">
                <button type="button" wire:click="presetPeriod('this_month')" class="rp-preset">This Month</button>
                <button type="button" wire:click="presetPeriod('last_month')" class="rp-preset">Last Month</button>
                <button type="button" wire:click="presetPeriod('this_quarter')" class="rp-preset">This Quarter</button>
                <button type="button" wire:click="presetPeriod('this_year')" class="rp-preset">This Year</button>
            </div>

            <div class="rp-dates">
                <input type="date" wire:model="from" class="rp-date-input">
                <span class="rp-date-sep">to</span>
                <input type="date" wire:model="to" class="rp-date-input">
                <button type="button" wire:click="applyPeriod" class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-filter"></i>
                    Apply
                </button>
            </div>

            <div class="rp-exports">
                <button type="button"
                        wire:click="export('excel')"
                        wire:loading.attr="disabled"
                        wire:target="export"
                        class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-file-excel"></i>
                    Excel
                </button>
                <button type="button"
                        wire:click="export('pdf')"
                        wire:loading.attr="disabled"
                        wire:target="export"
                        class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-file-pdf"></i>
                    PDF
                </button>
            </div>
        </div>

        @if ($lastExport)
            <div class="rp-export-banner">
                <div class="rp-export-banner-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="rp-export-banner-body">
                    <strong>Report exported</strong>
                    <span>
                        {{ $lastExport['record_count'] ?? 0 }} records
                        — {{ strtoupper($lastExport['format'] ?? 'FILE') }}
                    </span>
                </div>
                <a href="{{ $lastExport['download_url'] }}"
                   download
                   class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-download"></i>
                    Download
                </a>
            </div>
        @endif

        @if ($loading)
            <x-nks.spinner block label="Loading team report…" />
        @else
            @php
                $summary = $report['summary'] ?? [];
            @endphp

            <div class="rp-stats">
                <div class="rp-stat">
                    <div class="rp-stat-icon"><i class="fa-solid fa-users"></i></div>
                    <div class="rp-stat-body">
                        <div class="rp-stat-label">Team Size</div>
                        <div class="rp-stat-value">{{ $report['team_size'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="rp-stat">
                    <div class="rp-stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                    <div class="rp-stat-body">
                        <div class="rp-stat-label">Timesheets</div>
                        <div class="rp-stat-value">{{ $summary['total_timesheets'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="rp-stat rp-stat--success">
                    <div class="rp-stat-icon"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="rp-stat-body">
                        <div class="rp-stat-label">Approved</div>
                        <div class="rp-stat-value">{{ $summary['total_approved'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="rp-stat">
                    <div class="rp-stat-icon"><i class="fa-solid fa-clock"></i></div>
                    <div class="rp-stat-body">
                        <div class="rp-stat-label">Regular Hours</div>
                        <div class="rp-stat-value">
                            {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($summary['total_regular_hours'] ?? 0)) }}
                        </div>
                    </div>
                </div>
                <div class="rp-stat rp-stat--purple">
                    <div class="rp-stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                    <div class="rp-stat-body">
                        <div class="rp-stat-label">Overtime</div>
                        <div class="rp-stat-value">
                            {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($summary['total_overtime_hours'] ?? 0)) }}
                        </div>
                    </div>
                </div>
                <div class="rp-stat rp-stat--gold">
                    <div class="rp-stat-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
                    <div class="rp-stat-body">
                        <div class="rp-stat-label">Leave Days</div>
                        <div class="rp-stat-value">
                            {{ \App\Livewire\Manager\TeamReports::formatDays((float) ($summary['total_leave_days'] ?? 0)) }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="rp-content">
                <div class="rp-table-panel">
                    <h3 class="rp-section-title">Per-Employee Breakdown</h3>

                    @if (empty($report['by_employee']))
                        <div class="rp-empty">
                            <i class="fa-solid fa-users-slash"></i>
                            <strong>No team activity</strong>
                            <span>No timesheets or leave requests were submitted in this period.</span>
                        </div>
                    @else
                        <div class="rp-table-wrapper">
                            <table class="rp-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Submitted</th>
                                        <th>Approved</th>
                                        <th>Pending</th>
                                        <th>Regular</th>
                                        <th>Overtime</th>
                                        <th>Approval Rate</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($report['by_employee'] as $emp)
                                        <tr wire:key="rp-emp-{{ $emp['employee']['id'] ?? loop.index }}">
                                            <td>
                                                <div class="rp-emp-name">
                                                    {{ $emp['employee']['first_name'] ?? '' }}
                                                    {{ $emp['employee']['last_name'] ?? '' }}
                                                </div>
                                                <div class="rp-emp-meta">
                                                    {{ $emp['employee']['employee_number'] ?? '' }}
                                                    @if (!empty($emp['employee']['position']))
                                                        · {{ $emp['employee']['position'] }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td>{{ $emp['timesheets_submitted'] ?? 0 }}</td>
                                            <td><span class="rp-num rp-num--success">{{ $emp['approved'] ?? 0 }}</span></td>
                                            <td><span class="rp-num rp-num--warning">{{ $emp['pending'] ?? 0 }}</span></td>
                                            <td>{{ \App\Livewire\Employee\Timesheet::formatHours((float) ($emp['total_regular_hours'] ?? 0)) }}</td>
                                            <td>{{ \App\Livewire\Employee\Timesheet::formatHours((float) ($emp['total_overtime_hours'] ?? 0)) }}</td>
                                            <td>
                                                <div class="rp-rate">
                                                    <div class="rp-rate-bar">
                                                        <div class="rp-rate-fill"
                                                             style="width: {{ min(100, (float) ($emp['approval_rate'] ?? 0)) }}%"></div>
                                                    </div>
                                                    <span class="rp-rate-text">{{ number_format((float) ($emp['approval_rate'] ?? 0), 0) }}%</span>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="rp-sidebar">
                    <div class="panel">
                        <h2>Leave Breakdown</h2>
                        <ul class="rp-leave-list">
                            @foreach ([
                                'annual' => ['label' => 'Annual', 'icon' => 'fa-umbrella-beach', 'tone' => 'success'],
                                'sick'   => ['label' => 'Sick',   'icon' => 'fa-thermometer-half', 'tone' => 'red'],
                                'family' => ['label' => 'Family', 'icon' => 'fa-users',            'tone' => 'blue'],
                                'unpaid' => ['label' => 'Unpaid', 'icon' => 'fa-coins',            'tone' => 'gold'],
                            ] as $key => $meta)
                                <li>
                                    <span class="rp-leave-icon rp-leave-icon--{{ $meta['tone'] }}">
                                        <i class="fa-solid {{ $meta['icon'] }}"></i>
                                    </span>
                                    <span class="rp-leave-label">{{ $meta['label'] }}</span>
                                    <strong class="rp-leave-value">
                                        {{ \App\Livewire\Manager\TeamReports::formatDays((float) ($report['leave_breakdown'][$key] ?? 0)) }} d
                                    </strong>
                                </li>
                            @endforeach
                        </ul>

                        <div class="rp-leave-total">
                            <span>Total leave days</span>
                            <strong>
                                {{ \App\Livewire\Manager\TeamReports::formatDays((float) ($summary['total_leave_days'] ?? 0)) }}
                            </strong>
                        </div>
                    </div>

                    <div class="panel rp-period-panel">
                        <h2>Report Period</h2>
                        <div class="rp-period-row">
                            <span class="rp-period-label">From</span>
                            <span class="rp-period-value">
                                {{ !empty($report['period']['from']) ? \Carbon\Carbon::parse($report['period']['from'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div class="rp-period-row">
                            <span class="rp-period-label">To</span>
                            <span class="rp-period-value">
                                {{ !empty($report['period']['to']) ? \Carbon\Carbon::parse($report['period']['to'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>