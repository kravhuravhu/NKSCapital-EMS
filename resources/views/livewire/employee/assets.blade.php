<div class="page-assets">
    @if ($error)
        <div class="nks-notice" style="background: rgba(194, 91, 91, 0.1); color: var(--nks-danger);">
            <i class="fa-solid fa-circle-exclamation" style="color: var(--nks-danger);"></i>
            <div class="nks-notice-body">{{ $error }}</div>
        </div>
    @endif

    @if ($flash)
        <div class="nks-notice" style="background: var(--nks-mint); color: #1F6B45;">
            <i class="fa-solid fa-circle-check" style="color: #1F6B45;"></i>
            <div class="nks-notice-body">{{ $flash }}</div>
        </div>
    @endif

    @if ($loading)
        <x-nks.spinner block label="Loading assets..." />
    @else
        <div class="as-stats">
            <div class="as-stat">
                <div class="as-stat-head"><i class="fa-solid fa-laptop"></i><span>Total</span></div>
                <div class="as-stat-value">{{ $this->summary['total'] }}</div>
            </div>
            <div class="as-stat as-stat--success">
                <div class="as-stat-head"><i class="fa-solid fa-circle-check"></i><span>On loan</span></div>
                <div class="as-stat-value">{{ $this->summary['active'] }}</div>
            </div>
            <div class="as-stat">
                <div class="as-stat-head"><i class="fa-solid fa-box-archive"></i><span>Returned</span></div>
                <div class="as-stat-value">{{ $this->summary['returned'] }}</div>
            </div>
            <div class="as-stat as-stat--warning">
                <div class="as-stat-head"><i class="fa-solid fa-triangle-exclamation"></i><span>Overdue</span></div>
                <div class="as-stat-value">{{ $this->summary['overdue'] }}</div>
            </div>
        </div>

        <nav class="nks-tabs">
            <button type="button" wire:click="selectTab('current')"
                    class="nks-tab {{ $tab === 'current' ? 'is-active' : '' }}">
                Currently Assigned
            </button>
            <button type="button" wire:click="selectTab('history')"
                    class="nks-tab {{ $tab === 'history' ? 'is-active' : '' }}">
                History
            </button>
        </nav>

        @if (count($this->filteredAssets) === 0)
            <div class="panel">
                <div class="nks-card-empty">
                    <i class="fa-solid fa-laptop-slash"></i>
                    <p>
                        @if ($tab === 'current')
                            No assets assigned to you right now.
                        @else
                            No assets in your history yet.
                        @endif
                    </p>
                    @if ($tab === 'current')
                        <button type="button" wire:click="openRepair" class="nks-btn nks-btn--outline nks-btn--sm">
                            <i class="fa-solid fa-screwdriver-wrench"></i>
                            Report an issue
                        </button>
                    @endif
                </div>
            </div>
        @else
            <div class="as-grid">
                @foreach ($this->filteredAssets as $asset)
                    @php
                        $status = $asset['status'] ?? 'loaned';
                        $daysLeft = $this->daysLeft($asset);
                        $overdue = ($asset['days_overdue'] ?? 0) > 0;
                        $tone = match ($status) {
                            'loaned' => $overdue ? 'danger' : 'success',
                            'maintenance', 'repair_requested' => 'warning',
                            'available' => 'muted',
                            'lost', 'damaged' => 'danger',
                            default => 'muted',
                        };
                    @endphp
                    <article class="as-card as-card--{{ $tone }}">
                        <header class="as-card-head">
                            <div class="as-card-icon">
                                <i class="{{ self::iconFor($asset) }}" aria-hidden="true"></i>
                            </div>
                            <div class="as-card-title">
                                <h3>{{ $asset['model'] ?? 'Asset' }}</h3>
                                <span>{{ $asset['manufacturer'] ?? '' }}</span>
                            </div>
                            <x-nks.badge :tone="$tone">
                                {{ ucfirst(str_replace('_', ' ', $status)) }}
                            </x-nks.badge>
                        </header>

                        <dl class="as-card-meta">
                            <div>
                                <dt>Asset tag</dt>
                                <dd>{{ $asset['asset_tag'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Serial</dt>
                                <dd>{{ $asset['serial_number'] ?? '—' }}</dd>
                            </div>
                            <div>
                                <dt>Condition</dt>
                                <dd>{{ ucfirst(str_replace('_', ' ', $asset['condition'] ?? '—')) }}</dd>
                            </div>
                            <div>
                                <dt>Expected return</dt>
                                <dd>
                                    @if (! empty($asset['expected_return_date']))
                                        {{ \Carbon\Carbon::parse($asset['expected_return_date'])->format('d M Y') }}
                                        @if ($daysLeft !== null)
                                            <span class="as-days {{ $overdue ? 'is-overdue' : '' }}">
                                                {{ $overdue ? abs($daysLeft) . 'd overdue' : ($daysLeft === 0 ? 'due today' : $daysLeft . 'd left') }}
                                            </span>
                                        @endif
                                    @else
                                        —
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        @if ($tab === 'current')
                            <footer class="as-card-foot">
                                <button type="button"
                                        wire:click="openRepair({{ $asset['id'] }})"
                                        class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-screwdriver-wrench"></i>
                                    Report an issue
                                </button>
                            </footer>
                        @endif
                    </article>
                @endforeach
            </div>
        @endif
    @endif

    @if ($showRepair)
        <div class="nks-modal-backdrop" wire:click.self="closeRepair" @keydown.escape.window="$wire.closeRepair()">
            <div class="nks-modal nks-modal--md" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Report an issue</h3>
                    <button type="button" wire:click="closeRepair" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Asset</label>
                        <select wire:model="repairAssetId" class="nks-select">
                            @foreach ($assets as $asset)
                                <option value="{{ $asset['id'] }}">
                                    {{ $asset['model'] ?? 'Asset' }} ({{ $asset['asset_tag'] ?? '' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Describe the issue</label>
                        <textarea wire:model.live="repairIssue" class="nks-textarea"
                                  placeholder="Screen flickers when docked, keyboard misses keys..."></textarea>
                        <div class="as-ai-row">
                            <span class="nks-help">Not sure how to explain it? Let the AI polish your wording.</span>
                            <button type="button" wire:click="describeWithAi"
                                    wire:loading.attr="disabled" wire:target="describeWithAi"
                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                <i class="fa-solid fa-wand-magic-sparkles" wire:loading.remove wire:target="describeWithAi"></i>
                                <span class="nks-btn-spinner" wire:loading wire:target="describeWithAi"></span>
                                Help me describe
                            </button>
                        </div>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Urgency</label>
                        <select wire:model="repairUrgency" class="nks-select">
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Photo (optional)</label>
                        <input type="file" wire:model="repairPhoto" accept="image/*"
                               class="nks-input" style="height: auto; padding: 8px;">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeRepair" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitRepair" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-paper-plane"></i>
                        Submit
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>