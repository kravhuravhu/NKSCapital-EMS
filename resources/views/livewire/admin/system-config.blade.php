<div class="page-system-config">
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

    <div class="sc-header-strip">
        <div class="sc-header-icon">
            <i class="fa-solid fa-sliders"></i>
        </div>
        <div class="sc-header-text">
            <h2>System Configuration</h2>
            <p>Platform-wide settings used across leave, timesheets, payroll, and security</p>
        </div>
        @if ($this->dirtyCount > 0)
            <div class="sc-header-actions">
                <button type="button" wire:click="cancel" class="nks-btn nks-btn--outline nks-btn--sm">
                    <i class="fa-solid fa-rotate-left"></i>
                    Cancel
                </button>
                <button type="button" wire:click="openSave" class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-floppy-disk"></i>
                    Save Changes ({{ $this->dirtyCount }})
                </button>
            </div>
        @endif
    </div>

    @if ($loading)
        <x-nks.spinner block label="Loading configuration…" />
    @else
        @foreach ($this->groupedConfigs as $category => $items)
            <div class="panel sc-panel">
                <div class="sc-panel-head">
                    <div class="sc-panel-icon">
                        <i class="{{ \App\Livewire\Admin\SystemConfig::categoryIcon($category) }}"></i>
                    </div>
                    <h2>{{ \App\Livewire\Admin\SystemConfig::categoryLabel($category) }}</h2>
                    @if (collect($items)->contains(fn ($i) => $i['is_sensitive']))
                        <span class="sc-panel-sensitive">
                            <i class="fa-solid fa-lock"></i>
                            Some settings require your password to change
                        </span>
                    @endif
                </div>

                <div class="sc-fields">
                    @foreach ($items as $cfg)
                        @php
                            $key = $cfg['config_key'];
                            $type = $cfg['config_type'];
                            $isDirty = isset($edits[$key]);
                            $current = $isDirty ? $edits[$key] : $cfg['config_value'];
                            $isBool = $type === 'boolean';
                            $boolVal = (string) $current === '1';
                        @endphp

                        <div class="sc-field {{ $isDirty ? 'is-dirty' : '' }}">
                            <div class="sc-field-label">
                                <span>{{ $cfg['label'] }}</span>
                                @if ($cfg['is_sensitive'])
                                    <span class="sc-lock" title="Password required"><i class="fa-solid fa-lock"></i></span>
                                @endif
                            </div>
                            <div class="sc-field-input">
                                @if ($isBool)
                                    <button type="button"
                                            wire:click="edit('{{ $key }}', '{{ $boolVal ? '0' : '1' }}')"
                                            class="nks-toggle {{ $boolVal ? 'is-on' : '' }}"
                                            role="switch"
                                            aria-checked="{{ $boolVal ? 'true' : 'false' }}">
                                        <span class="nks-toggle-knob"></span>
                                    </button>
                                    <span class="sc-bool-label">{{ $boolVal ? 'Enabled' : 'Disabled' }}</span>
                                @elseif ($type === 'integer')
                                    <input type="number"
                                           step="1"
                                           value="{{ $current }}"
                                           wire:change="edit('{{ $key }}', $event.target.value)"
                                           class="nks-input sc-input">
                                @elseif ($type === 'decimal')
                                    <input type="number"
                                           step="0.01"
                                           value="{{ $current }}"
                                           wire:change="edit('{{ $key }}', $event.target.value)"
                                           class="nks-input sc-input">
                                @else
                                    <input type="text"
                                           value="{{ $current }}"
                                           wire:change="edit('{{ $key }}', $event.target.value)"
                                           class="nks-input sc-input">
                                @endif
                            </div>
                            <div class="sc-field-meta">
                                @if (!empty($cfg['description']))
                                    <span class="sc-field-desc">{{ $cfg['description'] }}</span>
                                @endif
                                @if ($isDirty)
                                    <span class="sc-field-dirty">
                                        <i class="fa-solid fa-pen"></i>
                                        Changed — was: {{ $cfg['config_value'] ?: '—' }}
                                    </span>
                                @endif
                                @if (!empty($cfg['updated_at']))
                                    <span class="sc-field-updated">
                                        Updated {{ \Carbon\Carbon::parse($cfg['updated_at'])->diffForHumans() }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    @endif

    @if ($showSave)
        <div class="sc-modal-backdrop" wire:click.self="closeSave">
            <div class="sc-modal">
                <header class="sc-modal-head">
                    <h3>Confirm Changes</h3>
                    <button type="button" wire:click="closeSave" class="sc-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="sc-modal-body">
                    <p class="sc-text">
                        {{ $this->dirtyCount }} {{ \Illuminate\Support\Str::plural('setting', $this->dirtyCount) }} will be updated.
                    </p>

                    @if ($requiresPassword)
                        <div class="sc-warning">
                            <i class="fa-solid fa-shield-halved"></i>
                            <span>
                                Some of the settings you are changing affect payroll or security.
                                Enter your admin password to confirm.
                            </span>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Admin Password <span class="nks-required">*</span></label>
                            <input type="password" wire:model="savePassword" class="nks-input" autocomplete="current-password">
                        </div>
                    @endif
                </div>
                <footer class="sc-modal-foot">
                    <button type="button" wire:click="closeSave" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button"
                            wire:click="submitSave"
                            wire:loading.attr="disabled"
                            wire:target="submitSave"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Save Changes
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>