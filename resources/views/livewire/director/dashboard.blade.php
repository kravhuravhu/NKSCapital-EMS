<div class="page-dashboard">

    @if ($loading)
        <x-nks.spinner block label="Loading executive dashboard…" />
    @elseif ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @else
        <div class="dash-stats">
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-users"></i><span>Total Employees</span></div>
                <div class="dash-stat-value">{{ $data['total_employees'] ?? 0 }}</div>
                <div class="dash-stat-note">
                    PS: {{ $data['ps_employees'] ?? 0 }} · PR: {{ $data['pr_employees'] ?? 0 }}
                </div>
            </div>
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-certificate"></i><span>Pending L2</span></div>
                <div class="dash-stat-value">{{ $data['pending_l2_approvals'] ?? 0 }}</div>
                <div class="dash-stat-note">Awaiting final approval</div>
            </div>
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-file-contract"></i><span>Contracts Expiring</span></div>
                <div class="dash-stat-value">{{ $data['expiring_contracts_30'] ?? 0 }}</div>
                <div class="dash-stat-note">Within 30 days</div>
            </div>
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-briefcase"></i><span>Open Requisitions</span></div>
                <div class="dash-stat-value">{{ $data['open_requisitions'] ?? 0 }}</div>
                <div class="dash-stat-note">Active offers: {{ $data['active_offers'] ?? 0 }}</div>
            </div>
        </div>

        <div class="dash-grid">
            <div class="stack">
                <div class="panel">
                    <h2>Company KPI</h2>
                    @php $wf = $kpi['workforce'] ?? []; @endphp
                    <div class="dash-progress-metrics" style="grid-template-columns: repeat(3, 1fr);">
                        <div class="dash-progress-metric">
                            <div class="metric-label">Total Employees</div>
                            <div class="metric-value">{{ $wf['total_employees'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">PS Employees</div>
                            <div class="metric-value">{{ $wf['ps_employees'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">PR Employees</div>
                            <div class="metric-value">{{ $wf['pr_employees'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2>Recent Critical Audit Events</h2>
                    @forelse (($data['recent_critical_audit'] ?? []) as $event)
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-shield-halved"></i>
                            <div class="dash-activity-text">{{ $event['action'] ?? '' }}</div>
                            <div class="dash-activity-time">
                                {{ isset($event['timestamp']) ? \Carbon\Carbon::parse($event['timestamp'])->diffForHumans() : '' }}
                            </div>
                        </div>
                    @empty
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div class="dash-activity-text">No critical events in the last period</div>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="stack">
                <div class="panel">
                    <h2>Company-wide Compliance</h2>
                    @php $comp = $data['timesheet_compliance'] ?? []; @endphp
                    <div class="dash-progress-metrics" style="grid-template-columns: repeat(3, 1fr);">
                        <div class="dash-progress-metric">
                            <div class="metric-label">PS Submitted</div>
                            <div class="metric-value">{{ $comp['ps_submitted'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">PR Submitted</div>
                            <div class="metric-value">{{ $comp['pr_submitted'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Target</div>
                            <div class="metric-value">{{ $comp['target'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2>Company Assets</h2>
                    <div class="dash-progress-metrics" style="grid-template-columns: 1fr;">
                        <div class="dash-progress-metric">
                            <div class="metric-label">Overdue Assets</div>
                            <div class="metric-value">{{ $data['overdue_assets_company'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Active Delegations</div>
                            <div class="metric-value">{{ $data['active_delegations'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>