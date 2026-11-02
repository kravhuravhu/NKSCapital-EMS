<div class="page-queue-monitor">
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

    <div class="qm-stats" @if ($autoRefresh) wire:poll.15s="loadStats" @endif>
        <div class="qm-stat">
            <div class="qm-stat-icon qm-stat-icon--blue">
                <i class="fa-solid fa-hourglass-half"></i>
            </div>
            <div>
                <div class="qm-stat-label">Pending Jobs</div>
                <div class="qm-stat-value">{{ $stats['pending'] ?? 0 }}</div>
            </div>
        </div>
        <div class="qm-stat">
            <div class="qm-stat-icon qm-stat-icon--red">
                <i class="fa-solid fa-circle-xmark"></i>
            </div>
            <div>
                <div class="qm-stat-label">Failed Jobs</div>
                <div class="qm-stat-value">{{ $stats['failed'] ?? 0 }}</div>
            </div>
        </div>
        <div class="qm-stat">
            <div class="qm-stat-icon qm-stat-icon--purple">
                <i class="fa-solid fa-layer-group"></i>
            </div>
            <div>
                <div class="qm-stat-label">Batches</div>
                <div class="qm-stat-value">{{ $stats['batches'] ?? 0 }}</div>
            </div>
        </div>
        <div class="qm-stat">
            <div class="qm-stat-icon">
                <i class="fa-solid fa-server"></i>
            </div>
            <div>
                <div class="qm-stat-label">Driver</div>
                <div class="qm-stat-value" style="font-size: 14px;">{{ $stats['driver'] ?? '—' }}</div>
            </div>
        </div>
    </div>

    <div class="panel qm-panel">
        <div class="qm-header">
            <i class="fa-solid fa-list-check qm-header-icon"></i>
            <div>
                <h2>Queue Monitor</h2>
                <p>Live view of pending and failed background jobs</p>
            </div>
            <div class="qm-header-actions">
                <label class="qm-auto">
                    <input type="checkbox" wire:click="toggleAutoRefresh" @checked($autoRefresh)>
                    <span>Auto-refresh stats (15s)</span>
                </label>
                <button type="button" wire:click="refreshAll" class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-rotate"></i>
                    Refresh
                </button>
            </div>
        </div>

        <nav class="nks-tabs qm-tabs">
            <button type="button"
                    wire:click="selectTab('pending')"
                    class="nks-tab {{ $tab === 'pending' ? 'is-active' : '' }}">
                Pending ({{ $pendingPagination['total'] ?? 0 }})
            </button>
            <button type="button"
                    wire:click="selectTab('failed')"
                    class="nks-tab {{ $tab === 'failed' ? 'is-active' : '' }}">
                Failed ({{ $failedPagination['total'] ?? 0 }})
            </button>
        </nav>

        @if ($loading)
            <x-nks.spinner block label="Loading queue…" />
        @elseif ($tab === 'pending')
            @if (count($pendingJobs) === 0)
                <div class="qm-empty">
                    <i class="fa-solid fa-circle-check"></i>
                    <strong>Queue is clear</strong>
                    <span>No pending jobs. New work appears here in real time.</span>
                </div>
            @else
                <div class="qm-toolbar">
                    <button type="button" wire:click="openClearPending" class="nks-btn nks-btn--danger nks-btn--sm">
                        <i class="fa-solid fa-trash-can"></i>
                        Clear Pending Queue
                    </button>
                </div>

                <div class="qm-table-wrapper">
                    <table class="qm-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Queue</th>
                                <th>Job Class</th>
                                <th style="width: 80px; text-align: right;">Attempts</th>
                                <th>Available At</th>
                                <th>Created</th>
                                <th style="width: 120px;">State</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pendingJobs as $job)
                                <tr wire:key="qj-{{ $job['id'] }}">
                                    <td>
                                        <span class="qm-id">#{{ $job['id'] }}</span>
                                    </td>
                                    <td>
                                        <x-nks.badge tone="blue">{{ $job['queue'] ?? 'default' }}</x-nks.badge>
                                    </td>
                                    <td>
                                        <div class="qm-job-class">{{ $job['job_class'] ?? 'Unknown' }}</div>
                                    </td>
                                    <td style="text-align: right;">{{ $job['attempts'] ?? 0 }}</td>
                                    <td>
                                        {{ !empty($job['available_at']) ? \Carbon\Carbon::parse($job['available_at'])->format('d M Y H:i') : '—' }}
                                    </td>
                                    <td>
                                        {{ !empty($job['created_at']) ? \Carbon\Carbon::parse($job['created_at'])->diffForHumans() : '—' }}
                                    </td>
                                    <td>
                                        @if (!empty($job['is_reserved']))
                                            <x-nks.badge tone="gold">Processing</x-nks.badge>
                                        @elseif (!empty($job['is_delayed']))
                                            <x-nks.badge tone="blue">Delayed</x-nks.badge>
                                        @else
                                            <x-nks.badge tone="success">Ready</x-nks.badge>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (($pendingPagination['last_page'] ?? 1) > 1)
                    <div class="qm-pagination">
                        <button type="button" wire:click="gotoPendingPage({{ max(1, $pendingPagination['current_page'] - 1) }})"
                                class="nks-btn nks-btn--outline nks-btn--sm"
                                @if ($pendingPagination['current_page'] <= 1) disabled @endif>
                            <i class="fa-solid fa-chevron-left"></i> Previous
                        </button>
                        <span class="qm-page-info">
                            Page {{ $pendingPagination['current_page'] }} of {{ $pendingPagination['last_page'] }}
                            ({{ $pendingPagination['total'] }} total)
                        </span>
                        <button type="button" wire:click="gotoPendingPage({{ min($pendingPagination['last_page'], $pendingPagination['current_page'] + 1) }})"
                                class="nks-btn nks-btn--outline nks-btn--sm"
                                @if ($pendingPagination['current_page'] >= $pendingPagination['last_page']) disabled @endif>
                            Next <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                @endif
            @endif
        @else
            @if (count($failedJobs) === 0)
                <div class="qm-empty">
                    <i class="fa-solid fa-circle-check"></i>
                    <strong>No failed jobs</strong>
                    <span>Everything is running cleanly.</span>
                </div>
            @else
                <div class="qm-toolbar">
                    <button type="button" wire:click="openRetryAll" class="nks-btn nks-btn--outline nks-btn--sm">
                        <i class="fa-solid fa-rotate-right"></i>
                        Retry All
                    </button>
                    <button type="button" wire:click="openFlushFailed" class="nks-btn nks-btn--danger nks-btn--sm">
                        <i class="fa-solid fa-trash-can"></i>
                        Flush Failed
                    </button>
                </div>

                <div class="qm-table-wrapper">
                    <table class="qm-table">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Queue</th>
                                <th>Job Class</th>
                                <th>Exception</th>
                                <th>Failed</th>
                                <th style="width: 160px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($failedJobs as $job)
                                <tr wire:key="qfj-{{ $job['id'] }}">
                                    <td>
                                        <span class="qm-id">#{{ $job['id'] }}</span>
                                    </td>
                                    <td>
                                        <x-nks.badge tone="red">{{ $job['queue'] ?? 'default' }}</x-nks.badge>
                                    </td>
                                    <td>
                                        <div class="qm-job-class">{{ $job['job_class'] ?? 'Unknown' }}</div>
                                    </td>
                                    <td>
                                        <div class="qm-exc-summary">{{ $job['exception_summary'] ?? '—' }}</div>
                                        @if ($expandedFailedId === $job['id'])
                                            <pre class="qm-exc-full">{{ $job['exception_full'] ?? '' }}</pre>
                                        @endif
                                        @if (!empty($job['exception_full']))
                                            <button type="button"
                                                    wire:click="toggleFailed({{ $job['id'] }})"
                                                    class="qm-exc-toggle">
                                                {{ $expandedFailedId === $job['id'] ? 'Hide full trace' : 'View full trace' }}
                                            </button>
                                        @endif
                                    </td>
                                    <td>
                                        {{ !empty($job['failed_at']) ? \Carbon\Carbon::parse($job['failed_at'])->diffForHumans() : '—' }}
                                    </td>
                                    <td>
                                        <div class="qm-row-actions">
                                            <button type="button"
                                                    wire:click="retryJob({{ $job['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-rotate-right"></i>
                                                Retry
                                            </button>
                                            <button type="button"
                                                    wire:click="forgetJob({{ $job['id'] }})"
                                                    class="nks-btn nks-btn--danger nks-btn--sm">
                                                <i class="fa-solid fa-trash-can"></i>
                                                Forget
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if (($failedPagination['last_page'] ?? 1) > 1)
                    <div class="qm-pagination">
                        <button type="button" wire:click="gotoFailedPage({{ max(1, $failedPagination['current_page'] - 1) }})"
                                class="nks-btn nks-btn--outline nks-btn--sm"
                                @if ($failedPagination['current_page'] <= 1) disabled @endif>
                            <i class="fa-solid fa-chevron-left"></i> Previous
                        </button>
                        <span class="qm-page-info">
                            Page {{ $failedPagination['current_page'] }} of {{ $failedPagination['last_page'] }}
                            ({{ $failedPagination['total'] }} total)
                        </span>
                        <button type="button" wire:click="gotoFailedPage({{ min($failedPagination['last_page'], $failedPagination['current_page'] + 1) }})"
                                class="nks-btn nks-btn--outline nks-btn--sm"
                                @if ($failedPagination['current_page'] >= $failedPagination['last_page']) disabled @endif>
                            Next <i class="fa-solid fa-chevron-right"></i>
                        </button>
                    </div>
                @endif
            @endif
        @endif
    </div>

    @if ($showRetryAll || $showFlushFailed || $showClearPending)
        @php
            $title = $showRetryAll ? 'Retry All Failed Jobs'
                : ($showFlushFailed ? 'Flush Failed Jobs'
                : 'Clear Pending Queue');
            $count = $showRetryAll ? ($failedPagination['total'] ?? 0)
                : ($showFlushFailed ? ($failedPagination['total'] ?? 0)
                : ($pendingPagination['total'] ?? 0));
            $danger = $showFlushFailed || $showClearPending;
        @endphp
        <div class="qm-modal-backdrop" wire:click.self="closeAction">
            <div class="qm-modal">
                <header class="qm-modal-head">
                    <h3>{{ $title }}</h3>
                    <button type="button" wire:click="closeAction" class="qm-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="qm-modal-body">
                    <p class="qm-text">
                        @if ($showRetryAll)
                            Push <strong>{{ $count }}</strong> failed job(s) back onto the queue? Jobs will be retried by the workers immediately.
                        @elseif ($showFlushFailed)
                            Permanently delete <strong>{{ $count }}</strong> failed job(s)? This cannot be undone.
                        @else
                            Permanently delete <strong>{{ $count }}</strong> pending job(s)? Any work waiting in the queue will be lost. This cannot be undone.
                        @endif
                    </p>
                    @if ($danger)
                        <div class="qm-danger-warning">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span>This is a destructive action and will be logged in the audit trail.</span>
                        </div>
                    @endif
                    <div class="nks-field">
                        <label class="nks-label">Admin Password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="actionPassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="qm-modal-foot">
                    <button type="button" wire:click="closeAction" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="submitAction"
                            wire:loading.attr="disabled"
                            wire:target="submitAction"
                            class="nks-btn {{ $danger ? 'nks-btn--danger' : 'nks-btn--primary' }}">
                        <i class="fa-solid fa-check"></i>
                        Confirm
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>