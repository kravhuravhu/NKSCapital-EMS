<div class="page-team-assets">
    @if ($error)
        <div class="nks-notice" style="background: #F5B5B5; color: #8A2A2A;">
            <i class="fa-solid fa-circle-exclamation" style="color: #8A2A2A;"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($loading)
        <x-nks.spinner block label="Loading team assets…" />
    @else
        <div class="ta-stats">
            <div class="ta-stat">
                <div class="ta-stat-head">
                    <i class="fa-solid fa-laptop"></i>
                    <span>Loaned</span>
                </div>
                <div class="ta-stat-value">{{ $stats['loaned'] ?? 0 }}</div>
                <div class="ta-stat-note">Out with employees</div>
            </div>
            <div class="ta-stat ta-stat--warning">
                <div class="ta-stat-head">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span>Overdue</span>
                </div>
                <div class="ta-stat-value">{{ $stats['overdue'] ?? 0 }}</div>
                <div class="ta-stat-note">Past expected return</div>
            </div>
            <div class="ta-stat">
                <div class="ta-stat-head">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                    <span>In Repair</span>
                </div>
                <div class="ta-stat-value">{{ $stats['in_repair'] ?? 0 }}</div>
                <div class="ta-stat-note">Maintenance or repair</div>
            </div>
            <div class="ta-stat">
                <div class="ta-stat-head">
                    <i class="fa-solid fa-coins"></i>
                    <span>Total Value</span>
                </div>
                <div class="ta-stat-value">
                    R {{ number_format($stats['total_value'] ?? 0, 0) }}
                </div>
                <div class="ta-stat-note">Across all assets</div>
            </div>
        </div>

        <div class="panel ta-panel">
            <div class="ta-header">
                <i class="fa-solid fa-laptop ta-header-icon"></i>
                <div>
                    <h2>Team Assets</h2>
                    <p>Loaned devices across your team</p>
                </div>
                <div class="ta-header-search">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text"
                           wire:model.live.debounce.500ms="search"
                           class="ta-search-input"
                           placeholder="Search by tag, model, or assignee">
                </div>
            </div>

            <nav class="nks-tabs ta-tabs">
                <button type="button"
                        wire:click="selectFilter('all')"
                        class="nks-tab {{ $filter === 'all' ? 'is-active' : '' }}">
                    All Loaned
                </button>
                <button type="button"
                        wire:click="selectFilter('overdue')"
                        class="nks-tab {{ $filter === 'overdue' ? 'is-active' : '' }}">
                    Overdue
                </button>
                <button type="button"
                        wire:click="selectFilter('my_department')"
                        class="nks-tab {{ $filter === 'my_department' ? 'is-active' : '' }}">
                    My Department
                </button>
            </nav>

            @if (count($assets) === 0)
                <div class="ta-empty">
                    <i class="fa-solid fa-laptop-slash"></i>
                    <strong>No loaned assets</strong>
                    <span>
                        @if ($filter === 'overdue')
                            Nothing is overdue right now.
                        @elseif ($filter === 'my_department')
                            No loaned assets in your department.
                        @else
                            When assets are loaned to your team, they will appear here.
                        @endif
                    </span>
                </div>
            @else
                <div class="ta-table-wrapper">
                    <table class="ta-table">
                        <thead>
                            <tr>
                                <th>Asset</th>
                                <th>Assignee</th>
                                <th>Condition</th>
                                <th>Status</th>
                                <th>Expected Return</th>
                                <th>Overdue</th>
                                <th style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($assets as $asset)
                                <tr wire:key="ta-{{ $asset['id'] }}">
                                    <td>
                                        <div class="ta-asset">
                                            <div class="ta-asset-icon">
                                                <i class="fa-solid fa-laptop"></i>
                                            </div>
                                            <div>
                                                <div class="ta-asset-name">{{ $asset['model'] ?? '—' }}</div>
                                                <div class="ta-asset-meta">
                                                    {{ $asset['asset_tag'] ?? '' }}
                                                    @if (!empty($asset['serial_number']))
                                                        · {{ $asset['serial_number'] }}
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        @if (!empty($asset['current_assignee']))
                                            <div class="ta-assignee">
                                                <div class="ta-assignee-name">
                                                    {{ $asset['current_assignee']['first_name'] ?? '' }}
                                                    {{ $asset['current_assignee']['last_name'] ?? '' }}
                                                </div>
                                                <div class="ta-assignee-meta">
                                                    {{ $asset['current_assignee']['employee_number'] ?? '' }}
                                                    @if (!empty($asset['current_assignee']['department']))
                                                        · {{ $asset['current_assignee']['department'] }}
                                                    @endif
                                                </div>
                                            </div>
                                        @else
                                            <span class="ta-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <x-nks.badge :tone="\App\Livewire\Manager\TeamAssets::conditionTone($asset['condition'] ?? '')">
                                            {{ ucfirst(str_replace('_', ' ', $asset['condition'] ?? '')) }}
                                        </x-nks.badge>
                                    </td>
                                    <td>
                                        <x-nks.badge :tone="\App\Livewire\Manager\TeamAssets::statusTone($asset['status'] ?? '')">
                                            {{ ucfirst(str_replace('_', ' ', $asset['status'] ?? '')) }}
                                        </x-nks.badge>
                                    </td>
                                    <td>
                                        {{ !empty($asset['expected_return_date']) ? \Carbon\Carbon::parse($asset['expected_return_date'])->format('d M Y') : '—' }}
                                    </td>
                                    <td>
                                        @if (!empty($asset['is_overdue']))
                                            <span class="ta-overdue">
                                                <i class="fa-solid fa-triangle-exclamation"></i>
                                                {{ (int) ($asset['days_overdue'] ?? 0) }} days
                                            </span>
                                        @else
                                            <span class="ta-muted">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <button type="button"
                                                wire:click="view({{ $asset['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i>
                                            View
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if ($showView && $viewData)
        @php $asset = $viewData['asset'] ?? []; @endphp
        <div class="ta-modal-backdrop" wire:click.self="closeView">
            <div class="ta-modal ta-modal--lg">
                <header class="ta-modal-head">
                    <h3>Asset Details</h3>
                    <button type="button" wire:click="closeView" class="ta-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ta-modal-body">
                    <div class="ta-detail-meta">
                        <div>
                            <span class="ta-detail-label">Asset Tag</span>
                            <span class="ta-detail-value">{{ $asset['asset_tag'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ta-detail-label">Serial</span>
                            <span class="ta-detail-value">{{ $asset['serial_number'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ta-detail-label">Model</span>
                            <span class="ta-detail-value">{{ $asset['model'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ta-detail-label">Manufacturer</span>
                            <span class="ta-detail-value">{{ $asset['manufacturer'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ta-detail-label">Condition</span>
                            <span class="ta-detail-value">{{ ucfirst(str_replace('_', ' ', $asset['condition'] ?? '—')) }}</span>
                        </div>
                        <div>
                            <span class="ta-detail-label">Status</span>
                            <span class="ta-detail-value">{{ ucfirst(str_replace('_', ' ', $asset['status'] ?? '—')) }}</span>
                        </div>
                        <div>
                            <span class="ta-detail-label">Location</span>
                            <span class="ta-detail-value">{{ $asset['location'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ta-detail-label">Purchased</span>
                            <span class="ta-detail-value">
                                {{ !empty($asset['purchase_date']) ? \Carbon\Carbon::parse($asset['purchase_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                    </div>

                    <div class="ta-detail-section">
                        <h4 class="ta-detail-title">Custody History</h4>

                        @if (empty($viewData['custody_history']))
                            <div class="ta-detail-empty">
                                <i class="fa-solid fa-circle-info"></i>
                                No custody records for this asset.
                            </div>
                        @else
                            <div class="ta-history-wrapper">
                                <table class="ta-history-table">
                                    <thead>
                                        <tr>
                                            <th>Assigned To</th>
                                            <th>Checkout</th>
                                            <th>Expected Return</th>
                                            <th>Returned</th>
                                            <th>Return Condition</th>
                                            <th>Repair Priority</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($viewData['custody_history'] as $hist)
                                            <tr>
                                                <td>
                                                    @if (!empty($hist['assigned_to']))
                                                        {{ $hist['assigned_to']['first_name'] ?? '' }}
                                                        {{ $hist['assigned_to']['last_name'] ?? '' }}
                                                        <div class="ta-muted" style="font-size: 10px;">
                                                            {{ $hist['assigned_to']['employee_number'] ?? '' }}
                                                        </div>
                                                    @else
                                                        <span class="ta-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>{{ !empty($hist['checkout_date']) ? \Carbon\Carbon::parse($hist['checkout_date'])->format('d M Y') : '—' }}</td>
                                                <td>{{ !empty($hist['expected_return_date']) ? \Carbon\Carbon::parse($hist['expected_return_date'])->format('d M Y') : '—' }}</td>
                                                <td>
                                                    @if (!empty($hist['actual_return_date']))
                                                        {{ \Carbon\Carbon::parse($hist['actual_return_date'])->format('d M Y') }}
                                                    @else
                                                        <span class="ta-muted">Still out</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if (!empty($hist['return_condition']))
                                                        <x-nks.badge :tone="\App\Livewire\Manager\TeamAssets::conditionTone($hist['return_condition'])">
                                                            {{ ucfirst(str_replace('_', ' ', $hist['return_condition'])) }}
                                                        </x-nks.badge>
                                                    @else
                                                        <span class="ta-muted">—</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if (!empty($hist['repair_priority']))
                                                        <span class="ta-priority ta-priority--{{ $hist['repair_priority'] }}">
                                                            {{ ucfirst($hist['repair_priority']) }}
                                                        </span>
                                                    @else
                                                        <span class="ta-muted">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>
                <footer class="ta-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>