<div class="page-documents">

    @if ($error)
        <div class="dc-flash dc-flash--error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    @if ($flash)
        <div class="dc-flash dc-flash--success">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ $flash }}</span>
        </div>
    @endif

    <div class="dc-layout">

        {{-- LEFT --}}
        <div class="dc-left">

            {{-- section heading --}}
            <header class="dc-heading">
                <div class="dc-heading-icon">
                    <i class="fa-solid fa-folder-open"></i>
                </div>
                <div>
                    <h2>My Documents</h2>
                    <p>View and download your company and personal documents</p>
                </div>
            </header>

            {{-- tabs --}}
            <nav class="nks-tabs dc-tabs" aria-label="Document views">
                <button type="button"
                        wire:click="selectTab('shared')"
                        class="nks-tab {{ $tab === 'shared' ? 'is-active' : '' }}">
                    Shared Documents
                </button>
                <button type="button"
                        wire:click="selectTab('mine')"
                        class="nks-tab {{ $tab === 'mine' ? 'is-active' : '' }}">
                    My Documents
                </button>
            </nav>

            {{-- table --}}
            <div class="dc-table-wrapper">
                <table class="dc-table">
                    <thead>
                        <tr>
                            <th>Document Name</th>
                            <th>Category</th>
                            <th>Last Updated</th>
                            <th>Size</th>
                            <th style="width: 60px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->currentList as $doc)
                            <tr>
                                <td>
                                    <div class="dc-name">
                                        <span class="dc-name-icon">
                                            <i class="{{ \App\Livewire\Employee\Documents::iconFor($doc['title'] ?? '') }}"></i>
                                        </span>
                                        <span>{{ $doc['title'] ?? 'Untitled' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Employee\Documents::toneFor($doc['category'] ?? 'HR')">
                                        {{ $doc['category'] ?? 'HR' }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    {{ isset($doc['updated_at']) ? \Carbon\Carbon::parse($doc['updated_at'])->format('d M Y') : '—' }}
                                </td>
                                <td>{{ $doc['size_human'] ?? '—' }}</td>
                                <td>
                                    <a href="{{ $doc['download_url'] ?? '#' }}"
                                       class="dc-download"
                                       title="Download document">
                                        <i class="fa-solid fa-download"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="dc-empty">
                                    <i class="fa-solid fa-folder-open"></i>
                                    <strong>No documents</strong>
                                    <span>
                                        @if ($tab === 'mine')
                                            You have no personal documents yet. Contact HR if this is unexpected.
                                        @else
                                            No shared documents have been published yet.
                                        @endif
                                    </span>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- upload strip --}}
            <button type="button"
                    wire:click="openUpload"
                    class="dc-upload-strip">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                <div class="dc-upload-text">
                    <strong>Upload Document</strong>
                    <span>Upload supporting documents (PDF, JPG, PNG, up to 10MB), only if requested by management.</span>
                </div>
            </button>
        </div>

        {{-- RIGHT --}}
        <div class="dc-right">

            {{-- summary --}}
            <div class="panel dc-summary">
                <h2>Document Summary</h2>

                <div class="dc-summary-row">
                    <div class="dc-summary-icon dc-summary-icon--blue">
                        <i class="fa-solid fa-share-nodes"></i>
                    </div>
                    <span class="dc-summary-label">Shared Documents</span>
                    <strong>{{ $summary['shared_count'] ?? 0 }}</strong>
                </div>

                <div class="dc-summary-row">
                    <div class="dc-summary-icon dc-summary-icon--green">
                        <i class="fa-solid fa-user"></i>
                    </div>
                    <span class="dc-summary-label">My Documents</span>
                    <strong>{{ $summary['mine_count'] ?? 0 }}</strong>
                </div>

                <div class="dc-summary-row">
                    <div class="dc-summary-icon dc-summary-icon--purple">
                        <i class="fa-solid fa-folder-tree"></i>
                    </div>
                    <span class="dc-summary-label">Total Documents</span>
                    <strong>{{ $summary['total_count'] ?? 0 }}</strong>
                </div>
            </div>

            {{-- help --}}
            <div class="panel dc-help">
                <h2>Need Help?</h2>
                <div class="dc-help-body">
                    <i class="fa-solid fa-headset"></i>
                    <div>
                        <p>Can't find the document you need? Contact HR and we will assist you.</p>
                        <button type="button" class="nks-btn nks-btn--outline nks-btn--sm">
                            <i class="fa-solid fa-envelope"></i>
                            Contact HR
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- upload modal --}}
    @if ($showUpload)
        <div class="dc-modal-backdrop" wire:click.self="closeUpload">
            <div class="dc-modal">
                <header class="dc-modal-head">
                    <h3>Upload Document</h3>
                    <button type="button" wire:click="closeUpload" class="dc-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>

                <div class="dc-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">
                            Document Title <span class="nks-required">*</span>
                        </label>
                        <input type="text"
                               wire:model="uploadTitle"
                               class="nks-input"
                               placeholder="e.g. Medical Certificate">
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            Category <span class="nks-required">*</span>
                        </label>
                        <select wire:model="uploadCategory" class="nks-select">
                            <option value="HR">HR</option>
                            <option value="Compliance">Compliance</option>
                            <option value="Policy">Policy</option>
                            <option value="Finance">Finance</option>
                            <option value="Personal">Personal</option>
                        </select>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">
                            File <span class="nks-required">*</span>
                        </label>
                        <input type="file"
                               wire:model="uploadFile"
                               accept=".pdf,.jpg,.jpeg,.png"
                               class="nks-input"
                               style="height: auto; padding: 8px;">
                        @if ($uploadFile)
                            <span class="nks-help">{{ $uploadFile->getClientOriginalName() }}</span>
                        @endif
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Visibility</label>
                        <label class="dc-modal-checkbox">
                            <input type="checkbox" wire:model="uploadShared">
                            <span>Share with the whole company</span>
                        </label>
                    </div>
                </div>

                <footer class="dc-modal-foot">
                    <button type="button" wire:click="closeUpload" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitUpload" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        Upload
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>