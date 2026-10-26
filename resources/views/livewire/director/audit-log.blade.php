<div class="page-audit-log">
    @if ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($flash)
        <div class="nks-notice" style="background: var(--nks-mint); color: #1F6B45;">
            <i class="fa-solid fa-circle-check" style="color: #1F6B45;"></i>
            <div class="nks-notice-body">
                <strong style="color: #1F6B45; display: block;">{{ $flash }}</strong>
                @if ($lastExport)
                    <a href="{{ $lastExport['download_url'] }}"
                       download
                       class="al-export-link">
                        <i class="fa-solid fa-download"></i>
                        Download {{ strtoupper($lastExport['format'] ?? 'CSV') }}
                        ({{ $lastExport['record_count'] ?? 0 }} records)
                    </a>
                @endif
            </div>
        </div>
    @endif

    <div class="al-stats">
        <div class="al-stat">
            <div class="al-stat-head">
                <i class="fa-solid fa-list"></i>
                <span>Total Events</span>
            </div>
            <div class="al-stat-value">{{ $stats['total_events'] ?? 0 }}</div>
            <div class="al-stat-note">Last 7 days by default</div>
        </div>
        <div class="al-stat al-stat--success">
            <div class="al-stat-head">
                <i class="fa-solid fa-circle-check"></i>
                <span>Success</span>
            </div>
            <div class="al-stat-value">{{ $stats['success'] ?? 0 }}</div>
            <div class="al-stat-note">Normal operations</div>
        </div>
        <div class="al-stat al-stat--warning">
            <div class="al-stat-head">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span>Warnings</span>
            </div>
            <div class="al-stat-value">{{ $stats['warnings'] ?? 0 }}</div>
            <div class="al-stat-note">4xx and soft failures</div>
        </div>
        <div class="al-stat al-stat--danger">
            <div class="al-stat-head">
                <i class="fa-solid fa-circle-xmark"></i>
                <span>Errors</span>
            </div>
            <div class="al-stat-value">{{ $stats['errors'] ?? 0 }}</div>
            <div class="al-stat-note">5xx and thrown exceptions</div>
        </div>
        <div class="al-stat">
            <div class="al-stat-head">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Slow Requests</span>
            </div>
            <div class="al-stat-value">{{ $stats['slow_requests'] ?? 0 }}</div>
            <div class="al-stat-note">Over 2 seconds</div>
        </div>
    </div>

    <div class="panel al-panel">
        <div class="al-header">
            <i class="fa-solid fa-shield-halved al-header-icon"></i>
            <div>
                <h2>Audit Log</h2>
                <p>Immutable, hash-chained record of every system action</p>
            </div>
            <div class="al-header-actions">
                <button type="button"
                        wire:click="verifyChain"
                        wire:loading.attr="disabled"
                        wire:target="verifyChain"
                        class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-circle-check"></i>
                    {{ $verifying ? 'Verifying…' : 'Verify Chain' }}
                </button>
                <button type="button"
                        wire:click="export"
                        wire:loading.attr="disabled"
                        wire:target="export"
                        class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-file-csv"></i>
                    Export CSV
                </button>
            </div>
        </div>

        @if ($chainResult)
            <div class="al-chain-badge {{ $chainResult['is_valid'] ? 'is-valid' : 'is-invalid' }}">
                <i class="fa-solid {{ $chainResult['is_valid'] ? 'fa-shield-halved' : 'fa-triangle-exclamation' }}"></i>
                <div>
                    <strong>
                        {{ $chainResult['is_valid'] ? 'Chain integrity verified' : 'Chain integrity failed' }}
                    </strong>
                    <span>
                        {{ $chainResult['total_logs'] ?? 0 }} entries checked
                        @if (!$chainResult['is_valid'] && !empty($chainResult['broken_at']))
                            · broken at log #{{ $chainResult['broken_at'] }}
                        @endif
                        @if (!empty($chainResult['verified_at']))
                            · verified {{ \Carbon\Carbon::parse($chainResult['verified_at'])->diffForHumans() }}
                        @endif
                    </span>
                </div>
            </div>
        @endif

        <div class="al-filters">
            <div class="al-filter-row">
                <div class="nks-field al-filter-search">
                    <label class="nks-label">Search</label>
                    <input type="text"
                           wire:model="search"
                           wire:keydown.enter="applyFilters"
                           class="nks-input"
                           placeholder="Action, error message, request path…">
                </div>

                <div class="nks-field">
                    <label class="nks-label">Log Type</label>
                    <select wire:model="logType" class="nks-select">
                        <option value="">Any</option>
                        <option value="info">Info</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                    </select>
                </div>

                <div class="nks-field">
                    <label class="nks-label">Severity</label>
                    <select wire:model="severity" class="nks-select">
                        <option value="">Any</option>
                        <option value="info">Info</option>
                        <option value="success">Success</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>

                <div class="nks-field">
                    <label class="nks-label">From</label>
                    <input type="date" wire:model="from" class="nks-input">
                </div>

                <div class="nks-field">
                    <label class="nks-label">To</label>
                    <input type="date" wire:model="to" class="nks-input">
                </div>
            </div>

            <div class="al-filter-row al-filter-row--bottom">
                <label class="al-checkbox">
                    <input type="checkbox" wire:model="isSlow">
                    <span>Slow requests only</span>
                </label>

                <div class="al-filter-actions">
                    <button type="button" wire:click="resetFilters" class="nks-btn nks-btn--ghost nks-btn--sm">
                        <i class="fa-solid fa-rotate-left"></i>
                        Reset
                    </button>
                    <button type="button" wire:click="applyFilters" class="nks-btn nks-btn--primary nks-btn--sm">
                        <i class="fa-solid fa-filter"></i>
                        Apply Filters
                    </button>
                </div>
            </div>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading audit logs…" />
        @elseif (count($logs) === 0)
            <div class="al-empty">
                <i class="fa-solid fa-magnifying-glass"></i>
                <strong>No audit logs match your filters</strong>
                <span>Try widening the date range, clearing the search box, or resetting the filters.</span>
            </div>
        @else
            <div class="al-table-wrapper">
                <table class="al-table">
                    <thead>
                        <tr>
                            <th style="width: 140px;">Timestamp</th>
                            <th>Action</th>
                            <th style="width: 90px;">Type</th>
                            <th style="width: 90px;">Severity</th>
                            <th>User</th>
                            <th style="width: 90px;">Status</th>
                            <th style="width: 80px;">Duration</th>
                            <th style="width: 60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr wire:key="al-{{ $log['id'] }}">
                                <td>
                                    <div class="al-timestamp">
                                        {{ \Carbon\Carbon::parse($log['timestamp'])->format('d M Y') }}
                                    </div>
                                    <div class="al-timestamp-time">
                                        {{ \Carbon\Carbon::parse($log['timestamp'])->format('H:i:s') }}
                                    </div>
                                </td>
                                <td>
                                    <div class="al-action">{{ $log['action'] ?? '—' }}</div>
                                    @if (!empty($log['request_path']))
                                        <div class="al-action-path">{{ $log['request_path'] }}</div>
                                    @endif
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Director\AuditLog::logTypeTone($log['log_type'] ?? '')">
                                        {{ ucfirst($log['log_type'] ?? '—') }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Director\AuditLog::severityTone($log['severity'] ?? '')">
                                        {{ ucfirst($log['severity'] ?? '—') }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    @if (!empty($log['user']))
                                        <div class="al-user-name">
                                            {{ $log['user']['first_name'] ?? '' }}
                                            {{ $log['user']['last_name'] ?? '' }}
                                        </div>
                                        <div class="al-user-email">{{ $log['user']['email'] ?? '' }}</div>
                                    @else
                                        <span class="al-muted">system</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!empty($log['http_status']))
                                        <x-nks.badge :tone="\App\Livewire\Director\AuditLog::httpTone((int) $log['http_status'])">
                                            {{ $log['http_status'] }}
                                        </x-nks.badge>
                                    @else
                                        <span class="al-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!empty($log['duration_ms']))
                                        <span class="al-duration {{ !empty($log['is_slow']) ? 'is-slow' : '' }}">
                                            {{ (int) $log['duration_ms'] }} ms
                                        </span>
                                    @else
                                        <span class="al-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <button type="button"
                                            wire:click="view({{ $log['id'] }})"
                                            class="al-row-btn"
                                            title="View details">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (($pagination['last_page'] ?? 1) > 1)
                <div class="al-pagination">
                    <button type="button"
                            wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] <= 1) disabled @endif>
                        <i class="fa-solid fa-chevron-left"></i>
                        Previous
                    </button>
                    <span class="al-page-info">
                        Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                        ({{ $pagination['total'] }} total)
                    </span>
                    <button type="button"
                            wire:click="gotoPage({{ min($pagination['last_page'], $pagination['current_page'] + 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] >= $pagination['last_page']) disabled @endif>
                        Next
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            @endif
        @endif
    </div>

    @if ($showDetail && $detail)
        <div class="al-modal-backdrop" wire:click.self="closeDetail">
            <div class="al-modal al-modal--lg">
                <header class="al-modal-head">
                    <div>
                        <h3>{{ $detail['action'] ?? 'Log Entry' }}</h3>
                        <span class="al-modal-sub">
                            {{ \Carbon\Carbon::parse($detail['timestamp'])->format('d M Y H:i:s') }}
                        </span>
                    </div>
                    <button type="button" wire:click="closeDetail" class="al-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="al-modal-body">
                    <div class="al-detail-grid">
                        <div>
                            <span class="al-detail-label">Log Type</span>
                            <span class="al-detail-value">
                                <x-nks.badge :tone="\App\Livewire\Director\AuditLog::logTypeTone($detail['log_type'] ?? '')">
                                    {{ ucfirst($detail['log_type'] ?? '—') }}
                                </x-nks.badge>
                            </span>
                        </div>
                        <div>
                            <span class="al-detail-label">Severity</span>
                            <span class="al-detail-value">
                                <x-nks.badge :tone="\App\Livewire\Director\AuditLog::severityTone($detail['severity'] ?? '')">
                                    {{ ucfirst($detail['severity'] ?? '—') }}
                                </x-nks.badge>
                            </span>
                        </div>
                        <div>
                            <span class="al-detail-label">HTTP Status</span>
                            <span class="al-detail-value">{{ $detail['http_status'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="al-detail-label">Duration</span>
                            <span class="al-detail-value">
                                {{ !empty($detail['duration_ms']) ? (int) $detail['duration_ms'] . ' ms' : '—' }}
                                @if (!empty($detail['is_slow']))
                                    <span class="al-slow-tag">SLOW</span>
                                @endif
                            </span>
                        </div>
                        <div>
                            <span class="al-detail-label">Request Method</span>
                            <span class="al-detail-value">{{ $detail['request_method'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="al-detail-label">IP Address</span>
                            <span class="al-detail-value">{{ $detail['ip_address'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="al-detail-label">Request ID</span>
                            <span class="al-detail-value al-mono">{{ $detail['request_id'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="al-detail-label">Table</span>
                            <span class="al-detail-value">{{ $detail['table_name'] ?? '—' }}</span>
                        </div>
                    </div>

                    @if (!empty($detail['request_path']))
                        <div class="al-detail-section">
                            <span class="al-detail-label">Request Path</span>
                            <p class="al-detail-code">{{ $detail['request_path'] }}</p>
                        </div>
                    @endif

                    @if (!empty($detail['user']))
                        <div class="al-detail-section">
                            <span class="al-detail-label">User</span>
                            <p class="al-detail-text">
                                {{ $detail['user']['first_name'] ?? '' }}
                                {{ $detail['user']['last_name'] ?? '' }}
                                ({{ $detail['user']['email'] ?? '—' }})
                            </p>
                        </div>
                    @endif

                    @if (!empty($detail['old_values']))
                        <div class="al-detail-section">
                            <span class="al-detail-label">Old Values</span>
                            <pre class="al-json">{{ json_encode($detail['old_values'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    @endif

                    @if (!empty($detail['new_values']))
                        <div class="al-detail-section">
                            <span class="al-detail-label">New Values</span>
                            <pre class="al-json">{{ json_encode($detail['new_values'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    @endif

                    @if (!empty($detail['error_message']))
                        <div class="al-detail-section">
                            <span class="al-detail-label">Error Message</span>
                            <p class="al-error-text">{{ $detail['error_message'] }}</p>
                        </div>
                    @endif

                    @if (!empty($detail['user_agent']))
                        <div class="al-detail-section">
                            <span class="al-detail-label">User Agent</span>
                            <p class="al-detail-text al-mono">{{ $detail['user_agent'] }}</p>
                        </div>
                    @endif
                </div>
                <footer class="al-modal-foot">
                    <button type="button" wire:click="closeDetail" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>