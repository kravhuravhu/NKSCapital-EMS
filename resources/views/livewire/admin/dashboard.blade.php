<div class="page-dashboard" wire:init="load">
    <x-nks.loading-overlay targets="load" />
    <x-nks.notice-stack :error="$error" />

    @if ($loading)
        <div class="panel" style="min-height: 400px;"></div>
    @else
        <div class="dash-stats">
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-users-cog"></i><span>Total Users</span></div>
                <div class="dash-stat-value">{{ $data['users']['total'] ?? 0 }}</div>
                <div class="dash-stat-note">
                    Active: {{ $data['users']['active'] ?? 0 }} &middot; Inactive: {{ $data['users']['inactive'] ?? 0 }}
                </div>
            </div>
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-shield-halved"></i><span>With 2FA</span></div>
                <div class="dash-stat-value">{{ $data['users']['with_2fa'] ?? 0 }}</div>
                <div class="dash-stat-note">Users with 2FA enabled</div>
            </div>
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-triangle-exclamation"></i><span>Errors Today</span></div>
                <div class="dash-stat-value">{{ $data['audit_stats']['errors_today'] ?? 0 }}</div>
                <div class="dash-stat-note">
                    Warnings: {{ $data['audit_stats']['warnings_today'] ?? 0 }}
                </div>
            </div>
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-gauge-high"></i><span>Slow Requests</span></div>
                <div class="dash-stat-value">{{ $data['audit_stats']['slow_requests_today'] ?? 0 }}</div>
                <div class="dash-stat-note">Over 2 seconds</div>
            </div>
        </div>

        <div class="dash-grid">
            <div class="stack">
                <div class="panel">
                    <h2>Recent Users</h2>
                    @forelse (($data['recent_users'] ?? []) as $user)
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-user"></i>
                            <div class="dash-activity-text">
                                {{ $user['first_name'] ?? '' }} {{ $user['last_name'] ?? '' }}
                                &middot; {{ ucfirst($user['role'] ?? '') }}
                            </div>
                            <div class="dash-activity-time">
                                {{ isset($user['created_at']) ? \Carbon\Carbon::parse($user['created_at'])->diffForHumans() : '' }}
                            </div>
                        </div>
                    @empty
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-circle-info"></i>
                            <div class="dash-activity-text">No recent users</div>
                        </div>
                    @endforelse
                </div>

                <div class="panel">
                    <h2>Recent Activity</h2>
                    @forelse ($recentActivity as $entry)
                        @php
                            $actor = $entry['user'] ?? [];
                            $actorName = trim(($actor['first_name'] ?? '') . ' ' . ($actor['last_name'] ?? ''));
                        @endphp
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-shield-halved"></i>
                            <div class="dash-activity-text">
                                <strong>{{ $actorName ?: 'System' }}</strong>
                                &middot; {{ $entry['action'] ?? '' }}
                            </div>
                            <div class="dash-activity-time">
                                {{ isset($entry['timestamp']) ? \Carbon\Carbon::parse($entry['timestamp'])->diffForHumans() : '' }}
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

            <div class="stack">
                <div class="panel">
                    <h2>System Health</h2>
                    @php $sys = $data['system_health'] ?? []; @endphp
                    <div class="dash-progress-metrics" style="grid-template-columns: 1fr;">
                        <div class="dash-progress-metric">
                            <div class="metric-label">Queue Pending Jobs</div>
                            <div class="metric-value">{{ $sys['queue_pending_jobs'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Failed Jobs</div>
                            <div class="metric-value">{{ $sys['failed_jobs'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Last Audit</div>
                            <div class="metric-value" style="font-size: 11px;">
                                {{ $sys['last_audit'] ?? '-' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>