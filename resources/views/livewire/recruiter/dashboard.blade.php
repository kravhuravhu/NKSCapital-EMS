<div class="page-dashboard">

    @if ($loading)
        <x-nks.spinner block label="Loading recruiter dashboard…" />
    @elseif ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @else
        <div class="dash-stats">
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-briefcase"></i><span>Open Requisitions</span></div>
                <div class="dash-stat-value">{{ $data['open_requisitions'] ?? 0 }}</div>
                <div class="dash-stat-note">Active job openings</div>
            </div>
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-user-plus"></i><span>Active Candidates</span></div>
                <div class="dash-stat-value">{{ $data['active_candidates'] ?? 0 }}</div>
                <div class="dash-stat-note">
                    Total: {{ $data['total_candidates'] ?? 0 }}
                </div>
            </div>
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-comments"></i><span>Interviews This Week</span></div>
                <div class="dash-stat-value">{{ $data['interviews_this_week'] ?? 0 }}</div>
                <div class="dash-stat-note">
                    Today: {{ $data['interviews_today'] ?? 0 }}
                </div>
            </div>
            <div class="dash-stat">
                <div class="dash-stat-head"><i class="fa-solid fa-file-signature"></i><span>Pending Offers</span></div>
                <div class="dash-stat-value">{{ $data['pending_offers'] ?? 0 }}</div>
                <div class="dash-stat-note">Awaiting candidate</div>
            </div>
        </div>

        <div class="dash-grid">
            <div class="stack">
                <div class="panel">
                    <h2>Recruitment Pipeline</h2>
                    @php $pipe = $data['pipeline'] ?? []; @endphp
                    <div class="dash-progress-metrics" style="grid-template-columns: repeat(5, 1fr);">
                        <div class="dash-progress-metric">
                            <div class="metric-label">Applied</div>
                            <div class="metric-value">{{ $pipe['applied'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Screening</div>
                            <div class="metric-value">{{ $pipe['screening'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Interview</div>
                            <div class="metric-value">{{ $pipe['interview'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Offer</div>
                            <div class="metric-value">{{ $pipe['offer'] ?? 0 }}</div>
                        </div>
                        <div class="dash-progress-metric">
                            <div class="metric-label">Hired</div>
                            <div class="metric-value">{{ $pipe['hired'] ?? 0 }}</div>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2>Recent Candidates</h2>
                    @forelse (($data['recent_candidates'] ?? []) as $candidate)
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-user"></i>
                            <div class="dash-activity-text">
                                {{ $candidate['first_name'] ?? '' }} {{ $candidate['last_name'] ?? '' }}
                                — {{ ucfirst($candidate['current_stage'] ?? '') }}
                            </div>
                            <div class="dash-activity-time">
                                {{ isset($candidate['applied_date']) ? \Carbon\Carbon::parse($candidate['applied_date'])->diffForHumans() : '' }}
                            </div>
                        </div>
                    @empty
                        <div class="dash-activity-item">
                            <i class="fa-solid fa-circle-info"></i>
                            <div class="dash-activity-text">No recent candidates</div>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="stack">
                <div class="panel">
                    <h2>Need Help?</h2>
                    <div class="help-body">
                        <i class="fa-solid fa-headset"></i>
                        <div>
                            <p>Questions about the pipeline or offer templates? Reach out to HR.</p>
                            <button type="button" class="nks-btn nks-btn--outline">
                                <i class="fa-solid fa-headset"></i>
                                Contact HR
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif
</div>