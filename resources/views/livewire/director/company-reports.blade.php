<div class="page-company-reports">
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

    <div class="panel cr-panel">
        <div class="cr-header">
            <i class="fa-solid fa-chart-pie cr-header-icon"></i>
            <div>
                <h2>Company Reports</h2>
                <p>Workforce, timesheet, leave, and financial intelligence across the whole company</p>
            </div>
            <div class="cr-header-actions">
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
            <div class="cr-export-banner">
                <div class="cr-export-banner-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="cr-export-banner-body">
                    <strong>Report exported</strong>
                    <span>
                        {{ $lastExport['record_count'] ?? 0 }} records
                        — {{ strtoupper($lastExport['format'] ?? 'FILE') }}
                    </span>
                </div>
                <a href="{{ $lastExport['download_url'] ?? '#' }}" download class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-download"></i>
                    Download
                </a>
            </div>
        @endif

        <div class="cr-filters">
            <div class="cr-presets">
                <button type="button" wire:click="presetPeriod('this_month')" class="cr-preset">This Month</button>
                <button type="button" wire:click="presetPeriod('last_month')" class="cr-preset">Last Month</button>
                <button type="button" wire:click="presetPeriod('this_quarter')" class="cr-preset">This Quarter</button>
                <button type="button" wire:click="presetPeriod('this_year')" class="cr-preset">This Year</button>
            </div>

            <div class="cr-dates">
                <input type="date" wire:model="from" class="cr-date-input">
                <span class="cr-date-sep">to</span>
                <input type="date" wire:model="to" class="cr-date-input">
                <button type="button" wire:click="applyPeriod" class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-filter"></i>
                    Apply
                </button>
            </div>
        </div>

        <nav class="nks-tabs cr-tabs">
            <button type="button"
                    wire:click="selectTab('kpi')"
                    class="nks-tab {{ $tab === 'kpi' ? 'is-active' : '' }}">
                Company KPI
            </button>
            <button type="button"
                    wire:click="selectTab('financial')"
                    class="nks-tab {{ $tab === 'financial' ? 'is-active' : '' }}">
                Financial
            </button>
        </nav>

        @if ($loading)
            <x-nks.spinner block label="Loading report…" />
        @elseif ($tab === 'kpi')
            @php
                $w = $kpi['workforce'] ?? [];
                $t = $kpi['timesheets'] ?? [];
                $l = $kpi['leave'] ?? [];
                $c = $kpi['contracts'] ?? [];
                $a = $kpi['assets'] ?? [];
                $r = $kpi['recruitment'] ?? [];
                $trend = $kpi['monthly_trend'] ?? [];
            @endphp

            <div class="cr-stats">
                <div class="cr-stat">
                    <div class="cr-stat-icon"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="cr-stat-label">Employees</div>
                        <div class="cr-stat-value">{{ $w['total_employees'] ?? 0 }}</div>
                        <div class="cr-stat-note">
                            PS {{ $w['ps_employees'] ?? 0 }} · PR {{ $w['pr_employees'] ?? 0 }}
                        </div>
                    </div>
                </div>
                <div class="cr-stat">
                    <div class="cr-stat-icon"><i class="fa-solid fa-clipboard-list"></i></div>
                    <div>
                        <div class="cr-stat-label">PR Timesheets</div>
                        <div class="cr-stat-value">{{ $t['pr_total'] ?? 0 }}</div>
                        <div class="cr-stat-note">
                            {{ $t['pr_approved'] ?? 0 }} approved · {{ $t['pr_pending'] ?? 0 }} pending
                        </div>
                    </div>
                </div>
                <div class="cr-stat cr-stat--purple">
                    <div class="cr-stat-icon"><i class="fa-solid fa-handshake"></i></div>
                    <div>
                        <div class="cr-stat-label">PS Timesheets</div>
                        <div class="cr-stat-value">{{ $t['ps_total'] ?? 0 }}</div>
                        <div class="cr-stat-note">{{ $t['ps_completed'] ?? 0 }} submitted to clients</div>
                    </div>
                </div>
                <div class="cr-stat cr-stat--gold">
                    <div class="cr-stat-icon"><i class="fa-solid fa-clock"></i></div>
                    <div>
                        <div class="cr-stat-label">Hours Logged</div>
                        <div class="cr-stat-value">
                            {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($t['total_regular_hours'] ?? 0) + (float) ($t['total_overtime_hours'] ?? 0)) }}
                        </div>
                        <div class="cr-stat-note">
                            {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($t['total_overtime_hours'] ?? 0)) }} overtime
                        </div>
                    </div>
                </div>
                <div class="cr-stat">
                    <div class="cr-stat-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
                    <div>
                        <div class="cr-stat-label">Leave Days</div>
                        <div class="cr-stat-value">{{ (int) ($l['total_days'] ?? 0) }}</div>
                        <div class="cr-stat-note">
                            {{ $l['total_requests'] ?? 0 }} requests · {{ $l['approved'] ?? 0 }} approved
                        </div>
                    </div>
                </div>
                <div class="cr-stat">
                    <div class="cr-stat-icon"><i class="fa-solid fa-file-contract"></i></div>
                    <div>
                        <div class="cr-stat-label">Contracts</div>
                        <div class="cr-stat-value">{{ $c['active'] ?? 0 }}</div>
                        <div class="cr-stat-note">
                            {{ $c['expiring_30'] ?? 0 }} expiring in 30 days
                        </div>
                    </div>
                </div>
            </div>

            <div class="cr-grid-2">
                <div class="panel">
                    <h2>Workforce Breakdown</h2>
                    <div class="cr-breakdown">
                        <div class="cr-breakdown-row">
                            <span class="cr-breakdown-label">By Department</span>
                            <div class="cr-chip-group">
                                @forelse (($w['by_department'] ?? []) as $dept => $count)
                                    <span class="cr-chip">
                                        {{ $dept ?: 'Unassigned' }} <strong>{{ $count }}</strong>
                                    </span>
                                @empty
                                    <span class="cr-muted">—</span>
                                @endforelse
                            </div>
                        </div>
                        <div class="cr-breakdown-row">
                            <span class="cr-breakdown-label">By Service Type</span>
                            <div class="cr-chip-group">
                                @forelse (($w['by_service_type'] ?? []) as $type => $count)
                                    <span class="cr-chip">
                                        {{ ucfirst($type ?: 'Unassigned') }} <strong>{{ $count }}</strong>
                                    </span>
                                @empty
                                    <span class="cr-muted">—</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2>Leave by Type</h2>
                    <ul class="cr-leave-list">
                        @foreach ([
                            'annual' => ['Annual', 'fa-umbrella-beach', 'success'],
                            'sick'   => ['Sick',   'fa-thermometer-half', 'red'],
                            'family' => ['Family', 'fa-users', 'blue'],
                            'unpaid' => ['Unpaid', 'fa-coins', 'gold'],
                        ] as $key => $meta)
                            <li>
                                <span class="cr-leave-icon cr-leave-icon--{{ $meta[2] }}">
                                    <i class="fa-solid {{ $meta[1] }}"></i>
                                </span>
                                <span class="cr-leave-label">{{ $meta[0] }}</span>
                                <strong class="cr-leave-value">
                                    {{ (int) ($l['by_type'][$key] ?? 0) }} d
                                </strong>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="panel">
                    <h2>Contracts & Assets</h2>
                    <div class="cr-stat-list">
                        <div class="cr-stat-row">
                            <span>Active contracts</span>
                            <strong>{{ $c['active'] ?? 0 }}</strong>
                        </div>
                        <div class="cr-stat-row">
                            <span>Expiring within 30 days</span>
                            <strong class="cr-warn">{{ $c['expiring_30'] ?? 0 }}</strong>
                        </div>
                        <div class="cr-stat-row">
                            <span>Expiring within 60 days</span>
                            <strong class="cr-warn">{{ $c['expiring_60'] ?? 0 }}</strong>
                        </div>
                        <div class="cr-stat-row">
                            <span>Loaned assets</span>
                            <strong>{{ $a['loaned'] ?? 0 }}</strong>
                        </div>
                        <div class="cr-stat-row">
                            <span>Overdue assets</span>
                            <strong class="cr-danger">{{ $a['overdue'] ?? 0 }}</strong>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2>Recruitment Snapshot</h2>
                    <div class="cr-stat-list">
                        <div class="cr-stat-row">
                            <span>Open requisitions</span>
                            <strong>{{ $r['open_requisitions'] ?? 0 }}</strong>
                        </div>
                        <div class="cr-stat-row">
                            <span>Active candidates</span>
                            <strong>{{ $r['active_candidates'] ?? 0 }}</strong>
                        </div>
                        <div class="cr-stat-row">
                            <span>Hired in period</span>
                            <strong>{{ $r['hired_in_period'] ?? 0 }}</strong>
                        </div>
                        <div class="cr-stat-row">
                            <span>Active offers</span>
                            <strong>{{ $r['active_offers'] ?? 0 }}</strong>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel cr-trend-panel">
                <h2>Monthly Trend</h2>
                @if (empty($trend))
                    <div class="cr-empty-inline">
                        <i class="fa-solid fa-circle-info"></i>
                        No trend data available for this period.
                    </div>
                @else
                    <div class="cr-trend-table-wrapper">
                        <table class="cr-trend-table">
                            <thead>
                                <tr>
                                    <th>Month</th>
                                    <th style="text-align: right;">PR Timesheets</th>
                                    <th style="text-align: right;">PS Timesheets</th>
                                    <th style="text-align: right;">Leave Requests</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($trend as $row)
                                    <tr>
                                        <td>{{ $row['month'] ?? '—' }}</td>
                                        <td style="text-align: right;">{{ $row['pr_timesheets'] ?? 0 }}</td>
                                        <td style="text-align: right;">{{ $row['ps_timesheets'] ?? 0 }}</td>
                                        <td style="text-align: right;">{{ $row['leave_requests'] ?? 0 }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @else
            @php
                $s = $financial['summary'] ?? [];
                $byEmployee = $financial['by_employee'] ?? [];
                $byClient = $financial['ps_by_client'] ?? [];
            @endphp

            <div class="cr-stats">
                <div class="cr-stat">
                    <div class="cr-stat-icon"><i class="fa-solid fa-clipboard-check"></i></div>
                    <div>
                        <div class="cr-stat-label">PR Approved</div>
                        <div class="cr-stat-value">{{ $s['pr_timesheets_approved'] ?? 0 }}</div>
                        <div class="cr-stat-note">Timesheets paid</div>
                    </div>
                </div>
                <div class="cr-stat cr-stat--danger">
                    <div class="cr-stat-icon"><i class="fa-solid fa-arrow-down"></i></div>
                    <div>
                        <div class="cr-stat-label">PR Cost</div>
                        <div class="cr-stat-value">
                            {{ \App\Livewire\Director\CompanyReports::money((float) ($s['pr_cost_estimate'] ?? 0)) }}
                        </div>
                        <div class="cr-stat-note">Internal labor cost</div>
                    </div>
                </div>
                <div class="cr-stat">
                    <div class="cr-stat-icon"><i class="fa-solid fa-arrow-up"></i></div>
                    <div>
                        <div class="cr-stat-label">PS Billing</div>
                        <div class="cr-stat-value">
                            {{ \App\Livewire\Director\CompanyReports::money((float) ($s['ps_billing_estimate'] ?? 0)) }}
                        </div>
                        <div class="cr-stat-note">{{ $s['ps_timesheets_billed'] ?? 0 }} timesheets billed</div>
                    </div>
                </div>
                <div class="cr-stat cr-stat--gold">
                    <div class="cr-stat-icon"><i class="fa-solid fa-chart-line"></i></div>
                    <div>
                        <div class="cr-stat-label">Gross Margin</div>
                        <div class="cr-stat-value">
                            {{ \App\Livewire\Director\CompanyReports::money((float) ($s['gross_margin'] ?? 0)) }}
                        </div>
                        <div class="cr-stat-note">
                            @php
                                $billing = (float) ($s['ps_billing_estimate'] ?? 0);
                                $margin = (float) ($s['gross_margin'] ?? 0);
                                $pct = $billing > 0 ? round(($margin / $billing) * 100, 1) : 0;
                            @endphp
                            {{ $pct }}% of billing
                        </div>
                    </div>
                </div>
            </div>

            <div class="cr-grid-2">
                <div class="panel">
                    <h2>PR Cost by Employee</h2>
                    @if (empty($byEmployee))
                        <div class="cr-empty-inline">
                            <i class="fa-solid fa-circle-info"></i>
                            No approved PR timesheets in this period.
                        </div>
                    @else
                        <div class="cr-table-wrapper">
                            <table class="cr-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th style="text-align: right;">Regular</th>
                                        <th style="text-align: right;">Overtime</th>
                                        <th style="text-align: right;">Cost</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($byEmployee as $row)
                                        <tr>
                                            <td>
                                                <div class="cr-emp-name">
                                                    {{ $row['employee']['first_name'] ?? '' }}
                                                    {{ $row['employee']['last_name'] ?? '' }}
                                                </div>
                                                <div class="cr-emp-meta">
                                                    {{ $row['employee']['employee_number'] ?? '' }}
                                                    @if (!empty($row['employee']['position']))
                                                        · {{ $row['employee']['position'] }}
                                                    @endif
                                                </div>
                                            </td>
                                            <td style="text-align: right;">
                                                {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($row['regular_hours'] ?? 0)) }}
                                            </td>
                                            <td style="text-align: right;">
                                                {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($row['overtime_hours'] ?? 0)) }}
                                            </td>
                                            <td style="text-align: right;" class="cr-total">
                                                {{ \App\Livewire\Director\CompanyReports::money((float) ($row['estimated_cost'] ?? 0)) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="panel">
                    <h2>PS Billing by Client</h2>
                    @if (empty($byClient))
                        <div class="cr-empty-inline">
                            <i class="fa-solid fa-circle-info"></i>
                            No submitted PS timesheets in this period.
                        </div>
                    @else
                        <div class="cr-table-wrapper">
                            <table class="cr-table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th style="text-align: right;">Hours</th>
                                        <th style="text-align: right;">Billing</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($byClient as $row)
                                        <tr>
                                            <td>
                                                <div class="cr-client-name">{{ $row['client'] ?? '—' }}</div>
                                            </td>
                                            <td style="text-align: right;">
                                                {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($row['total_hours'] ?? 0)) }}
                                            </td>
                                            <td style="text-align: right;" class="cr-total">
                                                {{ \App\Livewire\Director\CompanyReports::money((float) ($row['estimated_billing'] ?? 0)) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="cr-note">
                <i class="fa-solid fa-circle-info"></i>
                <span>
                    Rates are estimates — PR cost uses a default hourly rate with 1.5× overtime; PS billing uses
                    a 1.6× multiplier on the same base rate. Actual amounts depend on per-contract rates.
                </span>
            </div>
        @endif
    </div>
</div>