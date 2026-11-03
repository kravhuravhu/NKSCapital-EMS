<div class="page-recruiter-reports">
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

    <div class="panel rr-panel">
        <div class="rr-header">
            <i class="fa-solid fa-chart-line rr-header-icon"></i>
            <div>
                <h2>Recruitment Reports</h2>
                <p>Funnel, conversion, offers, requisitions, and candidate breakdown</p>
            </div>
            <div class="rr-header-actions">
                <button type="button"
                        wire:click="exportCsv"
                        wire:loading.attr="disabled"
                        wire:target="exportCsv"
                        class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-file-csv"></i>
                    Export CSV
                </button>
            </div>
        </div>

        @if ($lastExport && !empty($lastExport['download_url']))
            <div class="rr-export-banner">
                <div class="rr-export-icon">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <div class="rr-export-body">
                    <strong>Report exported</strong>
                    <span>{{ $lastExport['filename'] ?? 'recruitment-report.csv' }}</span>
                </div>
                <a href="{{ $lastExport['download_url'] }}" download class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-download"></i> Download
                </a>
            </div>
        @endif

        <div class="rr-filters">
            <div class="rr-presets">
                <button type="button" wire:click="presetPeriod('last_30')" class="rr-preset">Last 30 Days</button>
                <button type="button" wire:click="presetPeriod('last_90')" class="rr-preset">Last 90 Days</button>
                <button type="button" wire:click="presetPeriod('this_quarter')" class="rr-preset">This Quarter</button>
                <button type="button" wire:click="presetPeriod('this_year')" class="rr-preset">This Year</button>
            </div>

            <div class="rr-dates">
                <input type="date" wire:model="from" class="rr-date-input">
                <span class="rr-date-sep">to</span>
                <input type="date" wire:model="to" class="rr-date-input">
                <input type="text" wire:model="department" class="rr-date-input rr-dept-input"
                       placeholder="Department (optional)">
                <button type="button" wire:click="applyPeriod" class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-filter"></i>
                    Apply
                </button>
            </div>
        </div>

        <nav class="nks-tabs rr-tabs">
            <button type="button"
                    wire:click="selectTab('metrics')"
                    class="nks-tab {{ $tab === 'metrics' ? 'is-active' : '' }}">
                Metrics
            </button>
            <button type="button"
                    wire:click="selectTab('report')"
                    class="nks-tab {{ $tab === 'report' ? 'is-active' : '' }}">
                Candidate Report
            </button>
        </nav>

        @if ($loading)
            <x-nks.spinner block label="Loading recruitment data…" />
        @elseif ($tab === 'metrics')
            @php
                $funnel = $metrics['funnel'] ?? [];
                $conversion = $metrics['conversion'] ?? [];
                $offers = $metrics['offers'] ?? [];
                $reqs = $metrics['requisitions'] ?? [];
                $maxFunnel = max(1, (int) ($funnel['applied'] ?? 0));
            @endphp

            <div class="rr-stats">
                <div class="rr-stat">
                    <div class="rr-stat-icon"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="rr-stat-label">Candidates</div>
                        <div class="rr-stat-value">{{ $funnel['applied'] ?? 0 }}</div>
                        <div class="rr-stat-note">In period</div>
                    </div>
                </div>
                <div class="rr-stat rr-stat--success">
                    <div class="rr-stat-icon"><i class="fa-solid fa-user-check"></i></div>
                    <div>
                        <div class="rr-stat-label">Hired</div>
                        <div class="rr-stat-value">{{ $funnel['hired'] ?? 0 }}</div>
                        <div class="rr-stat-note">
                            {{ \App\Livewire\Recruiter\Reports::formatPercent((float) ($conversion['overall_hiring_rate'] ?? 0)) }} overall
                        </div>
                    </div>
                </div>
                <div class="rr-stat rr-stat--gold">
                    <div class="rr-stat-icon"><i class="fa-solid fa-file-signature"></i></div>
                    <div>
                        <div class="rr-stat-label">Offers Extended</div>
                        <div class="rr-stat-value">{{ $offers['total_extended'] ?? 0 }}</div>
                        <div class="rr-stat-note">
                            {{ \App\Livewire\Recruiter\Reports::formatPercent((float) ($offers['acceptance_rate'] ?? 0)) }} accepted
                        </div>
                    </div>
                </div>
                <div class="rr-stat rr-stat--blue">
                    <div class="rr-stat-icon"><i class="fa-solid fa-clock"></i></div>
                    <div>
                        <div class="rr-stat-label">Time to Hire</div>
                        <div class="rr-stat-value">
                            @if (!is_null($metrics['time_to_hire_days'] ?? null))
                                {{ $metrics['time_to_hire_days'] }}d
                            @else
                                —
                            @endif
                        </div>
                        <div class="rr-stat-note">Avg from application</div>
                    </div>
                </div>
            </div>

            <div class="rr-grid-2">
                <div class="panel">
                    <h2>Funnel</h2>
                    <div class="rr-funnel">
                        @foreach ([
                            'applied' => ['Applied', 'muted'],
                            'screening' => ['Screening', 'blue'],
                            'interview' => ['Interview', 'gold'],
                            'offer' => ['Offer', 'purple'],
                            'hired' => ['Hired', 'success'],
                        ] as $key => $meta)
                            @php
                                $count = (int) ($funnel[$key] ?? 0);
                                $width = \App\Livewire\Recruiter\Reports::funnelWidth($count, $maxFunnel);
                            @endphp
                            <div class="rr-funnel-row">
                                <div class="rr-funnel-label">
                                    <span class="rr-funnel-name">{{ $meta[0] }}</span>
                                    <span class="rr-funnel-count">{{ $count }}</span>
                                </div>
                                <div class="rr-funnel-bar-track">
                                    <div class="rr-funnel-bar rr-funnel-bar--{{ $meta[1] }}"
                                         style="width: {{ $width }}%"></div>
                                </div>
                                <span class="rr-funnel-pct">
                                    {{ $maxFunnel > 0 ? round(($count / $maxFunnel) * 100) : 0 }}%
                                </span>
                            </div>
                        @endforeach
                        <div class="rr-funnel-row rr-funnel-row--rejected">
                            <div class="rr-funnel-label">
                                <span class="rr-funnel-name">Rejected</span>
                                <span class="rr-funnel-count">{{ $funnel['rejected'] ?? 0 }}</span>
                            </div>
                            <div class="rr-funnel-bar-track">
                                <div class="rr-funnel-bar rr-funnel-bar--red"
                                     style="width: {{ \App\Livewire\Recruiter\Reports::funnelWidth((int) ($funnel['rejected'] ?? 0), $maxFunnel) }}%"></div>
                            </div>
                            <span class="rr-funnel-pct">
                                {{ $maxFunnel > 0 ? round((($funnel['rejected'] ?? 0) / $maxFunnel) * 100) : 0 }}%
                            </span>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2>Conversion Rates</h2>
                    <div class="rr-conv-grid">
                        <div class="rr-conv">
                            <div class="rr-conv-label">Applied → Interview</div>
                            <div class="rr-conv-value">{{ \App\Livewire\Recruiter\Reports::formatPercent((float) ($conversion['applied_to_interview'] ?? 0)) }}</div>
                        </div>
                        <div class="rr-conv">
                            <div class="rr-conv-label">Interview → Offer</div>
                            <div class="rr-conv-value">{{ \App\Livewire\Recruiter\Reports::formatPercent((float) ($conversion['interview_to_offer'] ?? 0)) }}</div>
                        </div>
                        <div class="rr-conv">
                            <div class="rr-conv-label">Offer → Hire</div>
                            <div class="rr-conv-value">{{ \App\Livewire\Recruiter\Reports::formatPercent((float) ($conversion['offer_to_hire'] ?? 0)) }}</div>
                        </div>
                        <div class="rr-conv">
                            <div class="rr-conv-label">Overall Hiring Rate</div>
                            <div class="rr-conv-value rr-conv-value--highlight">{{ \App\Livewire\Recruiter\Reports::formatPercent((float) ($conversion['overall_hiring_rate'] ?? 0)) }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="rr-grid-2">
                <div class="panel">
                    <h2>Offers</h2>
                    <div class="rr-stat-list">
                        <div class="rr-stat-row">
                            <span>Extended</span>
                            <strong>{{ $offers['total_extended'] ?? 0 }}</strong>
                        </div>
                        <div class="rr-stat-row">
                            <span>Accepted</span>
                            <strong class="rr-text-success">{{ $offers['accepted'] ?? 0 }}</strong>
                        </div>
                        <div class="rr-stat-row">
                            <span>Declined</span>
                            <strong class="rr-text-danger">{{ $offers['declined'] ?? 0 }}</strong>
                        </div>
                        <div class="rr-stat-row">
                            <span>Withdrawn</span>
                            <strong>{{ $offers['withdrawn'] ?? 0 }}</strong>
                        </div>
                        <div class="rr-stat-row">
                            <span>Expired</span>
                            <strong>{{ $offers['expired'] ?? 0 }}</strong>
                        </div>
                        <div class="rr-stat-row rr-stat-row--divider">
                            <span>Acceptance rate</span>
                            <strong>{{ \App\Livewire\Recruiter\Reports::formatPercent((float) ($offers['acceptance_rate'] ?? 0)) }}</strong>
                        </div>
                        <div class="rr-stat-row">
                            <span>Avg salary extended</span>
                            <strong>
                                @if (!empty($offers['avg_salary_extended']))
                                    R {{ number_format((float) $offers['avg_salary_extended'], 2) }}
                                @else —
                                @endif
                            </strong>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2>Requisitions</h2>
                    <div class="rr-stat-list">
                        <div class="rr-stat-row">
                            <span>Total</span>
                            <strong>{{ $reqs['total'] ?? 0 }}</strong>
                        </div>
                        <div class="rr-stat-row">
                            <span>Open</span>
                            <strong class="rr-text-success">{{ $reqs['open'] ?? 0 }}</strong>
                        </div>
                        <div class="rr-stat-row">
                            <span>Pending Approval</span>
                            <strong class="rr-text-warning">{{ $reqs['pending_approval'] ?? 0 }}</strong>
                        </div>
                        <div class="rr-stat-row">
                            <span>Closed</span>
                            <strong class="rr-text-muted">{{ $reqs['closed'] ?? 0 }}</strong>
                        </div>
                    </div>

                    @if (!empty($reqs['by_priority']))
                        <div class="rr-req-priorities">
                            @foreach (['critical', 'high', 'medium', 'low'] as $priority)
                                @php $count = (int) ($reqs['by_priority'][$priority] ?? 0); @endphp
                                @if ($count > 0)
                                    <span class="rr-priority-chip rr-priority-chip--{{ $priority }}">
                                        {{ ucfirst($priority) }}: {{ $count }}
                                    </span>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="rr-period-note">
                <i class="fa-solid fa-circle-info"></i>
                <span>
                    Period:
                    {{ !empty($metrics['period']['from']) ? \Carbon\Carbon::parse($metrics['period']['from'])->format('d M Y') : '—' }}
                    to
                    {{ !empty($metrics['period']['to']) ? \Carbon\Carbon::parse($metrics['period']['to'])->format('d M Y') : '—' }}
                </span>
            </div>
        @else
            @php
                $summary = $report['summary'] ?? [];
                $candidates = $report['candidates'] ?? [];
            @endphp

            <div class="rr-stats">
                <div class="rr-stat">
                    <div class="rr-stat-icon"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="rr-stat-label">Total Candidates</div>
                        <div class="rr-stat-value">{{ $summary['total_candidates'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="rr-stat rr-stat--success">
                    <div class="rr-stat-icon"><i class="fa-solid fa-user-check"></i></div>
                    <div>
                        <div class="rr-stat-label">Hired</div>
                        <div class="rr-stat-value">{{ $summary['by_stage']['hired'] ?? 0 }}</div>
                    </div>
                </div>
                <div class="rr-stat rr-stat--gold">
                    <div class="rr-stat-icon"><i class="fa-solid fa-hourglass-half"></i></div>
                    <div>
                        <div class="rr-stat-label">In Pipeline</div>
                        <div class="rr-stat-value">
                            @php
                                $pipeline = ($summary['by_stage']['applied'] ?? 0)
                                    + ($summary['by_stage']['screening'] ?? 0)
                                    + ($summary['by_stage']['interview'] ?? 0)
                                    + ($summary['by_stage']['offer'] ?? 0);
                            @endphp
                            {{ $pipeline }}
                        </div>
                    </div>
                </div>
                <div class="rr-stat rr-stat--danger">
                    <div class="rr-stat-icon"><i class="fa-solid fa-user-xmark"></i></div>
                    <div>
                        <div class="rr-stat-label">Rejected</div>
                        <div class="rr-stat-value">{{ $summary['by_stage']['rejected'] ?? 0 }}</div>
                    </div>
                </div>
            </div>

            <div class="rr-grid-2">
                <div class="panel">
                    <h2>By Stage</h2>
                    @if (!empty($summary['by_stage']))
                        <div class="rr-stat-list">
                            @foreach ($summary['by_stage'] as $stage => $count)
                                <div class="rr-stat-row">
                                    <span>{{ ucfirst(str_replace('_', ' ', $stage)) }}</span>
                                    <strong>{{ $count }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rr-inline-empty">
                            <i class="fa-solid fa-circle-info"></i>
                            No candidates in this period.
                        </div>
                    @endif
                </div>

                <div class="panel">
                    <h2>By Source</h2>
                    @if (!empty($summary['by_source']))
                        <div class="rr-stat-list">
                            @foreach ($summary['by_source'] as $source => $count)
                                <div class="rr-stat-row">
                                    <span>{{ ucfirst($source ?: 'Unknown') }}</span>
                                    <strong>{{ $count }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rr-inline-empty">
                            <i class="fa-solid fa-circle-info"></i>
                            No source data available.
                        </div>
                    @endif
                </div>

                <div class="panel">
                    <h2>By Department</h2>
                    @if (!empty($summary['by_department']))
                        <div class="rr-stat-list">
                            @foreach ($summary['by_department'] as $dept => $count)
                                <div class="rr-stat-row">
                                    <span>{{ $dept ?: 'Unassigned' }}</span>
                                    <strong>{{ $count }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rr-inline-empty">
                            <i class="fa-solid fa-circle-info"></i>
                            No department data.
                        </div>
                    @endif
                </div>

                <div class="panel">
                    <h2>By Requisition</h2>
                    @if (!empty($summary['by_requisition']))
                        <div class="rr-stat-list">
                            @foreach ($summary['by_requisition'] as $code => $count)
                                <div class="rr-stat-row">
                                    <span class="rr-mono">{{ $code ?: '—' }}</span>
                                    <strong>{{ $count }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rr-inline-empty">
                            <i class="fa-solid fa-circle-info"></i>
                            No requisition data.
                        </div>
                    @endif
                </div>
            </div>

            <div class="panel rr-candidates-panel">
                <div class="rr-candidates-head">
                    <h2>Candidates ({{ count($candidates) }})</h2>
                    <span class="rr-candidates-sub">
                        All candidates in the selected period
                    </span>
                </div>

                @if (count($candidates) === 0)
                    <div class="rr-inline-empty">
                        <i class="fa-solid fa-circle-info"></i>
                        No candidates in this period.
                    </div>
                @else
                    <div class="rr-table-wrapper">
                        <table class="rr-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Requisition</th>
                                    <th>Stage</th>
                                    <th>Source</th>
                                    <th>Applied</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($candidates as $c)
                                    <tr wire:key="rr-c-{{ $c['id'] }}">
                                        <td>
                                            <div class="rr-c-name">{{ $c['name'] ?? '—' }}</div>
                                        </td>
                                        <td>{{ $c['email'] ?? '—' }}</td>
                                        <td>
                                            <span class="rr-mono">{{ $c['requisition'] ?? '—' }}</span>
                                        </td>
                                        <td>
                                            <x-nks.badge :tone="match($c['stage'] ?? '') {
                                                'hired' => 'success',
                                                'rejected', 'offer_declined', 'withdrawn' => 'red',
                                                'offer' => 'purple',
                                                'interview' => 'gold',
                                                'screening' => 'blue',
                                                default => 'muted',
                                            }">
                                                {{ ucfirst(str_replace('_', ' ', $c['stage'] ?? '')) }}
                                            </x-nks.badge>
                                        </td>
                                        <td>{{ ucfirst($c['source'] ?? '—') }}</td>
                                        <td>
                                            {{ !empty($c['applied_date']) ? \Carbon\Carbon::parse($c['applied_date'])->format('d M Y') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="rr-period-note">
                <i class="fa-solid fa-circle-info"></i>
                <span>
                    Report generated
                    {{ !empty($report['generated_at']) ? \Carbon\Carbon::parse($report['generated_at'])->format('d M Y H:i') : '—' }}
                </span>
            </div>
        @endif
    </div>
</div>