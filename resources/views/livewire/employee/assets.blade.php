<div class="page-assets">

    @if ($error)
        <div class="ast-flash ast-flash--error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    @if ($flash)
        <div class="ast-flash ast-flash--success">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ $flash }}</span>
        </div>
    @endif

    <div class="ast-layout">

        {{-- LEFT --}}
        <div class="ast-left">

            {{-- tabs --}}
            <nav class="nks-tabs ast-tabs" aria-label="Assets views">
                <button type="button"
                        wire:click="selectTab('current')"
                        class="nks-tab {{ $tab === 'current' ? 'is-active' : '' }}">
                    My Assets
                </button>
                <button type="button"
                        wire:click="selectTab('history')"
                        class="nks-tab {{ $tab === 'history' ? 'is-active' : '' }}">
                    Assets History
                </button>
            </nav>

            {{-- section heading --}}
            <header class="ast-heading">
                <div>
                    <h2>{{ $tab === 'current' ? 'Currently Assigned Assets' : 'Previously Assigned Assets' }}</h2>
                    <p>{{ $tab === 'current' ? 'These assets are assigned to you' : 'Assets returned to the company' }}</p>
                </div>

                @if ($tab === 'current' && count($assets) > 0)
                    <button type="button"
                            wire:click="openRepair()"
                            class="nks-btn nks-btn--outline">
                        <i class="fa-solid fa-screwdriver-wrench"></i>
                        Request Repair / Report Issue
                    </button>
                @endif
            </header>

            {{-- details panel --}}
            @if ($details)
                <div class="ast-details">
                    <div class="ast-details-head">
                        <strong>{{ $details['model'] ?? 'Asset' }}</strong>
                        <button type="button" wire:click="closeDetails" class="ast-details-close">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="ast-details-grid">
                        <div>
                            <span class="ast-details-label">Asset Tag</span>
                            <span class="ast-details-value">{{ $details['asset_tag'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ast-details-label">Serial Number</span>
                            <span class="ast-details-value">{{ $details['serial_number'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ast-details-label">Condition</span>
                            <span class="ast-details-value">{{ ucfirst($details['condition'] ?? '—') }}</span>
                        </div>
                        <div>
                            <span class="ast-details-label">Expected Return</span>
                            <span class="ast-details-value">
                                {{ $details['expected_return_date'] ? \Carbon\Carbon::parse($details['expected_return_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                    </div>
                </div>
            @endif

            {{-- assets list --}}
            <div class="ast-list">
                @forelse ($this->filteredAssets as $index => $asset)
                    <article class="ast-card">
                        <div class="ast-card-icon">
                            <i class="{{ \App\Livewire\Employee\Assets::iconFor($asset) }}"></i>
                        </div>

                        <div class="ast-card-body">
                            <header class="ast-card-head">
                                <div>
                                    <h3>{{ $asset['model'] ?? 'Unnamed Asset' }}</h3>
                                    <p>{{ $asset['manufacturer'] ?? 'Asset' }}</p>
                                </div>

                                <button type="button"
                                        wire:click="viewDetails({{ $index }})"
                                        class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-circle-info"></i>
                                    View Details
                                </button>
                            </header>

                            <dl class="ast-card-meta">
                                <div>
                                    <dt>Serial Number</dt>
                                    <dd>{{ $asset['serial_number'] ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt>Asset Tag</dt>
                                    <dd>{{ $asset['asset_tag'] ?? '—' }}</dd>
                                </div>
                                <div>
                                    <dt>Condition</dt>
                                    <dd>{{ ucfirst($asset['condition'] ?? '—') }}</dd>
                                </div>
                                <div>
                                    <dt>Status</dt>
                                    <dd>
                                        @php
                                            $status = $asset['status'] ?? 'loaned';
                                            $tone   = match ($status) {
                                                'loaned'             => 'success',
                                                'available'          => 'gold',
                                                'maintenance',
                                                'repair_requested'   => 'gold',
                                                'lost', 'damaged'    => 'red',
                                                default              => 'muted',
                                            };
                                        @endphp
                                        <x-nks.badge :tone="$tone">
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </x-nks.badge>
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </article>
                @empty
                    <div class="ast-empty">
                        <i class="fa-solid fa-laptop-slash"></i>
                        <h3>No assets {{ $tab === 'current' ? 'assigned' : 'in history' }}</h3>
                        <p>
                            @if ($tab === 'current')
                                You don't have any assets assigned right now. Contact IT if this is unexpected.
                            @else
                                Historical asset custody records are managed by HR. Contact HR for your full asset history.
                            @endif
                        </p>
                    </div>
                @endforelse
            </div>

            {{-- responsible use notice --}}
            <div class="ast-responsible">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Responsible Use:</strong>
                    These assets are company property. Please ensure proper care and return them when requested.
                </div>
            </div>
        </div>

        {{-- RIGHT --}}
        <div class="ast-right">

            {{-- summary --}}
            <div class="panel ast-summary">
                <h2>Assets Summary</h2>

                <div class="ast-summary-total">
                    <div>
                        <div class="ast-summary-count">{{ $this->summary['total'] }}</div>
                        <div class="ast-summary-label">Total Assets</div>
                    </div>
                    <i class="fa-solid fa-laptop"></i>
                </div>

                <ul class="ast-status-list">
                    <li>
                        <span class="ast-status-count">{{ $this->summary['active'] }}</span>
                        <span class="ast-status-dot is-active"></span>
                        Active
                    </li>
                    <li>
                        <span class="ast-status-count">{{ $this->summary['returned'] }}</span>
                        <span class="ast-status-dot is-returned"></span>
                        Returned
                    </li>
                    <li>
                        <span class="ast-status-count">{{ $this->summary['maintenance'] }}</span>
                        <span class="ast-status-dot is-maintenance"></span>
                        Under Maintenance
                    </li>
                    <li>
                        <span class="ast-status-count">{{ $this->summary['overdue'] }}</span>
                        <span class="ast-status-dot is-overdue"></span>
                        Overdue
                    </li>
                </ul>
            </div>

            {{-- help --}}
            <div class="panel ast-help">
                <h2>Need Help?</h2>
                <div class="ast-help-body">
                    <i class="fa-solid fa-headset"></i>
                    <div>
                        <p>Report an issue with your asset or request assistance from IT.</p>
                        <button type="button"
                                wire:click="openRepair()"
                                class="nks-btn nks-btn--outline nks-btn--sm">
                            <i class="fa-solid fa-screwdriver-wrench"></i>
                            Request Support
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- repair modal --}}
    @if ($showRepair)
        <div class="ast-modal-backdrop" wire:click.self="closeRepair">
            <div class="ast-modal">
                <header class="ast-modal-head">
                    <h3>Request Repair / Report Issue</h3>
                    <button type="button" wire:click="closeRepair" class="ast-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>

                <div class="ast-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">
                            Asset <span class="nks-required">*</span>
                        </label>
                        <select wire:model="repairAssetId" class="nks-select">
                            @foreach ($assets as $asset)
                                <option value="{{ $asset['id'] }}">
                                    {{ $asset['model'] ?? 'Asset' }} — {{ $asset['asset_tag'] ?? '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Describe the issue <span class="nks-required">*</span>
                        </label>
                        <textarea wire:model="repairIssue"
                                  class="nks-textarea"
                                  placeholder="Screen cracked, keyboard not responding, etc."></textarea>
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
                        <input type="file"
                               wire:model="repairPhoto"
                               accept="image/*"
                               class="nks-input"
                               style="height: auto; padding: 8px;">
                    </div>
                </div>

                <footer class="ast-modal-foot">
                    <button type="button" wire:click="closeRepair" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitRepair" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-paper-plane"></i>
                        Submit Repair Request
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>