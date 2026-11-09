<div class="nks-dashboard">
    @if ($loading)
        <div class="nks-kpi-grid">
            @for ($i = 0; $i < 4; $i++)
                <div class="nks-kpi">
                    <div class="nks-skeleton" style="width: 40%; height: 12px; margin-bottom: 12px;"></div>
                    <div class="nks-skeleton" style="width: 60%; height: 28px; margin-bottom: 8px;"></div>
                    <div class="nks-skeleton" style="width: 50%; height: 10px;"></div>
                </div>
            @endfor
        </div>
    @elseif ($error)
        <div class="nks-notice" style="background: rgba(194, 91, 91, 0.1); color: var(--nks-danger);">
            <i class="fa-solid fa-circle-exclamation" style="color: var(--nks-danger);"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @else
        <div class="nks-dashboard-greeting">
            <div>
                <h2>Team overview for {{ auth()->user()->first_name }}</h2>
                <p>{{ now()->format('l, j F Y') }}</p>
            </div>
        </div>

        <div class="nks-kpi-grid">
            <div class="nks-kpi">
                <div class="nks-kpi-head">
                    <span class="nks-kpi-icon"><i class="fa-solid fa-users" aria-hidden="true"></i></span>
                    <span class="nks-kpi-label">Team Size</span>
                </div>
                <div class="nks-kpi-value">{{ $teamSize }}</div>
                <div class="nks-kpi-foot"><span>active team members</span></div>
            </div>

            <div class="nks-kpi nks-kpi--{{ $pendingL1 > 0 ? 'warning' : 'success' }}">
                <div class="nks-kpi-head">
                    <span class="nks-kpi-icon"><i class="fa-solid fa-check-double" aria-hidden="true"></i></span>
                    <span class="nks-kpi-label">Pending L1</span>
                </div>
                <div class="nks-kpi-value">{{ $pendingL1 }}</div>
                <div class="nks-kpi-foot">
                    <a href="{{ route('portal.manager.pending-l1') }}" wire:navigate>
                        {{ $pendingL1 > 0 ? 'Open approvals' : 'Nothing pending' }}
                    </a>
                </div>
            </div>

            <div class="nks-kpi nks-kpi--{{ $pendingLeave > 0 ? 'warning' : 'success' }}">
                <div class="nks-kpi-head">
                    <span class="nks-kpi-icon"><i class="fa-solid fa-calendar-check" aria-hidden="true"></i></span>
                    <span class="nks-kpi-label">Pending Leave</span>
                </div>
                <div class="nks-kpi-value">{{ $pendingLeave }}</div>
                <div class="nks-kpi-foot">
                    <a href="{{ route('portal.manager.pending-leave') }}" wire:navigate>
                        {{ $pendingLeave > 0 ? 'Review requests' : 'Nothing pending' }}
                    </a>
                </div>
            </div>

            <div class="nks-kpi nks-kpi--{{ $overdueAssets > 0 ? 'danger' : 'success' }}">
                <div class="nks-kpi-head">
                    <span class="nks-kpi-icon"><i class="fa-solid fa-laptop" aria-hidden="true"></i></span>
                    <span class="nks-kpi-label">Overdue Assets</span>
                </div>
                <div class="nks-kpi-value">{{ $overdueAssets }}</div>
                <div class="nks-kpi-foot">
                    <a href="{{ route('portal.manager.team-assets') }}" wire:navigate>
                        {{ $overdueAssets > 0 ? 'Follow up' : 'All clear' }}
                    </a>
                </div>
            </div>
        </div>

        <div class="nks-dashboard-grid">
            <div class="nks-dashboard-col">
                <div class="nks-card">
                    <div class="nks-card-head">
                        <h3>Team hours submitted</h3>
                        <a href="{{ route('portal.manager.team-timesheets') }}" wire:navigate class="nks-card-link">
                            All timesheets
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>

                    @if (count($teamHistory) === 0)
                        <div class="nks-card-empty">
                            <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                            <p>No team data yet.</p>
                        </div>
                    @else
                        <div class="nks-bar-chart" style="height: 160px;">
                            @foreach ($teamHistory as $row)
                                <div class="nks-bar"
                                     style="height: {{ $this->barHeight((float) ($row['total_hours'] ?? 0)) }}%"
                                     title="{{ $row['month'] }} &middot; {{ $row['total_hours'] }}h &middot; {{ $row['submitted'] }} submitted">
                                    <span class="nks-bar-value">{{ $row['total_hours'] }}h</span>
                                    <span class="nks-bar-label">{{ $row['month'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="nks-card">
                    <div class="nks-card-head">
                        <h3>Pending approvals</h3>
                        <a href="{{ route('portal.manager.pending-l1') }}" wire:navigate class="nks-card-link">
                            Open queue
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>

                    @if (count($recentApprovals) === 0)
                        <div class="nks-card-empty">
                            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                            <p>No timesheets awaiting your approval.</p>
                        </div>
                    @else
                        <div class="nks-list">
                            @foreach ($recentApprovals as $row)
                                <div class="nks-list-item">
                                    <span class="nks-list-avatar">
                                        {{ strtoupper(substr($row['user']['first_name'] ?? 'T', 0, 1) . substr($row['user']['last_name'] ?? 'M', 0, 1)) }}
                                    </span>
                                    <div class="nks-list-body">
                                        <strong>{{ $row['user']['first_name'] ?? '' }} {{ $row['user']['last_name'] ?? '' }}</strong>
                                        <span>
                                            {{ \Carbon\Carbon::parse($row['month_year'])->format('F Y') }}
                                            &middot; {{ \App\Livewire\Employee\Timesheet::formatHours((float) ($row['total_regular_hours'] ?? 0)) }}
                                        </span>
                                    </div>
                                    <x-nks.badge tone="warning">L1</x-nks.badge>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="nks-card">
                    <div class="nks-card-head">
                        <h3>Recent team activity</h3>
                    </div>

                    @if (count($recentActivity) === 0)
                        <div class="nks-card-empty">
                            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                            <p>No recent activity.</p>
                        </div>
                    @else
                        <div class="nks-activity-table-wrap">
                            <table class="nks-activity-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Record</th>
                                        <th>IP</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentActivity as $entry)
                                        @php
                                            $actor = $entry['user'] ?? [];
                                            $actorName = trim(($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? ''));
                                            $initials = strtoupper(substr($actor['first_name'] ?? 'S', 0, 1) . substr($actor['last_name'] ?? 'Y', 0, 1));
                                            $tone = $this->actionTone($entry['action'] ?? '');
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="nks-activity-user">
                                                    <span class="nks-activity-avatar nks-activity-avatar--{{ $tone }}">{{ $initials }}</span>
                                                    <span>{{ $actorName ?: 'System' }}</span>
                                                </div>
                                            </td>
                                            <td>{{ $this->humanizeAction($entry['action'] ?? '') }}</td>
                                            <td>
                                                @if (!empty($entry['table_name']))
                                                    <span class="nks-activity-record">{{ $entry['table_name'] }}#{{ $entry['record_id'] ?? '0' }}</span>
                                                @else
                                                    &mdash;
                                                @endif
                                            </td>
                                            <td><span class="nks-activity-ip">{{ $entry['ip_address'] ?? '—' }}</span></td>
                                            <td><span class="nks-activity-time">
                                                {{ isset($entry['timestamp']) ? \Carbon\Carbon::parse($entry['timestamp'])->format('d M H:i') : '' }}
                                            </span></td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="nks-dashboard-col">
                <div class="nks-card">
                    <div class="nks-card-head">
                        <h3>Timesheet status</h3>
                    </div>

                    @if ($this->totalTimesheets === 0)
                        <div class="nks-card-empty">
                            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                            <p>No timesheets submitted yet.</p>
                        </div>
                    @else
                        <div class="nks-donut-wrap">
                            <svg viewBox="0 0 100 100" class="nks-donut">
                                @php
                                    $runningOffset = 0;
                                    $colors = ['approved' => 'var(--nks-success)', 'pending' => 'var(--nks-warning)', 'rejected' => 'var(--nks-danger)', 'draft' => 'var(--nks-muted)'];
                                @endphp
                                @foreach (['approved', 'pending', 'rejected', 'draft'] as $key)
                                    @php
                                        $count = $statusBuckets[$key] ?? 0;
                                        $dash = $this->donutDash($count);
                                    @endphp
                                    @if ($count > 0)
                                        <circle cx="50" cy="50" r="44"
                                                fill="none"
                                                stroke="{{ $colors[$key] }}"
                                                stroke-width="12"
                                                stroke-dasharray="{{ $dash }} 276.46"
                                                stroke-dashoffset="{{ -$runningOffset }}" />
                                        @php $runningOffset += $dash; @endphp
                                    @endif
                                @endforeach
                            </svg>
                            <div class="nks-donut-center">
                                <strong>{{ $this->totalTimesheets }}</strong>
                                <span>total</span>
                            </div>
                        </div>

                        <div class="nks-legend">
                            @foreach (['approved' => 'Approved', 'pending' => 'Pending', 'rejected' => 'Rejected', 'draft' => 'Draft'] as $key => $label)
                                <div class="nks-legend-row">
                                    <span class="nks-legend-dot" style="background: {{ $colors[$key] }}"></span>
                                    <span>{{ $label }}</span>
                                    <strong>{{ $statusBuckets[$key] }}</strong>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="nks-card">
                    <div class="nks-card-head">
                        <h3>Team on leave today</h3>
                        <a href="{{ route('portal.manager.team-calendar') }}" wire:navigate class="nks-card-link">
                            Calendar
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>

                    @if (count($teamLeaveToday) === 0)
                        <div class="nks-card-empty">
                            <i class="fa-solid fa-umbrella-beach" aria-hidden="true"></i>
                            <p>Nobody on leave today.</p>
                        </div>
                    @else
                        <div class="nks-list">
                            @foreach ($teamLeaveToday as $row)
                                <div class="nks-list-item">
                                    <span class="nks-list-avatar">
                                        {{ strtoupper(substr($row['user']['first_name'] ?? 'T', 0, 1) . substr($row['user']['last_name'] ?? 'M', 0, 1)) }}
                                    </span>
                                    <div class="nks-list-body">
                                        <strong>{{ $row['user']['first_name'] ?? '' }} {{ $row['user']['last_name'] ?? '' }}</strong>
                                        <span>{{ ucfirst($row['leave_type'] ?? '') }} leave until {{ \Carbon\Carbon::parse($row['end_date'])->format('d M') }}</span>
                                    </div>
                                    <x-nks.badge tone="success">{{ ucfirst($row['leave_type'] ?? '') }}</x-nks.badge>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="nks-card">
                    <div class="nks-card-head">
                        <h3>Upcoming meetings</h3>
                        <a href="{{ route('portal.manager.meetings') }}" wire:navigate class="nks-card-link">
                            All
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>

                    @if (count($upcomingMeetings) === 0)
                        <div class="nks-card-empty">
                            <i class="fa-solid fa-calendar-days" aria-hidden="true"></i>
                            <p>No meetings scheduled.</p>
                        </div>
                    @else
                        <div class="nks-meeting-list">
                            @foreach (array_slice($upcomingMeetings, 0, 4) as $meeting)
                                <div class="nks-meeting-item">
                                    <div class="nks-meeting-date">
                                        <strong>{{ \Carbon\Carbon::parse($meeting['start_time'])->format('d') }}</strong>
                                        <span>{{ \Carbon\Carbon::parse($meeting['start_time'])->format('M') }}</span>
                                    </div>
                                    <div class="nks-meeting-body">
                                        <strong>{{ $meeting['title'] }}</strong>
                                        <span>{{ \Carbon\Carbon::parse($meeting['start_time'])->format('H:i') }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if (count($delegations) > 0)
                    <div class="nks-card">
                        <div class="nks-card-head">
                            <h3>Active delegations</h3>
                        </div>
                        <div class="nks-list">
                            @foreach ($delegations as $row)
                                <div class="nks-list-item">
                                    <span class="nks-list-avatar nks-list-avatar--muted">
                                        <i class="fa-solid fa-user-cog" aria-hidden="true"></i>
                                    </span>
                                    <div class="nks-list-body">
                                        <strong>{{ $row['delegate']['first_name'] ?? '' }} {{ $row['delegate']['last_name'] ?? '' }}</strong>
                                        <span>until {{ \Carbon\Carbon::parse($row['end_date'])->format('d M Y') }}</span>
                                    </div>
                                    <x-nks.badge tone="gold">{{ ucfirst($row['delegation_type']) }}</x-nks.badge>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>