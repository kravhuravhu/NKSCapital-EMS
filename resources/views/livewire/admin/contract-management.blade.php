<div class="page-admin-contracts">
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
        <x-nks.spinner block label="Loading contracts..." />
    @else
        <div class="ct-stats">
            <div class="ct-stat">
                <div class="ct-stat-icon"><i class="fa-solid fa-file-contract" aria-hidden="true"></i></div>
                <div>
                    <div class="ct-stat-label">Total</div>
                    <div class="ct-stat-value">{{ $stats['total'] }}</div>
                </div>
            </div>
            <div class="ct-stat ct-stat--success">
                <div class="ct-stat-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                <div>
                    <div class="ct-stat-label">Active</div>
                    <div class="ct-stat-value">{{ $stats['active'] }}</div>
                </div>
            </div>
            <div class="ct-stat ct-stat--warning">
                <div class="ct-stat-icon"><i class="fa-solid fa-clock" aria-hidden="true"></i></div>
                <div>
                    <div class="ct-stat-label">Expiring soon</div>
                    <div class="ct-stat-value">{{ $stats['expiring'] }}</div>
                </div>
            </div>
            <div class="ct-stat ct-stat--gold">
                <div class="ct-stat-icon"><i class="fa-solid fa-pen-nib" aria-hidden="true"></i></div>
                <div>
                    <div class="ct-stat-label">Pending signature</div>
                    <div class="ct-stat-value">{{ $stats['pending_signature'] }}</div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="page-toolbar">
                <div>
                    <h2>Contracts</h2>
                    <p>Upload, renew, terminate, and monitor employment contracts</p>
                </div>
                <button type="button" wire:click="openUpload" class="nks-btn nks-btn--primary">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    Upload contract
                </button>
            </div>

            <div class="ct-filters">
                <div class="nks-field ct-filter-search">
                    <label class="nks-label">Search</label>
                    <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                           class="nks-input" placeholder="Employee, number, or position">
                </div>
                <div class="nks-field">
                    <label class="nks-label">Status</label>
                    <select wire:model="statusFilter" class="nks-select">
                        <option value="">Any</option>
                        <option value="active">Active</option>
                        <option value="draft">Draft</option>
                        <option value="pending_signature">Pending signature</option>
                        <option value="expired">Expired</option>
                        <option value="terminated">Terminated</option>
                        <option value="renewed">Renewed</option>
                        <option value="superseded">Superseded</option>
                    </select>
                </div>
                <div class="nks-field">
                    <label class="nks-label">Type</label>
                    <select wire:model="typeFilter" class="nks-select">
                        <option value="">Any</option>
                        <option value="permanent">Permanent</option>
                        <option value="fixed_term">Fixed term</option>
                        <option value="consulting">Consulting</option>
                        <option value="internship">Internship</option>
                    </select>
                </div>
                <div class="nks-field">
                    <label class="nks-label">Expiring</label>
                    <select wire:model="expiringFilter" class="nks-select">
                        <option value="">Any</option>
                        <option value="7">Within 7 days</option>
                        <option value="14">Within 14 days</option>
                        <option value="30">Within 30 days</option>
                        <option value="60">Within 60 days</option>
                        <option value="90">Within 90 days</option>
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

            @if (count($contracts) === 0)
                <div class="nks-card-empty">
                    <i class="fa-solid fa-file-contract"></i>
                    <p>No contracts match your filters.</p>
                </div>
            @else
                <div class="nks-table-wrapper">
                    <table class="nks-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Version</th>
                                <th>Type</th>
                                <th>Effective</th>
                                <th>Expiry</th>
                                <th>Status</th>
                                <th style="width: 220px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($contracts as $c)
                                @php
                                    $status = $c['status'] ?? 'draft';
                                    $days = $this->daysUntilExpiry($c);
                                    $canRenew = in_array($status, ['active', 'expired'], true);
                                    $canTerminate = in_array($status, ['active', 'pending_signature'], true);
                                    $canDelete = in_array($status, ['draft', 'pending_signature'], true);
                                @endphp
                                <tr wire:key="ct-{{ $c['id'] }}" wire:click="openView({{ $c['id'] }})" class="ct-row">
                                    <td>
                                        <strong>
                                            {{ $c['user']['first_name'] ?? '' }}
                                            {{ $c['user']['last_name'] ?? '' }}
                                        </strong>
                                        <div class="ct-meta">{{ $c['user']['employee_number'] ?? '' }}</div>
                                    </td>
                                    <td><span class="ct-version">v{{ $c['version'] ?? '—' }}</span></td>
                                    <td>
                                        <x-nks.badge :tone="self::typeTone($c['contract_type'] ?? '')">
                                            {{ ucfirst(str_replace('_', ' ', $c['contract_type'] ?? '—')) }}
                                        </x-nks.badge>
                                    </td>
                                    <td>
                                        {{ ! empty($c['effective_date']) ? \Carbon\Carbon::parse($c['effective_date'])->format('d M Y') : '—' }}
                                    </td>
                                    <td>
                                        @if (! empty($c['expiry_date']))
                                            <div>{{ \Carbon\Carbon::parse($c['expiry_date'])->format('d M Y') }}</div>
                                            @if ($days !== null)
                                                <x-nks.badge :tone="$this->expiryTone($c)">
                                                    {{ $days < 0 ? abs($days) . 'd ago' : $days . 'd left' }}
                                                </x-nks.badge>
                                            @endif
                                        @else
                                            <span class="ct-muted">No expiry</span>
                                        @endif
                                    </td>
                                    <td>
                                        <x-nks.badge :tone="self::statusTone($status)">
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </x-nks.badge>
                                    </td>
                                    <td @click.stop>
                                        <div class="ct-row-actions">
                                            @if ($canRenew)
                                                <button type="button" wire:click="openRenew({{ $c['id'] }})"
                                                        class="nks-btn nks-btn--outline nks-btn--sm" title="Renew">
                                                    <i class="fa-solid fa-arrows-rotate"></i>
                                                </button>
                                            @endif
                                            @if ($canTerminate)
                                                <button type="button" wire:click="openStatus({{ $c['id'] }})"
                                                        class="nks-btn nks-btn--outline nks-btn--sm" title="Change status">
                                                    <i class="fa-solid fa-toggle-on"></i>
                                                </button>
                                            @endif
                                            @if ($canDelete)
                                                <button type="button" wire:click="openDelete({{ $c['id'] }})"
                                                        class="nks-btn nks-btn--danger nks-btn--sm" title="Delete">
                                                    <i class="fa-solid fa-trash-can"></i>
                                                </button>
                                            @endif
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
                            &middot; {{ $pagination['total'] }} total
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
    @endif

    {{-- Upload modal --}}
    @if ($showUpload)
        <div class="nks-modal-backdrop" wire:click.self="closeUpload" @keydown.escape.window="$wire.closeUpload()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Upload contract</h3>
                    <button type="button" wire:click="closeUpload" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="ct-form-grid">
                        <div class="nks-field ct-form-full">
                            <label class="nks-label">Employee <span class="nks-required">*</span></label>
                            <select wire:model="uploadForm.user_id" class="nks-select">
                                <option value="">Select an employee</option>
                                @foreach ($employees as $emp)
                                    <option value="{{ $emp['id'] }}">
                                        {{ $emp['first_name'] }} {{ $emp['last_name'] }} &middot; {{ $emp['employee_number'] ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field ct-form-full">
                            <label class="nks-label">Contract PDF <span class="nks-required">*</span></label>
                            <input type="file" wire:model="uploadFile" accept="application/pdf"
                                   class="nks-input" style="height: auto; padding: 8px;">
                            <span class="nks-help">PDF only, up to 20MB. File is hashed on upload for chain of custody.</span>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Effective date <span class="nks-required">*</span></label>
                            <input type="date" wire:model="uploadForm.effective_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Expiry date</label>
                            <input type="date" wire:model="uploadForm.expiry_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Probation end</label>
                            <input type="date" wire:model="uploadForm.probation_end_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Contract type</label>
                            <select wire:model="uploadForm.contract_type" class="nks-select">
                                <option value="permanent">Permanent</option>
                                <option value="fixed_term">Fixed term</option>
                                <option value="consulting">Consulting</option>
                                <option value="internship">Internship</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Annual salary (R)</label>
                            <input type="number" wire:model="uploadForm.salary_annual" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Notice period (days)</label>
                            <input type="number" wire:model="uploadForm.notice_period_days" class="nks-input" min="0">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Position</label>
                            <input type="text" wire:model="uploadForm.position" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Initial status</label>
                            <select wire:model="uploadForm.status" class="nks-select">
                                <option value="pending_signature">Pending signature</option>
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                            </select>
                        </div>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeUpload" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitUpload" class="nks-btn nks-btn--primary"
                            wire:loading.attr="disabled" wire:target="submitUpload">
                        <i class="fa-solid fa-cloud-arrow-up" wire:loading.remove wire:target="submitUpload"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="submitUpload"></span>
                        Upload contract
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Renew modal --}}
    @if ($showRenew)
        <div class="nks-modal-backdrop" wire:click.self="closeRenew" @keydown.escape.window="$wire.closeRenew()">
            <div class="nks-modal nks-modal--md" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Renew contract</h3>
                    <button type="button" wire:click="closeRenew" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="ct-modal-asset">{!! $renewLabel !!}</div>

                    <div class="ct-form-row">
                        <div class="nks-field">
                            <label class="nks-label">New effective date <span class="nks-required">*</span></label>
                            <input type="date" wire:model="renewForm.effective_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">New expiry date</label>
                            <input type="date" wire:model="renewForm.expiry_date" class="nks-input">
                        </div>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Annual salary (R)</label>
                        <input type="number" wire:model="renewForm.salary_annual" class="nks-input" min="0" step="0.01">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Notice period (days)</label>
                        <input type="number" wire:model="renewForm.notice_period_days" class="nks-input" min="0">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Position</label>
                        <input type="text" wire:model="renewForm.position" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Contract type</label>
                        <select wire:model="renewForm.contract_type" class="nks-select">
                            <option value="permanent">Permanent</option>
                            <option value="fixed_term">Fixed term</option>
                            <option value="consulting">Consulting</option>
                            <option value="internship">Internship</option>
                        </select>
                    </div>

                    <div class="nks-notice" style="background: var(--nks-elevated); font-size: 12px;">
                        <i class="fa-solid fa-circle-info" style="color: var(--nks-gold-deep);"></i>
                        <div class="nks-notice-body">
                            The current version is superseded. A new version is created in pending signature. Upload the signed PDF to activate it.
                        </div>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeRenew" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitRenew" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        Renew contract
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Status modal --}}
    @if ($showStatus)
        <div class="nks-modal-backdrop" wire:click.self="closeStatus" @keydown.escape.window="$wire.closeStatus()">
            <div class="nks-modal nks-modal--md" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Update contract status</h3>
                    <button type="button" wire:click="closeStatus" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="ct-modal-asset">{!! $statusLabel !!}</div>

                    <div class="nks-field">
                        <label class="nks-label">New status <span class="nks-required">*</span></label>
                        <select wire:model="statusForm.status" class="nks-select">
                            <option value="active">Active</option>
                            <option value="terminated">Terminated</option>
                            <option value="expired">Expired</option>
                            <option value="superseded">Superseded</option>
                        </select>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Effective date</label>
                        <input type="date" wire:model="statusForm.effective_date" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">
                            Reason
                            @if (($statusForm['status'] ?? '') === 'terminated')
                                <span class="nks-required">*</span>
                            @endif
                        </label>
                        <textarea wire:model="statusForm.reason" class="nks-textarea"
                                  placeholder="Required when terminating. Recorded in the audit trail."></textarea>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeStatus" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitStatus" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Update status
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Delete modal --}}
    @if ($showDelete)
        <div class="nks-modal-backdrop" wire:click.self="closeDelete" @keydown.escape.window="$wire.closeDelete()">
            <div class="nks-modal nks-modal--sm" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Delete contract</h3>
                    <button type="button" wire:click="closeDelete" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <p>Delete <strong>{!! $deleteLabel !!}</strong>? Only draft and pending-signature contracts can be deleted. The file is removed from storage.</p>
                    <div class="nks-field">
                        <label class="nks-label">Admin password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="deletePassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeDelete" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitDelete" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-trash-can"></i>
                        Delete contract
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- View modal --}}
    @if ($showView && $viewData)
        @php
            $vStatus = $viewData['status'] ?? '';
            $canRenewV = in_array($vStatus, ['active', 'expired'], true);
        @endphp
        <div class="nks-modal-backdrop" wire:click.self="closeView" @keydown.escape.window="$wire.closeView()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <div>
                        <h3>
                            v{{ $viewData['version'] ?? '—' }} &middot;
                            {{ $viewData['user']['first_name'] ?? '' }}
                            {{ $viewData['user']['last_name'] ?? '' }}
                        </h3>
                        <span class="nks-modal-sub">
                            {{ $viewData['user']['position'] ?? '' }}
                            @if (! empty($viewData['user']['department']))
                                &middot; {{ $viewData['user']['department'] }}
                            @endif
                        </span>
                    </div>
                    <button type="button" wire:click="closeView" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="ct-detail-grid">
                        <div>
                            <span class="ct-detail-label">Status</span>
                            <span class="ct-detail-value">
                                <x-nks.badge :tone="self::statusTone($vStatus)">
                                    {{ ucfirst(str_replace('_', ' ', $vStatus)) }}
                                </x-nks.badge>
                            </span>
                        </div>
                        <div>
                            <span class="ct-detail-label">Type</span>
                            <span class="ct-detail-value">{{ ucfirst(str_replace('_', ' ', $viewData['contract_type'] ?? '—')) }}</span>
                        </div>
                        <div>
                            <span class="ct-detail-label">Effective</span>
                            <span class="ct-detail-value">
                                {{ ! empty($viewData['effective_date']) ? \Carbon\Carbon::parse($viewData['effective_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="ct-detail-label">Expiry</span>
                            <span class="ct-detail-value">
                                {{ ! empty($viewData['expiry_date']) ? \Carbon\Carbon::parse($viewData['expiry_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="ct-detail-label">Annual salary</span>
                            <span class="ct-detail-value">
                                @if (! empty($viewData['salary_annual']))
                                    R {{ number_format((float) $viewData['salary_annual'], 2) }}
                                @else — @endif
                            </span>
                        </div>
                        <div>
                            <span class="ct-detail-label">Notice period</span>
                            <span class="ct-detail-value">
                                {{ ! empty($viewData['notice_period_days']) ? $viewData['notice_period_days'] . ' days' : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="ct-detail-label">Uploaded by</span>
                            <span class="ct-detail-value">
                                {{ $viewData['uploaded_by']['first_name'] ?? '—' }}
                                {{ $viewData['uploaded_by']['last_name'] ?? '' }}
                            </span>
                        </div>
                        <div>
                            <span class="ct-detail-label">Signed</span>
                            <span class="ct-detail-value">{{ ! empty($viewData['signed_by_employee']) ? 'Yes' : 'No' }}</span>
                        </div>
                    </div>

                    @if (! empty($viewData['termination_reason']))
                        <div class="ct-detail-section">
                            <span class="ct-detail-label">Termination reason</span>
                            <p class="ct-detail-text">{{ $viewData['termination_reason'] }}</p>
                        </div>
                    @endif

                    <div class="ct-detail-section">
                        <span class="ct-detail-label">Documents</span>
                        <div class="ct-file-row">
                            @if (! empty($viewData['download_url']))
                                <a href="{{ $viewData['download_url'] }}" target="_blank" rel="noopener"
                                   class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-file-pdf"></i>
                                    Original PDF
                                </a>
                            @endif
                            @if (! empty($viewData['signed_download_url']))
                                <a href="{{ $viewData['signed_download_url'] }}" target="_blank" rel="noopener"
                                   class="nks-btn nks-btn--primary nks-btn--sm">
                                    <i class="fa-solid fa-file-signature"></i>
                                    Signed PDF
                                </a>
                            @endif
                            @if (empty($viewData['download_url']) && empty($viewData['signed_download_url']))
                                <span class="ct-muted">No files attached.</span>
                            @endif
                        </div>
                    </div>

                    @if (! empty($viewData['parent_contract']))
                        <div class="ct-detail-section">
                            <span class="ct-detail-label">Renewed from</span>
                            <p class="ct-detail-text">
                                v{{ $viewData['parent_contract']['version'] ?? '—' }}
                                (effective {{ ! empty($viewData['parent_contract']['effective_date']) ? \Carbon\Carbon::parse($viewData['parent_contract']['effective_date'])->format('d M Y') : '—' }})
                            </p>
                        </div>
                    @endif
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    @if ($canRenewV)
                        <button type="button" wire:click="openRenew({{ $viewData['id'] }})" class="nks-btn nks-btn--primary">
                            <i class="fa-solid fa-arrows-rotate"></i>
                            Renew
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif
</div>