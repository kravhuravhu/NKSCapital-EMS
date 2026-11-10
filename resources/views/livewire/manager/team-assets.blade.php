<div class="page-team-assets">
    @if ($error)
        <div class="nks-notice" style="background: rgba(194, 91, 91, 0.1); color: var(--nks-danger);">
            <i class="fa-solid fa-circle-exclamation" style="color: var(--nks-danger);"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($loading)
        <x-nks.spinner block label="Loading team assets..." />
    @else
        <div class="ta-stats">
            <div class="ta-stat">
                <div class="ta-stat-head"><i class="fa-solid fa-laptop" aria-hidden="true"></i><span>Total</span></div>
                <div class="ta-stat-value">{{ $stats['total'] ?? 0 }}</div>
                <div class="ta-stat-note">In the company</div>
            </div>
            <div class="ta-stat ta-stat--success">
                <div class="ta-stat-head"><i class="fa-solid fa-user" aria-hidden="true"></i><span>Loaned</span></div>
                <div class="ta-stat-value">{{ $stats['loaned'] ?? 0 }}</div>
                <div class="ta-stat-note">Currently out</div>
            </div>
            <div class="ta-stat ta-stat--warning">
                <div class="ta-stat-head"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i><span>Overdue</span></div>
                <div class="ta-stat-value">{{ $stats['overdue'] ?? 0 }}</div>
                <div class="ta-stat-note">Past expected return</div>
            </div>
            <div class="ta-stat">
                <div class="ta-stat-head"><i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i><span>In repair</span></div>
                <div class="ta-stat-value">{{ $stats['in_repair'] ?? 0 }}</div>
                <div class="ta-stat-note">Maintenance or repair</div>
            </div>
        </div>

        <div class="panel">
            <div class="page-toolbar">
                <div>
                    <h2>Team assets</h2>
                    <p>Assets currently loaned to your team, their status, and history</p>
                </div>
                <div class="page-toolbar-actions">
                    <div class="ta-search">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        <input type="text"
                               wire:model.live.debounce.400ms="search"
                               class="ta-search-input"
                               placeholder="Tag, model, assignee">
                    </div>
                    <select wire:model.live="departmentFilter" class="nks-select">
                        <option value="">All departments</option>
                        @foreach ($departments as $dept)
                            <option value="{{ $dept }}">{{ $dept }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <nav class="nks-tabs">
                <button type="button" wire:click="selectFilter('all')"
                        class="nks-tab {{ $filter === 'all' ? 'is-active' : '' }}">
                    All
                </button>
                <button type="button" wire:click="selectFilter('overdue')"
                        class="nks-tab {{ $filter === 'overdue' ? 'is-active' : '' }}">
                    Overdue
                </button>
                <button type="button" wire:click="selectFilter('maintenance')"
                        class="nks-tab {{ $filter === 'maintenance' ? 'is-active' : '' }}">
                    In repair
                </button>
                <button type="button" wire:click="selectFilter('my_department')"
                        class="nks-tab {{ $filter === 'my_department' ? 'is-active' : '' }}">
                    My department
                </button>
            </nav>

            @if (count($assets) === 0)
                <div class="nks-card-empty">
                    <i class="fa-solid fa-laptop-slash"></i>
                    <p>
                        @if ($filter === 'overdue')
                            No overdue assets. Your team is on top of returns.
                        @elseif ($filter === 'my_department')
                            No loaned assets in your department.
                        @else
                            No assets match your filters.
                        @endif
                    </p>
                </div>
            @else
                <div class="ta-grid">
                    @foreach ($assets as $asset)
                        @php
                            $status = $asset['status'] ?? 'loaned';
                            $condition = $asset['condition'] ?? 'good';
                            $daysOverdue = $this->daysOverdue($asset);
                            $daysLeft = $this->daysUntilReturn($asset);
                            $isOverdue = $daysOverdue > 0;
                            $assignee = $asset['current_assignee'] ?? null;
                            $initials = $assignee
                                ? strtoupper(substr($assignee['first_name'] ?? 'U', 0, 1) . substr($assignee['last_name'] ?? '', 0, 1))
                                : '?';
                        @endphp

                        <article class="ta-card {{ $isOverdue ? 'ta-card--overdue' : '' }}">
                            <header class="ta-card-head">
                                <div class="ta-card-icon">
                                    <i class="{{ self::iconFor($asset) }}" aria-hidden="true"></i>
                                </div>
                                <div class="ta-card-title">
                                    <h3>{{ $asset['model'] ?? 'Asset' }}</h3>
                                    <span>{{ $asset['asset_tag'] ?? '' }}</span>
                                </div>
                                <x-nks.badge :tone="$this->statusTone($status)">
                                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                                </x-nks.badge>
                            </header>

                            @if ($assignee)
                                <div class="ta-assignee-row">
                                    <div class="ta-assignee-avatar">{{ $initials }}</div>
                                    <div class="ta-assignee-info">
                                        <strong>{{ $assignee['first_name'] ?? '' }} {{ $assignee['last_name'] ?? '' }}</strong>
                                        <span>
                                            {{ $assignee['employee_number'] ?? '' }}
                                            @if (! empty($assignee['department']))
                                                &middot; {{ $assignee['department'] }}
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @endif

                            <dl class="ta-card-meta">
                                <div>
                                    <dt>Condition</dt>
                                    <dd>
                                        <x-nks.badge :tone="$this->conditionTone($condition)">
                                            {{ ucfirst(str_replace('_', ' ', $condition)) }}
                                        </x-nks.badge>
                                    </dd>
                                </div>
                                <div>
                                    <dt>Serial</dt>
                                    <dd>{{ $asset['serial_number'] ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt>Expected return</dt>
                                    <dd>
                                        @if (! empty($asset['expected_return_date']))
                                            {{ \Carbon\Carbon::parse($asset['expected_return_date'])->format('d M Y') }}
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>
                                <div>
                                    <dt>{{ $isOverdue ? 'Overdue' : 'Due' }}</dt>
                                    <dd>
                                        @if ($isOverdue)
                                            <span class="ta-overdue">
                                                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                                {{ $daysOverdue }} {{ \Illuminate\Support\Str::plural('day', $daysOverdue) }}
                                            </span>
                                        @elseif ($daysLeft !== null)
                                            <span class="ta-due">
                                                {{ $daysLeft === 0 ? 'Today' : $daysLeft . ' ' . \Illuminate\Support\Str::plural('day', $daysLeft) . ' left' }}
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </dd>
                                </div>
                            </dl>

                            <footer class="ta-card-foot">
                                <button type="button"
                                        wire:click="view({{ $asset['id'] }})"
                                        class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-eye"></i>
                                    View history
                                </button>
                            </footer>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    @if ($showView && $viewData)
        @php
            $asset = $viewData['asset'] ?? [];
            $history = $viewData['custody_history'] ?? [];
        @endphp
        <div class="nks-modal-backdrop" wire:click.self="closeView" @keydown.escape.window="$wire.closeView()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <div>
                        <h3>{{ $asset['model'] ?? 'Asset' }}</h3>
                        <span class="nks-modal-sub">{{ $asset['asset_tag'] ?? '' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>

                <div class="nks-modal-body">
                    <div class="ta-detail-grid">
                        <div>
                            <span class="ta-detail-label">Status</span>
                            <span class="ta-detail-value">
                                <x-nks.badge :tone="$this->statusTone($asset['status'] ?? '')">
                                    {{ ucfirst(str_replace('_', ' ', $asset['status'] ?? '')) }}
                                </x-nks.badge>
                            </span>
                        </div>
                        <div>
                            <span class="ta-detail-label">Condition</span>
                            <span class="ta-detail-value">
                                {{ ucfirst(str_replace('_', ' ', $asset['condition'] ?? '')) }}
                            </span>
                        </div>
                        <div>
                            <span class="ta-detail-label">Serial</span>
                            <span class="ta-detail-value">{{ $asset['serial_number'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ta-detail-label">Manufacturer</span>
                            <span class="ta-detail-value">{{ $asset['manufacturer'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ta-detail-label">Location</span>
                            <span class="ta-detail-value">{{ $asset['location'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ta-detail-label">Purchased</span>
                            <span class="ta-detail-value">
                                {{ ! empty($asset['purchase_date']) ? \Carbon\Carbon::parse($asset['purchase_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                    </div>

                    <h4 class="ta-detail-title">Custody history</h4>

                    @if (count($history) === 0)
                        <div class="nks-card-empty">
                            <i class="fa-solid fa-circle-info"></i>
                            <p>No custody records for this asset.</p>
                        </div>
                    @else
                        <div class="nks-table-wrapper">
                            <table class="nks-table">
                                <thead>
                                    <tr>
                                        <th>Assigned to</th>
                                        <th>Checkout</th>
                                        <th>Expected</th>
                                        <th>Returned</th>
                                        <th>Condition</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($history as $row)
                                        <tr>
                                            <td>
                                                @if (! empty($row['assigned_to']))
                                                    {{ $row['assigned_to']['first_name'] ?? '' }}
                                                    {{ $row['assigned_to']['last_name'] ?? '' }}
                                                    <small style="display: block; color: var(--nks-muted); font-size: 10px;">
                                                        {{ $row['assigned_to']['employee_number'] ?? '' }}
                                                    </small>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ ! empty($row['checkout_date']) ? \Carbon\Carbon::parse($row['checkout_date'])->format('d M Y') : '—' }}</td>
                                            <td>{{ ! empty($row['expected_return_date']) ? \Carbon\Carbon::parse($row['expected_return_date'])->format('d M Y') : '—' }}</td>
                                            <td>
                                                @if (! empty($row['actual_return_date']))
                                                    {{ \Carbon\Carbon::parse($row['actual_return_date'])->format('d M Y') }}
                                                @else
                                                    <span style="color: var(--nks-warning); font-weight: 600;">Still out</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if (! empty($row['return_condition']))
                                                    <x-nks.badge :tone="$this->conditionTone($row['return_condition'])">
                                                        {{ ucfirst(str_replace('_', ' ', $row['return_condition'])) }}
                                                    </x-nks.badge>
                                                @else
                                                    —
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>