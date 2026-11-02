<div class="page-backup-restore">
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

    @if (!empty($health['checks']))
        <div class="br-health">
            <div class="br-health-head">
                <i class="fa-solid fa-heart-pulse"></i>
                <div>
                    <h2>System Readiness</h2>
                    <p>
                        Status:
                        <x-nks.badge :tone="\App\Livewire\Admin\BackupRestore::healthTone($health['status'] ?? 'unknown')">
                            {{ ucfirst($health['status'] ?? 'unknown') }}
                        </x-nks.badge>
                    </p>
                </div>
            </div>
            <div class="br-health-grid">
                @foreach ($health['checks'] as $key => $check)
                    <div class="br-health-item">
                        <div class="br-health-item-head">
                            <span class="br-health-label">{{ ucfirst($key) }}</span>
                            <x-nks.badge :tone="\App\Livewire\Admin\BackupRestore::healthTone($check['status'] ?? 'unknown')">
                                {{ ucfirst($check['status'] ?? '—') }}
                            </x-nks.badge>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="panel br-panel">
        <div class="br-header">
            <i class="fa-solid fa-database br-header-icon"></i>
            <div>
                <h2>Backups</h2>
                <p>Create, download, and manage full database backups</p>
            </div>
            <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary br-create-btn">
                <i class="fa-solid fa-download"></i>
                Create Backup
            </button>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading backups…" />
        @elseif (count($backups) === 0)
            <div class="br-empty">
                <i class="fa-solid fa-database"></i>
                <strong>No backups yet</strong>
                <span>Click Create Backup to make your first snapshot.</span>
            </div>
        @else
            <div class="br-table-wrapper">
                <table class="br-table">
                    <thead>
                        <tr>
                            <th>Filename</th>
                            <th>Format</th>
                            <th>Size</th>
                            <th>Created</th>
                            <th style="width: 240px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($backups as $b)
                            <tr wire:key="bk-{{ $b['filename'] }}">
                                <td>
                                    <div class="br-filename">
                                        {{ $b['filename'] }}
                                        @if (!empty($b['is_pre_restore']))
                                            <x-nks.badge tone="gold">Pre-restore</x-nks.badge>
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Admin\BackupRestore::formatTone($b['format'])">
                                        {{ strtoupper($b['format']) }}
                                    </x-nks.badge>
                                </td>
                                <td>{{ $b['size_human'] }}</td>
                                <td>
                                    {{ !empty($b['created_at']) ? \Carbon\Carbon::parse($b['created_at'])->format('d M Y H:i') : '—' }}
                                </td>
                                <td>
                                    <div class="br-actions">
                                        <a href="{{ url('/api/v1/admin/backup/download/' . $b['filename']) }}"
                                           download
                                           class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-download"></i>
                                            Download
                                        </a>

                                        @if (!empty($b['restorable']))
                                            @if ($isSuperAdmin)
                                                <button type="button"
                                                        wire:click="openRestore('{{ $b['filename'] }}')"
                                                        class="nks-btn nks-btn--danger nks-btn--sm">
                                                    <i class="fa-solid fa-rotate-left"></i>
                                                    Restore
                                                </button>
                                            @else
                                                <button type="button"
                                                        class="nks-btn nks-btn--outline nks-btn--sm"
                                                        disabled
                                                        title="Only Super Admins can restore backups">
                                                    <i class="fa-solid fa-lock"></i>
                                                    Restore
                                                </button>
                                            @endif
                                        @else
                                            <button type="button"
                                                    class="nks-btn nks-btn--outline nks-btn--sm"
                                                    disabled
                                                    title="This format cannot be restored from the portal">
                                                <i class="fa-solid fa-ban"></i>
                                                Restore
                                            </button>
                                        @endif

                                        <button type="button"
                                                wire:click="openDelete('{{ $b['filename'] }}')"
                                                class="br-icon-btn br-icon-btn--danger"
                                                title="Delete">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="br-warning">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div>
                    <strong>Restore is destructive.</strong>
                    Restoring overwrites the live database. A <code>pre-restore-*</code> safety backup is created automatically,
                    but please verify before proceeding. Restore requires Super Admin access, a valid password, and typing the
                    exact filename.
                </div>
            </div>
        @endif
    </div>

    {{-- create --}}
    @if ($showCreate)
        <div class="br-modal-backdrop" wire:click.self="closeCreate">
            <div class="br-modal">
                <header class="br-modal-head">
                    <h3>Create Backup</h3>
                    <button type="button" wire:click="closeCreate" class="br-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="br-modal-body">
                    <p class="br-text">
                        Create a full snapshot of the database now?
                        @if (DB::getDriverName() === 'mysql')
                            The server will use <code>mysqldump</code> when available. Otherwise a PHP fallback dump is created
                            (viewable but not restorable from this portal).
                        @elseif (DB::getDriverName() === 'sqlite')
                            SQLite deployments use a direct file copy — fast and fully restorable.
                        @else
                            A best-effort dump will be generated for driver: <code>{{ DB::getDriverName() }}</code>.
                        @endif
                    </p>
                </div>
                <footer class="br-modal-foot">
                    <button type="button" wire:click="closeCreate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitCreate"
                            wire:loading.attr="disabled" wire:target="submitCreate"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-download"></i> Create Backup
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- delete --}}
    @if ($showDelete)
        <div class="br-modal-backdrop" wire:click.self="closeDelete">
            <div class="br-modal">
                <header class="br-modal-head">
                    <h3>Delete Backup</h3>
                    <button type="button" wire:click="closeDelete" class="br-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="br-modal-body">
                    <p class="br-text">
                        Delete <strong>{{ $deleteFilename }}</strong>?
                        This removes the file from disk permanently.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Admin Password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="deletePassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="br-modal-foot">
                    <button type="button" wire:click="closeDelete" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitDelete" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-trash-can"></i> Delete Backup
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- restore --}}
    @if ($showRestore)
        <div class="br-modal-backdrop" wire:click.self="closeRestore">
            <div class="br-modal br-modal--lg">
                <header class="br-modal-head">
                    <h3 style="color: #8A2A2A;">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Restore Database
                    </h3>
                    <button type="button" wire:click="closeRestore" class="br-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="br-modal-body">
                    <div class="br-danger-banner">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        <div>
                            <strong style="display: block; margin-bottom: 4px;">This will overwrite the live database.</strong>
                            All data created after this backup will be lost. A pre-restore safety backup will be created automatically.
                            This cannot be undone from the portal if the safety backup is also lost.
                        </div>
                    </div>

                    <div class="br-restore-summary">
                        <span class="br-detail-label">Restoring From</span>
                        <code class="br-restore-filename">{{ $restoreFilename }}</code>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Admin Password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="restorePassword" class="nks-input" autocomplete="current-password">
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Type the filename exactly to confirm <span class="nks-required">*</span>
                        </label>
                        <input type="text" wire:model="restoreConfirm" class="nks-input"
                               placeholder="{{ $restoreFilename }}"
                               autocomplete="off" spellcheck="false">
                        <span class="nks-help">Case-sensitive. Must match the filename shown above character by character.</span>
                    </div>
                </div>
                <footer class="br-modal-foot">
                    <button type="button" wire:click="closeRestore" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitRestore"
                            wire:loading.attr="disabled" wire:target="submitRestore"
                            class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-rotate-left"></i> Restore Now
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>