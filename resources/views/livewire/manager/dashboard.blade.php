<div class="page-dashboard">

    @if ($loading)
        <x-nks.spinner block label="Loading dashboard…" />
    @elseif ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @else
        <div class="dash-stats">
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-users"></i><span>Team Size</span></div>
                <div class="dash-stat-value">{{ $data['team_size'] ?? 0 }}</div>
                <div class="dash-stat-note">Active team members</div>
            </div>
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-check-double"></i><span>Pending L1</span></div>
                <div class="dash-stat-value">{{ $data['pending_l1_approvals'] ?? 0 }}</div>
                <div class="dash-stat-note">Awaiting your approval</div>
            </div>
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-umbrella-beach"></i><span>Leave Requests</span></div>
                <div class="dash-stat-value">{{ $data['pending_leave_requests'] ?? 0 }}</div>
                <div class="dash-stat-note">Pending your action</div>
            </div>
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-laptop"></i><span>Overdue Assets</span></div>
                <div class="dash-stat-value">{{ $data['overdue_assets'] ?? 0 }}</div>
                <div class="dash-stat-note">Team members</div>
            </div>
        </div>

        <div class="dash-grid">
            <div class="stack">
                <div class="panel">
                    <h2>Team Timesheet Summary</h2>
                    @php $ts = $data['team_timesheet_summary'] ?? []; @endphp
                    <div class="dash-progress-metrics" style="grid-template-columns: repeat(5, 1fr);">
                        <div class="dash-progress-metric">
                            <div class="metric-label">Draft</div>
                            <div class="metric-value">{{ $ts['draft'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Pending L1</div>
                            <div class="metric-value">{{ $ts['pending_l1'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Pending L2</div>
                            <div class="metric-value">{{ $ts['pending_l2'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Approved</div>
                            <div class="metric-value">{{ $ts['approved'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Rejected</div>
                            <div class="metric-value">{{ $ts['rejected'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2>Recent Pending Approvals</h2>
                    @forelse (($data['recent_pending_approvals'] ?? []) as $row)
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-clipboard-list"></i>
                            <div class="dash-activity-text">
                                {{ $row['user']['first_name'] ?? '' }} {{ $row['user']['last_name'] ?? '' }}
                                — {{ $row['total_regular_hours'] ?? 0 }}h
                            </div>
                            <div class="dash-activity-time">
                                {{ $row['month_year'] ?? '' }}
                            </div>
                        </div>
                    @empty
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-circle-check"></i>
                            <div class="dash-activity-text">No timesheets pending approval</div>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="stack">
                <div class="panel">
                    <h2>Team on Leave Today</h2>
                    @forelse (($data['team_leave_today'] ?? []) as $leave)
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-umbrella-beach"></i>
                            <div class="dash-activity-text">
                                {{ $leave['user']['first_name'] ?? '' }} {{ $leave['user']['last_name'] ?? '' }}
                                — {{ ucfirst($leave['leave_type'] ?? '') }}
                            </div>
                        </div>
                    @empty
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-circle-info"></i>
                            <div class="dash-activity-text">Nobody on leave today</div>
                        </div>
                    @endforelse
                </div>

                <div class="panel">
                    <h2>Upcoming Meetings</h2>
                    @forelse (($data['upcoming_meetings'] ?? []) as $meeting)
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-calendar-days"></i>
                            <div class="dash-activity-text">{{ $meeting['title'] ?? '' }}</div>
                            <div class="dash-activity-time">
                                {{ isset($meeting['start_time']) ? \Carbon\Carbon::parse($meeting['start_time'])->format('d M H:i') : '' }}
                            </div>
                        </div>
                    @empty
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-circle-info"></i>
                            <div class="dash-activity-text">No upcoming meetings</div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>