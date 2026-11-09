<div class="page-documents">
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
        <x-nks.spinner block label="Loading documents..." />
    @else
        <div class="dc-stats">
            <div class="dc-stat">
                <div class="dc-stat-icon"><i class="fa-solid fa-share-nodes"></i></div>
                <div>
                    <div class="dc-stat-label">Shared</div>
                    <div class="dc-stat-value">{{ $summary['shared_count'] ?? 0 }}</div>
                </div>
            </div>
            <div class="dc-stat dc-stat--success">
                <div class="dc-stat-icon"><i class="fa-solid fa-user"></i></div>
                <div>
                    <div class="dc-stat-label">My Documents</div>
                    <div class="dc-stat-value">{{ $summary['mine_count'] ?? 0 }}</div>
                </div>
            </div>
            <div class="dc-stat dc-stat--gold">
                <div class="dc-stat-icon"><i class="fa-solid fa-folder-tree"></i></div>
                <div>
                    <div class="dc-stat-label">Total</div>
                    <div class="dc-stat-value">{{ $summary['total_count'] ?? 0 }}</div>
                </div>
            </div>
        </div>

        <nav class="nks-tabs">
            <button type="button" wire:click="selectTab('shared')"
                    class="nks-tab {{ $tab === 'shared' ? 'is-active' : '' }}">
                Shared with me
            </button>
            <button type="button" wire:click="selectTab('mine')"
                    class="nks-tab {{ $tab === 'mine' ? 'is-active' : '' }}">
                My documents
            </button>
        </nav>

        <div class="panel">
            <div class="page-toolbar">
                <div>
                    <h2>{{ $tab === 'shared' ? 'Shared documents' : 'My documents' }}</h2>
                    <p>Download anything you need. Upload supporting files only when requested.</p>
                </div>
                <button type="button" wire:click="openUpload" class="nks-btn nks-btn--primary">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    Upload
                </button>
            </div>

            @if (count($this->currentList) === 0)
                <div class="nks-card-empty">
                    <i class="fa-solid fa-folder-open"></i>
                    <p>
                        @if ($tab === 'shared')
                            No shared documents have been published yet.
                        @else
                            You have no personal documents yet.
                        @endif
                    </p>
                </div>
            @else
                <div class="nks-table-wrapper">
                    <table class="nks-table">
                        <thead>
                            <tr>
                                <th>Document</th>
                                <th>Category</th>
                                <th>Last updated</th>
                                <th>Size</th>
                                <th style="width: 60px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->currentList as $doc)
                                <tr>
                                    <td>
                                        <div class="dc-name">
                                            <span class="dc-name-icon">
                                                <i class="{{ self::iconFor($doc['title'] ?? '') }}" aria-hidden="true"></i>
                                            </span>
                                            <div>
                                                <strong>{{ $doc['title'] ?? 'Untitled' }}</strong>
                                                @if (! empty($doc['description']))
                                                    <small>{{ \Illuminate\Support\Str::limit($doc['description'], 70) }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <x-nks.badge :tone="self::toneFor($doc['category'] ?? 'HR')">
                                            {{ $doc['category'] ?? 'HR' }}
                                        </x-nks.badge>
                                    </td>
                                    <td>
                                        {{ isset($doc['updated_at']) ? \Carbon\Carbon::parse($doc['updated_at'])->format('d M Y') : '—' }}
                                    </td>
                                    <td>{{ $doc['size_human'] ?? '—' }}</td>
                                    <td>
                                        <a href="{{ $doc['download_url'] ?? '#' }}" class="dc-download" title="Download">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    @endif

    @if ($showUpload)
        <div class="nks-modal-backdrop" wire:click.self="closeUpload" @keydown.escape.window="$wire.closeUpload()">
            <div class="nks-modal nks-modal--md" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Upload document</h3>
                    <button type="button" wire:click="closeUpload" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Title</label>
                        <input type="text" wire:model.live="uploadTitle" class="nks-input"
                               placeholder="Medical certificate">
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Category</label>
                        <select wire:model="uploadCategory" class="nks-select">
                            <option value="HR">HR</option>
                            <option value="Compliance">Compliance</option>
                            <option value="Policy">Policy</option>
                            <option value="Finance">Finance</option>
                            <option value="Personal">Personal</option>
                        </select>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">File</label>
                        <input type="file" wire:model="uploadFile" accept=".pdf,.jpg,.jpeg,.png"
                               class="nks-input" style="height: auto; padding: 8px;">
                        @if ($uploadFile)
                            <span class="nks-help">{{ $uploadFile->getClientOriginalName() }}</span>
                        @endif
                    </div>

                    @if ($aiSummary)
                        <div class="dc-ai-summary">
                            <div class="dc-ai-summary-head">
                                <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                                <span>AI summary (will be saved as description)</span>
                            </div>
                            <p>{{ $aiSummary }}</p>
                        </div>
                    @endif

                    <div class="dc-ai-row">
                        <span class="nks-help">Not sure what to describe? Let AI read the file and summarize it.</span>
                        <button type="button" wire:click="summarizeDraft"
                                wire:loading.attr="disabled" wire:target="summarizeDraft"
                                class="nks-btn nks-btn--outline nks-btn--sm">
                            <i class="fa-solid fa-wand-magic-sparkles" wire:loading.remove wire:target="summarizeDraft"></i>
                            <span class="nks-btn-spinner" wire:loading wire:target="summarizeDraft"></span>
                            Summarize
                        </button>
                    </div>

                    <label class="nks-checkbox">
                        <input type="checkbox" wire:model="uploadShared">
                        <span>Share with the whole company</span>
                    </label>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeUpload" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitUpload" class="nks-btn nks-btn--primary"
                            wire:loading.attr="disabled" wire:target="submitUpload">
                        <i class="fa-solid fa-cloud-arrow-up" wire:loading.remove wire:target="submitUpload"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="submitUpload"></span>
                        Upload
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>