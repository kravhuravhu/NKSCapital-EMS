<div class="page-client-templates">
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

    <div class="panel ct-panel">
        <div class="ct-header">
            <i class="fa-solid fa-file-lines ct-header-icon"></i>
            <div>
                <h2>Client Templates</h2>
                <p>Client-branded PS timesheet templates used when generating monthly submissions</p>
            </div>
            <button type="button" wire:click="openUpload" class="nks-btn nks-btn--primary ct-create-btn">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                Upload Template
            </button>
        </div>

        <div class="ct-filters">
            <div class="nks-field ct-filter-search">
                <label class="nks-label">Search</label>
                <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                       class="nks-input" placeholder="Template name or client">
            </div>
            <div class="nks-field">
                <label class="nks-label">Status</label>
                <select wire:model="activeFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="ct-filter-actions">
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

        @if ($loading)
            <x-nks.spinner block label="Loading templates…" />
        @elseif (count($templates) === 0)
            <div class="ct-empty">
                <i class="fa-solid fa-file-lines"></i>
                <strong>No templates yet</strong>
                <span>Upload your first client-branded timesheet template to get started.</span>
            </div>
        @else
            <div class="ct-table-wrapper">
                <table class="ct-table">
                    <thead>
                        <tr>
                            <th>Template</th>
                            <th>Client</th>
                            <th>File</th>
                            <th>Updated By</th>
                            <th>Updated</th>
                            <th>Status</th>
                            <th style="width: 160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($templates as $tpl)
                            <tr wire:key="ct-{{ $tpl['id'] }}">
                                <td>
                                    <div class="ct-name">{{ $tpl['template_name'] ?? '—' }}</div>
                                    @if (!empty($tpl['file_hash']))
                                        <div class="ct-hash">sha256: {{ substr($tpl['file_hash'], 0, 12) }}…</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $tpl['client']['company_name'] ?? '—' }}
                                </td>
                                <td>
                                    @if (!empty($tpl['file_path']))
                                        <span class="ct-file">
                                            <i class="fa-solid fa-file-pdf"></i>
                                            PDF
                                        </span>
                                    @else
                                        <span class="ct-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!empty($tpl['updated_by']))
                                        {{ $tpl['updated_by']['first_name'] ?? '' }}
                                        {{ $tpl['updated_by']['last_name'] ?? '' }}
                                    @else
                                        <span class="ct-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    {{ !empty($tpl['updated_at']) ? \Carbon\Carbon::parse($tpl['updated_at'])->format('d M Y') : '—' }}
                                </td>
                                <td>
                                    <x-nks.badge :tone="!empty($tpl['is_active']) ? 'success' : 'muted'">
                                        {{ !empty($tpl['is_active']) ? 'Active' : 'Inactive' }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    <div class="ct-row-actions">
                                        <button type="button" wire:click="openView({{ $tpl['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i>
                                            View
                                        </button>
                                        <button type="button" wire:click="openUpdate({{ $tpl['id'] }})"
                                                class="nks-btn nks-btn--primary nks-btn--sm">
                                            <i class="fa-solid fa-pen"></i>
                                            Update
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (($pagination['last_page'] ?? 1) > 1)
                <div class="ct-pagination">
                    <button type="button" wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] <= 1) disabled @endif>
                        <i class="fa-solid fa-chevron-left"></i>
                        Previous
                    </button>
                    <span class="ct-page-info">
                        Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                        ({{ $pagination['total'] }} total)
                    </span>
                    <button type="button" wire:click="gotoPage({{ min($pagination['last_page'], $pagination['current_page'] + 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] >= $pagination['last_page']) disabled @endif>
                        Next
                        <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            @endif
        @endif
    </div>

    @if ($showUpload)
        <div class="ct-modal-backdrop" wire:click.self="closeUpload">
            <div class="ct-modal">
                <header class="ct-modal-head">
                    <h3>Upload Template</h3>
                    <button type="button" wire:click="closeUpload" class="ct-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ct-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Client <span class="nks-required">*</span></label>
                        <select wire:model="uploadForm.client_id" class="nks-select">
                            <option value="">Select a client</option>
                            @foreach ($clients as $c)
                                <option value="{{ $c['id'] }}">{{ $c['company_name'] }}</option>
                            @endforeach
                        </select>
                        <span class="nks-help">Only clients without an active template will benefit from this upload — existing ones will be deactivated.</span>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Template Name <span class="nks-required">*</span></label>
                        <input type="text" wire:model="uploadForm.template_name" class="nks-input"
                               placeholder="e.g. Standard Monthly Timesheet">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">File <span class="nks-required">*</span></label>
                        <input type="file" wire:model="uploadFile"
                               accept=".pdf,.doc,.docx"
                               class="nks-input" style="height: auto; padding: 8px;">
                        <span class="nks-help">PDF, DOC, or DOCX — up to 10MB</span>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Template Content (optional)</label>
                        <textarea wire:model="uploadForm.template_content" class="nks-textarea"
                                  placeholder="Any additional template notes or headers."></textarea>
                    </div>
                </div>
                <footer class="ct-modal-foot">
                    <button type="button" wire:click="closeUpload" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitUpload" wire:loading.attr="disabled" wire:target="submitUpload"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-cloud-arrow-up"></i>
                        Upload
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showUpdate && $updateId)
        <div class="ct-modal-backdrop" wire:click.self="closeUpdate">
            <div class="ct-modal">
                <header class="ct-modal-head">
                    <h3>Update Template</h3>
                    <button type="button" wire:click="closeUpdate" class="ct-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ct-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Template Name</label>
                        <input type="text" wire:model="updateForm.template_name" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Replace File (optional)</label>
                        <input type="file" wire:model="updateFile"
                               accept=".pdf,.doc,.docx"
                               class="nks-input" style="height: auto; padding: 8px;">
                        <span class="nks-help">Leave blank to keep the existing file.</span>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Template Content (optional)</label>
                        <textarea wire:model="updateForm.template_content" class="nks-textarea"></textarea>
                    </div>
                </div>
                <footer class="ct-modal-foot">
                    <button type="button" wire:click="closeUpdate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitUpdate" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Save Changes
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showView && $viewTemplate)
        <div class="ct-modal-backdrop" wire:click.self="closeView">
            <div class="ct-modal ct-modal--lg">
                <header class="ct-modal-head">
                    <div>
                        <h3>{{ $viewTemplate['template_name'] ?? 'Template' }}</h3>
                        <span class="ct-modal-sub">{{ $viewTemplate['client']['company_name'] ?? '—' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="ct-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ct-modal-body">
                    <div class="ct-detail-grid">
                        <div>
                            <span class="ct-detail-label">Status</span>
                            <span class="ct-detail-value">
                                <x-nks.badge :tone="!empty($viewTemplate['is_active']) ? 'success' : 'muted'">
                                    {{ !empty($viewTemplate['is_active']) ? 'Active' : 'Inactive' }}
                                </x-nks.badge>
                            </span>
                        </div>
                        <div>
                            <span class="ct-detail-label">Client</span>
                            <span class="ct-detail-value">{{ $viewTemplate['client']['company_name'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ct-detail-label">Created</span>
                            <span class="ct-detail-value">
                                {{ !empty($viewTemplate['created_at']) ? \Carbon\Carbon::parse($viewTemplate['created_at'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="ct-detail-label">Last Updated</span>
                            <span class="ct-detail-value">
                                {{ !empty($viewTemplate['updated_at']) ? \Carbon\Carbon::parse($viewTemplate['updated_at'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                    </div>

                    @if (!empty($viewTemplate['file_hash']))
                        <div class="ct-detail-section">
                            <span class="ct-detail-label">File Hash</span>
                            <p class="ct-detail-mono">{{ $viewTemplate['file_hash'] }}</p>
                        </div>
                    @endif

                    @if (!empty($viewTemplate['template_content']))
                        <div class="ct-detail-section">
                            <span class="ct-detail-label">Template Content</span>
                            <p class="ct-detail-text">{{ $viewTemplate['template_content'] }}</p>
                        </div>
                    @endif

                    <div class="ct-detail-section">
                        <span class="ct-detail-label">File</span>
                        <div class="ct-file-card">
                            <div class="ct-file-icon">
                                <i class="fa-solid fa-file-pdf"></i>
                            </div>
                            <div class="ct-file-body">
                                <div class="ct-file-name">{{ $viewTemplate['template_name'] ?? 'template' }}.pdf</div>
                                <div class="ct-file-hint">The PDF is stored on the server. It is used when generating PS timesheets for this client.</div>
                            </div>
                        </div>
                    </div>
                </div>
                <footer class="ct-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    <button type="button" wire:click="openUpdate({{ $viewTemplate['id'] }})" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-pen"></i>
                        Update Template
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>