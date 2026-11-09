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
                <h2>Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ auth()->user()->first_name }}</h2>
                <p>{{ now()->format('l, j F Y') }}</p>
            </div>
        </div>

        <div class="nks-kpi-grid">
            <div class="nks-kpi nks-kpi--{{ $this->statusTone }}">
                <div class="nks-kpi-head">
                    <span class="nks-kpi-icon"><i class="fa-solid fa-clipboard-list" aria-hidden="true"></i></span>
                    <span class="nks-kpi-label">Timesheet</span>
                </div>
                <div class="nks-kpi-value">{{ $this->statusLabel }}</div>
                <div class="nks-kpi-foot">
                    <div class="nks-mini-progress">
                        <div class="nks-mini-progress-bar" style="width: {{ $progressPercent }}%"></div>
                    </div>
                    <span>{{ $progressPercent }}% of {{ $targetHours }}h</span>
                </div>
            </div>

            <div class="nks-kpi">
                <div class="nks-kpi-head">
                    <span class="nks-kpi-icon"><i class="fa-solid fa-umbrella-beach" aria-hidden="true"></i></span>
                    <span class="nks-kpi-label">Annual Leave</span>
                </div>
                <div class="nks-kpi-value">{{ (float) ($leaveBalances['annual']['available'] ?? 0) }}<span class="nks-kpi-unit"> days</span></div>
                <div class="nks-kpi-foot">
                    <span>{{ (float) ($leaveBalances['annual']['used_this_year'] ?? 0) }} used this year</span>
                </div>
            </div>

            <div class="nks-kpi">
                <div class="nks-kpi-head">
                    <span class="nks-kpi-icon"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span>
                    <span class="nks-kpi-label">Pending Items</span>
                </div>
                <div class="nks-kpi-value">{{ $this->totalPending }}</div>
                <div class="nks-kpi-foot"><span>awaiting your attention</span></div>
            </div>

            <div class="nks-kpi">
                <div class="nks-kpi-head">
                    <span class="nks-kpi-icon"><i class="fa-solid fa-laptop" aria-hidden="true"></i></span>
                    <span class="nks-kpi-label">My Assets</span>
                </div>
                <div class="nks-kpi-value">{{ count($myAssets) }}</div>
                <div class="nks-kpi-foot">
                    <a href="{{ route('portal.employee.assets') }}" wire:navigate>View all</a>
                </div>
            </div>
        </div>

        <div class="nks-dashboard-grid">
            <div class="nks-dashboard-col">
                <div class="nks-card">
                    <div class="nks-card-head">
                        <h3>Timesheet Progress</h3>
                        <a href="{{ route('portal.employee.timesheet') }}" wire:navigate class="nks-card-link">
                            Open timesheet
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>

                    <div class="nks-progress-header">
                        <div class="nks-progress-ring">
                            <svg viewBox="0 0 100 100" aria-hidden="true">
                                <circle cx="50" cy="50" r="44" class="nks-ring-track"></circle>
                                <circle cx="50" cy="50" r="44" class="nks-ring-fill"
                                        style="stroke-dasharray: 276.46; stroke-dashoffset: {{ 276.46 - (276.46 * $progressPercent / 100) }};"></circle>
                            </svg>
                            <div class="nks-ring-label">
                                <strong>{{ $progressPercent }}%</strong>
                                <span>complete</span>
                            </div>
                        </div>
                        <div class="nks-progress-metrics">
                            <div class="nks-progress-metric">
                                <span>Logged</span>
                                <strong>{{ \App\Livewire\Employee\Timesheet::formatHours($totalLogged) }}</strong>
                            </div>
                            <div class="nks-progress-metric">
                                <span>Target</span>
                                <strong>{{ \App\Livewire\Employee\Timesheet::formatHours($targetHours) }}</strong>
                            </div>
                            <div class="nks-progress-metric">
                                <span>Days</span>
                                <strong>{{ $workingDaysElapsed }} / {{ $workingDaysTotal }}</strong>
                            </div>
                        </div>
                    </div>

                    @if (count($timesheetHistory) > 0)
                        <div class="nks-chart-label">Recent months</div>
                        <div class="nks-bar-chart">
                            @php
                                $maxHours = collect($timesheetHistory)
                                    ->map(fn ($t) => (float) ($t['total_regular_hours'] ?? 0) + (float) ($t['total_overtime_hours'] ?? 0))
                                    ->max() ?: 1;
                            @endphp
                            @foreach ($timesheetHistory as $row)
                                @php
                                    $hours = (float) ($row['total_regular_hours'] ?? 0) + (float) ($row['total_overtime_hours'] ?? 0);
                                    $height = $maxHours > 0 ? max(8, round(($hours / $maxHours) * 100)) : 8;
                                    $monthLabel = isset($row['month_year'])
                                        ? \Carbon\Carbon::parse($row['month_year'])->format('M')
                                        : '';
                                @endphp
                                <div class="nks-bar" style="height: {{ $height }}%" title="{{ $monthLabel }}">
                                    <span class="nks-bar-value">{{ \App\Livewire\Employee\Timesheet::formatHours($hours) }}</span>
                                    <span class="nks-bar-label">{{ $monthLabel }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="nks-card">
                    <div class="nks-card-head">
                        <h3>Recent Activity</h3>
                    </div>

                    @if (count($recentActivity) === 0)
                        <div class="nks-card-empty">
                            <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                            <p>No recent activity yet.</p>
                        </div>
                    @else
                        <div class="nks-activity-table-wrap">
                            <table class="nks-activity-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Action</th>
                                        <th>Record</th>
                                        <th>IP Address</th>
                                        <th>Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentActivity as $entry)
                                        @php
                                            $actor = $entry['user'] ?? [];
                                            $actorName = trim(($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? ''));
                                            $initials = strtoupper(
                                                substr($actor['first_name'] ?? 'S', 0, 1) .
                                                substr($actor['last_name'] ?? 'Y', 0, 1)
                                            );
                                            $tone = $this->actionTone($entry['action'] ?? '');
                                            $time = isset($entry['timestamp'])
                                                ? \Carbon\Carbon::parse($entry['timestamp'])->format('d M Y H:i')
                                                : '';
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
                                                    <span class="nks-activity-record">
                                                        {{ $entry['table_name'] }}#{{ $entry['record_id'] ?? '0' }}
                                                    </span>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td><span class="nks-activity-ip">{{ $entry['ip_address'] ?? '—' }}</span></td>
                                            <td><span class="nks-activity-time">{{ $time }}</span></td>
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
                        <h3>Leave Balance</h3>
                        <a href="{{ route('portal.employee.leave') }}" wire:navigate class="nks-card-link">
                            Apply
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>

                    <div class="nks-leave-list">
                        @foreach ([
                            'annual' => ['Annual', 'fa-umbrella-beach', 'gold'],
                            'sick' => ['Sick', 'fa-thermometer-half', 'danger'],
                            'family' => ['Family', 'fa-users', 'info'],
                            'unpaid' => ['Unpaid', 'fa-coins', 'muted'],
                        ] as $key => $meta)
                            @php
                                $available = $leaveBalances[$key]['available'] ?? 0;
                                $entitlement = $leaveBalances[$key]['entitlement'] ?? 0;
                                $isInfinite = $available === 'infinite' || $entitlement === 'infinite';
                                $pct = (! $isInfinite && (float) $entitlement > 0)
                                    ? min(100, ((float) $available / (float) $entitlement) * 100)
                                    : 0;
                            @endphp
                            <div class="nks-leave-row">
                                <div class="nks-leave-row-head">
                                    <div class="nks-leave-row-label">
                                        <i class="fa-solid {{ $meta[1] }} nks-leave-row-icon nks-leave-row-icon--{{ $meta[2] }}" aria-hidden="true"></i>
                                        <span>{{ $meta[0] }}</span>
                                    </div>
                                    <strong>
                                        @if ($isInfinite) Unlimited
                                        @else {{ (float) $available }} / {{ (float) $entitlement }} d
                                        @endif
                                    </strong>
                                </div>
                                @if (! $isInfinite)
                                    <div class="nks-leave-track">
                                        <div class="nks-leave-fill nks-leave-fill--{{ $meta[2] }}" style="width: {{ $pct }}%"></div>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="nks-card">
                    <div class="nks-card-head">
                        <h3>My Assets</h3>
                        <a href="{{ route('portal.employee.assets') }}" wire:navigate class="nks-card-link">
                            View all
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                    @if (count($myAssets) === 0)
                        <div class="nks-card-empty">
                            <i class="fa-solid fa-laptop" aria-hidden="true"></i>
                            <p>No assets assigned.</p>
                        </div>
                    @else
                        <div class="nks-asset-list">
                            @foreach (array_slice($myAssets, 0, 4) as $asset)
                                <div class="nks-asset-item">
                                    <div class="nks-asset-icon"><i class="fa-solid fa-laptop" aria-hidden="true"></i></div>
                                    <div class="nks-asset-body">
                                        <strong>{{ $asset['model'] ?? 'Asset' }}</strong>
                                        <span>{{ $asset['asset_tag'] ?? '' }}</span>
                                    </div>
                                    <span class="nks-asset-tag">{{ ucfirst($asset['condition'] ?? '') }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <div class="nks-card">
                    <div class="nks-card-head"><h3>Upcoming Meetings</h3></div>
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
                                        <strong>{{ isset($meeting['start_time']) ? \Carbon\Carbon::parse($meeting['start_time'])->format('d') : '—' }}</strong>
                                        <span>{{ isset($meeting['start_time']) ? \Carbon\Carbon::parse($meeting['start_time'])->format('M') : '' }}</span>
                                    </div>
                                    <div class="nks-meeting-body">
                                        <strong>{{ $meeting['title'] ?? 'Meeting' }}</strong>
                                        <span>
                                            {{ isset($meeting['start_time']) ? \Carbon\Carbon::parse($meeting['start_time'])->format('H:i') : '' }}
                                            @if (!empty($meeting['location']))
                                                · {{ $meeting['location'] }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>