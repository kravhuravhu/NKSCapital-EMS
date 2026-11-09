<div class="page-audit-log">
    @if ($error)
        <div class="nks-notice" style="background: rgba(194, 91, 91, 0.1); color: var(--nks-danger);">
            <i class="fa-solid fa-circle-exclamation" style="color: var(--nks-danger);"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($flash)
        <div class="nks-notice" style="background: var(--nks-mint); color: #1F6B45;">
            <i class="fa-solid fa-circle-check" style="color: #1F6B45;"></i>
            <div class="nks-notice-body">
                <strong style="color: #1F6B45; display: block;">{{ $flash }}</strong>
                @if ($lastExport)
                    <a href="{{ $lastExport['download_url'] }}" download class="al-export-link">
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
            <div class="al-stat-head"><i class="fa-solid fa-list"></i><span>Total Events</span></div>
            <div class="al-stat-value">{{ $stats['total_events'] ?? 0 }}</div>
            <div class="al-stat-note">Selected period</div>
        </div>
        <div class="al-stat al-stat--success">
            <div class="al-stat-head"><i class="fa-solid fa-circle-check"></i><span>Success</span></div>
            <div class="al-stat-value">{{ $stats['success'] ?? 0 }}</div>
            <div class="al-stat-note">Normal operations</div>
        </div>
        <div class="al-stat al-stat--warning">
            <div class="al-stat-head"><i class="fa-solid fa-triangle-exclamation"></i><span>Warnings</span></div>
            <div class="al-stat-value">{{ $stats['warnings'] ?? 0 }}</div>
            <div class="al-stat-note">Soft failures</div>
        </div>
        <div class="al-stat al-stat--danger">
            <div class="al-stat-head"><i class="fa-solid fa-circle-xmark"></i><span>Errors</span></div>
            <div class="al-stat-value">{{ $stats['errors'] ?? 0 }}</div>
            <div class="al-stat-note">Thrown exceptions</div>
        </div>
        <div class="al-stat">
            <div class="al-stat-head"><i class="fa-solid fa-gauge-high"></i><span>Slow</span></div>
            <div class="al-stat-value">{{ $stats['slow_requests'] ?? 0 }}</div>
            <div class="al-stat-note">Over 2 seconds</div>
        </div>
    </div>

    <div class="panel">
        <div class="al-header">
            <i class="fa-solid fa-shield-halved al-header-icon"></i>
            <div>
                <h2>Audit log</h2>
                <p>Immutable, hash chained record of every business event</p>
            </div>
            <div class="al-header-actions">
                <button type="button"
                        wire:click="verifyChain"
                        wire:loading.attr="disabled"
                        wire:target="verifyChain"
                        class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-circle-check"></i>
                    Verify chain
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
                        @if (! $chainResult['is_valid'] && ! empty($chainResult['broken_at']))
                            &middot; broken at log #{{ $chainResult['broken_at'] }}
                        @endif
                    </span>
                </div>
            </div>
        @endif

        <div class="al-filters">
            <div class="al-filter-row">
                <div class="nks-field al-filter-search">
                    <label class="nks-label">Search</label>
                    <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                           class="nks-input" placeholder="Action, user, record or IP">
                </div>
                <div class="nks-field">
                    <label class="nks-label">Log type</label>
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
                        Apply
                    </button>
                </div>
            </div>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading audit logs..." />
        @elseif (count($logs) === 0)
            <div class="al-empty">
                <i class="fa-solid fa-magnifying-glass"></i>
                <strong>No audit entries match your filters</strong>
                <span>Try widening the date range or clearing the search box.</span>
            </div>
        @else
            <div class="al-table-wrapper">
                <table class="al-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Record</th>
                            <th>IP address</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($logs as $log)
                            <tr wire:key="al-{{ $log['id'] }}"
                                wire:click="view({{ $log['id'] }})"
                                class="al-row">
                                <td>
                                    <div class="al-user-cell">
                                        <span class="al-avatar al-avatar--{{ $this->tone($log['action'] ?? '') }}">
                                            {{ $this->initials($log['user'] ?? null) }}
                                        </span>
                                        <div class="al-user-body">
                                            <strong>
                                                @if (! empty($log['user']))
                                                    {{ $log['user']['first_name'] ?? '' }} {{ $log['user']['last_name'] ?? '' }}
                                                @else
                                                    System
                                                @endif
                                            </strong>
                                            @if (! empty($log['user']['email']))
                                                <small>{{ $log['user']['email'] }}</small>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="al-action-verb al-action-verb--{{ $this->tone($log['action'] ?? '') }}">
                                        {{ $this->verb($log['action'] ?? '') }}
                                    </span>
                                    <div class="al-action-full">{{ $log['action'] ?? '' }}</div>
                                </td>
                                <td>
                                    @if (! empty($log['table_name']))
                                        <span class="al-record">{{ $log['table_name'] }}#{{ $log['record_id'] ?? '0' }}</span>
                                    @else
                                        &mdash;
                                    @endif
                                </td>
                                <td><span class="al-ip">{{ $log['ip_address'] ?? '—' }}</span></td>
                                <td>
                                    <div class="al-time">{{ isset($log['timestamp']) ? \Carbon\Carbon::parse($log['timestamp'])->format('d M Y') : '' }}</div>
                                    <div class="al-time-small">{{ isset($log['timestamp']) ? \Carbon\Carbon::parse($log['timestamp'])->format('H:i:s') : '' }}</div>
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
        <div class="nks-modal-backdrop" wire:click.self="closeDetail" @keydown.escape.window="$wire.closeDetail()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <div>
                        <h3>{{ $this->verb($detail['action'] ?? '') }}</h3>
                        <span class="nks-modal-sub">
                            {{ isset($detail['timestamp']) ? \Carbon\Carbon::parse($detail['timestamp'])->format('d M Y H:i:s') : '' }}
                        </span>
                    </div>
                    <button type="button" wire:click="closeDetail" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="al-detail-grid">
                        <div><span class="al-detail-label">Log type</span><span class="al-detail-value">{{ ucfirst($detail['log_type'] ?? '—') }}</span></div>
                        <div><span class="al-detail-label">Severity</span><span class="al-detail-value">{{ ucfirst($detail['severity'] ?? '—') }}</span></div>
                        <div><span class="al-detail-label">HTTP status</span><span class="al-detail-value">{{ $detail['http_status'] ?? '—' }}</span></div>
                        <div><span class="al-detail-label">Duration</span><span class="al-detail-value">{{ ! empty($detail['duration_ms']) ? (int) $detail['duration_ms'] . ' ms' : '—' }}</span></div>
                        <div><span class="al-detail-label">Request</span><span class="al-detail-value">{{ $detail['request_method'] ?? '—' }} {{ $detail['request_path'] ?? '' }}</span></div>
                        <div><span class="al-detail-label">IP</span><span class="al-detail-value">{{ $detail['ip_address'] ?? '—' }}</span></div>
                        <div><span class="al-detail-label">Record</span><span class="al-detail-value">{{ $detail['table_name'] ?? '—' }}#{{ $detail['record_id'] ?? '0' }}</span></div>
                        <div><span class="al-detail-label">Request ID</span><span class="al-detail-value" style="font-family: monospace;">{{ $detail['request_id'] ?? '—' }}</span></div>
                    </div>

                    @if (! empty($detail['old_values']))
                        <div class="al-detail-section">
                            <span class="al-detail-label">Old values</span>
                            <pre class="al-json">{{ json_encode($detail['old_values'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    @endif

                    @if (! empty($detail['new_values']))
                        <div class="al-detail-section">
                            <span class="al-detail-label">New values</span>
                            <pre class="al-json">{{ json_encode($detail['new_values'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                        </div>
                    @endif

                    @if (! empty($detail['error_message']))
                        <div class="al-detail-section">
                            <span class="al-detail-label">Error</span>
                            <p class="al-error-text">{{ $detail['error_message'] }}</p>
                        </div>
                    @endif
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeDetail" class="nks-btn nks-btn--outline">Close</button>
                </footer>
            </div>
        </div>
    @endif
</div>