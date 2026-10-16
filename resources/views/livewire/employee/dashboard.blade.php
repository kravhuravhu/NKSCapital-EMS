<div class="page-dashboard">

    @if ($loading)
        <x-nks.spinner block label="Loading dashboard…" />
    @elseif ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @else

        {{-- top stats --}}
        <div class="dash-stats">
            <div class="dash-stat">
                <div class="dash-stat-head">
                    <i class="fa-solid fa-clock"></i>
                    <span>Timesheet Status</span>
                </div>
                <div class="dash-stat-value">
                    @if (($summary['current_ps_timesheet']['status'] ?? null) === 'submitted_to_client'
                      || ($summary['current_pr_timesheet']['status'] ?? null) === 'approved')
                        Completed
                    @elseif (($summary['current_ps_timesheet']['status'] ?? null) === 'template_generated'
                      || ($summary['current_pr_timesheet']['status'] ?? null) === 'pending_l1')
                        Pending
                    @else
                        Draft
                    @endif
                </div>
                <div class="dash-stat-note">
                    Total hours: {{ $summary['current_ps_timesheet']['total_hours']
                        ?? $summary['current_pr_timesheet']['regular_hours'] ?? 0 }}
                </div>
            </div>

            <div class="dash-stat">
                <div class="dash-stat-head">
                    <i class="fa-solid fa-hourglass-half"></i>
                    <span>Leave Balance</span>
                </div>
                <div class="dash-stat-value">
                    {{ $leaveBalances['annual']['available'] ?? 0 }}<span> Days</span>
                </div>
                <div class="dash-stat-note">
                    Used: {{ $leaveBalances['annual']['used_this_year'] ?? 0 }} days
                </div>
            </div>

            <div class="dash-stat">
                <div class="dash-stat-head">
                    <i class="fa-solid fa-diagram-project"></i>
                    <span>My Projects</span>
                </div>
                <div class="dash-stat-value">{{ count($summary['recent_pr_timesheets'] ?? []) }}</div>
                <div class="dash-stat-note">Timesheets recorded</div>
            </div>

            <div class="dash-stat">
                <div class="dash-stat-head">
                    <i class="fa-solid fa-laptop"></i>
                    <span>My Assets</span>
                </div>
                <div class="dash-stat-value">{{ count($summary['my_assets'] ?? []) }}</div>
                <div class="dash-stat-note">Currently assigned</div>
            </div>
        </div>

        {{-- main grid --}}
        <div class="dash-grid">

            {{-- left: progress + notifications --}}
            <div class="stack">

                <div class="panel dash-progress">
                    <h2>Timesheet Progress</h2>

                    @php
                        $regular = $summary['current_pr_timesheet']['regular_hours']
                            ?? $summary['current_ps_timesheet']['total_hours'] ?? 0;
                        $overtime = $summary['current_pr_timesheet']['overtime_hours'] ?? 0;
                        $target = 180;
                        $pct = $target > 0 ? min(100, round((($regular + $overtime) / $target) * 100)) : 0;
                    @endphp

                    <div class="dash-progress-head">
                        <div>
                            <div class="dash-progress-label">Hours logged this month</div>
                            <div class="dash-progress-label">
                                {{ $regular }}h regular · {{ $overtime }}h overtime
                            </div>
                        </div>
                        <div class="dash-progress-value">{{ $pct }}%</div>
                    </div>

                    <div class="dash-progress-bar">
                        <div class="dash-progress-fill" style="width: {{ $pct }}%"></div>
                    </div>

                    <div class="dash-progress-metrics">
                        <div class="dash-progress-metric">
                            <div class="metric-label">Regular hours</div>
                            <div class="metric-value">{{ $regular }}h</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Overtime</div>
                            <div class="metric-value">{{ $overtime }}h</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Leave taken</div>
                            <div class="metric-value">{{ $leaveBalances['annual']['used_this_year'] ?? 0 }} days</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Status</div>
                            <div class="metric-value">
                                {{ ucfirst($summary['current_pr_timesheet']['status']
                                    ?? $summary['current_ps_timesheet']['status'] ?? 'draft') }}
                            </div>
                        </div>
                    </div>

                    <div class="progress-actions">
                        <a href="{{ route('portal.' . auth()->user()->role . '.timesheet') }}"
                           class="nks-btn nks-btn--primary">
                            <i class="fa-solid fa-clock"></i>
                            Continue Timesheet
                        </a>
                        <a href="{{ route('portal.' . auth()->user()->role . '.timesheet') }}"
                           class="nks-btn nks-btn--outline">
                            <i class="fa-solid fa-history"></i>
                            View History
                        </a>
                    </div>
                </div>

                <div class="panel dash-activity">
                    <h2>Recent Activity</h2>

                    @forelse ($recentActivity as $activity)
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-circle-notch"></i>
                            <div class="dash-activity-text">
                                {{ $activity['action'] ?? 'Activity' }}
                            </div>
                            <div class="dash-activity-time">
                                {{ isset($activity['timestamp']) ? \Carbon\Carbon::parse($activity['timestamp'])->diffForHumans() : '' }}
                            </div>
                        </div>
                    @empty
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-circle-info"></i>
                            <div class="dash-activity-text">No recent activity</div>
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- right: leave + assets --}}
            <div class="stack">

                <div class="panel">
                    <h2>Leave Balance Summary</h2>

                    <div class="dash-leave-grid">
                        @foreach (['annual' => 'Annual Leave', 'sick' => 'Sick Leave', 'family' => 'Family Leave', 'unpaid' => 'Unpaid Leave'] as $key => $label)
                            <div class="dash-leave-tile">
                                <div class="dash-leave-name">{{ $label }}</div>
                                <div class="dash-leave-days">
                                    {{ $leaveBalances[$key]['available'] ?? 0 }} Days
                                </div>
                                <div class="dash-leave-sub">
                                    Entitlement: {{ $leaveBalances[$key]['entitlement'] ?? 0 }}
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <a href="{{ route('portal.' . auth()->user()->role . '.leave') }}"
                       class="nks-btn nks-btn--primary nks-btn--block">
                        <i class="fa-solid fa-umbrella-beach"></i>
                        Apply For Leave
                    </a>
                </div>

                <div class="panel">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="m-0">My Assets</h2>
                        <a href="{{ route('portal.' . auth()->user()->role . '.assets') }}"
                           class="text-gold" style="font-size: 12px;">
                            View All
                        </a>
                    </div>

                    @forelse (($summary['my_assets'] ?? []) as $asset)
                        <div class="dash-asset-row">
                            <div class="dash-asset-icon">
                                <i class="fa-solid fa-laptop"></i>
                            </div>
                            <div class="dash-asset-info">
                                <div class="dash-asset-name">{{ $asset['model'] ?? $asset['asset_tag'] }}</div>
                                <div class="dash-asset-serial">
                                    {{ $asset['asset_tag'] ?? '' }} · {{ $asset['condition'] ?? '' }}
                                </div>
                            </div>
                            <x-nks.badge tone="success">Active</x-nks.badge>
                        </div>
                    @empty
                        <div class="dash-asset-row">
                            <div class="dash-asset-icon">
                                <i class="fa-solid fa-laptop"></i>
                            </div>
                            <div class="dash-asset-info">
                                <div class="dash-asset-name">No assets assigned</div>
                                <div class="dash-asset-serial">Contact IT if this is unexpected</div>
                            </div>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</div>