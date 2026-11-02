<div class="page-admin-contracts">
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

    <div class="panel ac-panel">
        <div class="ac-header">
            <i class="fa-solid fa-file-contract ac-header-icon"></i>
            <div>
                <h2>Contracts</h2>
                <p>Upload, renew, terminate, and monitor employment contracts</p>
            </div>
            <button type="button" wire:click="openUpload" class="nks-btn nks-btn--primary ac-create-btn">
                <i class="fa-solid fa-cloud-arrow-up"></i>
                Upload Contract
            </button>
        </div>

        <div class="ac-filters">
            <div class="nks-field ac-filter-search">
                <label class="nks-label">Search</label>
                <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                       class="nks-input" placeholder="Employee name, number, or position">
            </div>
            <div class="nks-field">
                <label class="nks-label">Status</label>
                <select wire:model="statusFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="active">Active</option>
                    <option value="draft">Draft</option>
                    <option value="pending_signature">Pending Signature</option>
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
                    <option value="fixed_term">Fixed Term</option>
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
            <div class="ac-filter-actions">
                <button type="button" wire:click="resetFilters" class="nks-btn nks-btn--ghost nks-btn--sm">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </button>
                <button type="button" wire:click="applyFilters" class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-filter"></i> Apply
                </button>
            </div>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading contracts…" />
        @elseif (count($contracts) === 0)
            <div class="ac-empty">
                <i class="fa-solid fa-file-contract"></i>
                <strong>No contracts found</strong>
                <span>Adjust filters or upload a new contract.</span>
            </div>
        @else
            <div class="ac-table-wrapper">
                <table class="ac-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Version</th>
                            <th>Type</th>
                            <th>Effective</th>
                            <th>Expiry</th>
                            <th>Status</th>
                            <th style="width: 260px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contracts as $c)
                            @php
                                $status = $c['status'] ?? 'draft';
                                $canRenew = in_array($status, ['active', 'expired'], true);
                                $canTerminate = in_array($status, ['active', 'pending_signature'], true);
                                $canDelete = in_array($status, ['draft', 'pending_signature'], true);
                                $expiringSoon = !empty($c['days_until_expiry']) && $c['days_until_expiry'] >= 0 && $c['days_until_expiry'] <= 30;
                            @endphp
                            <tr wire:key="ac-{{ $c['id'] }}">
                                <td>
                                    <div class="ac-name">
                                        {{ $c['user']['first_name'] ?? '' }}
                                        {{ $c['user']['last_name'] ?? '' }}
                                    </div>
                                    <div class="ac-meta">{{ $c['user']['employee_number'] ?? '' }}</div>
                                </td>
                                <td><span class="ac-version">v{{ $c['version'] ?? '—' }}</span></td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Admin\ContractManagement::typeTone($c['contract_type'] ?? '')">
                                        {{ ucfirst(str_replace('_', ' ', $c['contract_type'] ?? '—')) }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    {{ !empty($c['effective_date']) ? \Carbon\Carbon::parse($c['effective_date'])->format('d M Y') : '—' }}
                                </td>
                                <td>
                                    @if (!empty($c['expiry_date']))
                                        <div class="{{ $expiringSoon ? 'ac-expiring' : '' }}">
                                            {{ \Carbon\Carbon::parse($c['expiry_date'])->format('d M Y') }}
                                        </div>
                                        @if ($expiringSoon)
                                            <div class="ac-meta">
                                                {{ (int) $c['days_until_expiry'] }} days left
                                            </div>
                                        @endif
                                    @else
                                        <span class="ac-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Admin\ContractManagement::statusTone($status)">
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    <div class="ac-row-actions">
                                        <button type="button" wire:click="openView({{ $c['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>
                                        @if ($canRenew)
                                            <button type="button" wire:click="openRenew({{ $c['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-arrows-rotate"></i> Renew
                                            </button>
                                        @endif
                                        @if ($canTerminate)
                                            <button type="button" wire:click="openStatus({{ $c['id'] }})"
                                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                                <i class="fa-solid fa-toggle-on"></i> Status
                                            </button>
                                        @endif
                                        @if ($canDelete)
                                            <button type="button" wire:click="openDelete({{ $c['id'] }})"
                                                    class="ac-icon-btn ac-icon-btn--danger" title="Delete">
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
                <div class="ac-pagination">
                    <button type="button" wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] <= 1) disabled @endif>
                        <i class="fa-solid fa-chevron-left"></i> Previous
                    </button>
                    <span class="ac-page-info">
                        Page {{ $pagination['current_page'] }} of {{ $pagination['last_page'] }}
                        ({{ $pagination['total'] }} total)
                    </span>
                    <button type="button" wire:click="gotoPage({{ min($pagination['last_page'], $pagination['current_page'] + 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] >= $pagination['last_page']) disabled @endif>
                        Next <i class="fa-solid fa-chevron-right"></i>
                    </button>
                </div>
            @endif
        @endif
    </div>

    {{-- upload --}}
    @if ($showUpload)
        <div class="ac-modal-backdrop" wire:click.self="closeUpload">
            <div class="ac-modal ac-modal--lg">
                <header class="ac-modal-head">
                    <h3>Upload Contract</h3>
                    <button type="button" wire:click="closeUpload" class="ac-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ac-modal-body">
                    <div class="ac-form-grid">
                        <div class="nks-field ac-form-full">
                            <label class="nks-label">Employee <span class="nks-required">*</span></label>
                            <select wire:model="uploadForm.user_id" class="nks-select">
                                <option value="">Select an employee</option>
                                @foreach ($employees as $emp)
                                    <option value="{{ $emp['id'] }}">
                                        {{ $emp['first_name'] }} {{ $emp['last_name'] }} — {{ $emp['employee_number'] ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="nks-field ac-form-full">
                            <label class="nks-label">Contract PDF <span class="nks-required">*</span></label>
                            <input type="file" wire:model="uploadFile" accept="application/pdf"
                                   class="nks-input" style="height: auto; padding: 8px;">
                            <span class="nks-help">PDF only, up to 20MB. File is SHA-256 hashed on upload for chain-of-custody.</span>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Effective Date <span class="nks-required">*</span></label>
                            <input type="date" wire:model="uploadForm.effective_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Expiry Date</label>
                            <input type="date" wire:model="uploadForm.expiry_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Probation End Date</label>
                            <input type="date" wire:model="uploadForm.probation_end_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Contract Type</label>
                            <select wire:model="uploadForm.contract_type" class="nks-select">
                                <option value="permanent">Permanent</option>
                                <option value="fixed_term">Fixed Term</option>
                                <option value="consulting">Consulting</option>
                                <option value="internship">Internship</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Annual Salary (R)</label>
                            <input type="number" wire:model="uploadForm.salary_annual" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Notice Period (days)</label>
                            <input type="number" wire:model="uploadForm.notice_period_days" class="nks-input" min="0">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Position</label>
                            <input type="text" wire:model="uploadForm.position" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Initial Status</label>
                            <select wire:model="uploadForm.status" class="nks-select">
                                <option value="pending_signature">Pending Signature</option>
                                <option value="draft">Draft</option>
                                <option value="active">Active</option>
                            </select>
                        </div>
                    </div>
                </div>
                <footer class="ac-modal-foot">
                    <button type="button" wire:click="closeUpload" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitUpload" wire:loading.attr="disabled" wire:target="submitUpload"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Upload Contract
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- renew --}}
    @if ($showRenew)
        <div class="ac-modal-backdrop" wire:click.self="closeRenew">
            <div class="ac-modal">
                <header class="ac-modal-head">
                    <h3>Renew Contract</h3>
                    <button type="button" wire:click="closeRenew" class="ac-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ac-modal-body">
                    <div class="ac-modal-asset">
                        <i class="fa-solid fa-file-contract"></i>
                        <span>{{ $renewLabel }}</span>
                    </div>
                    <div class="ac-form-row">
                        <div class="nks-field">
                            <label class="nks-label">New Effective Date <span class="nks-required">*</span></label>
                            <input type="date" wire:model="renewForm.effective_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">New Expiry Date</label>
                            <input type="date" wire:model="renewForm.expiry_date" class="nks-input">
                        </div>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Annual Salary (R)</label>
                        <input type="number" wire:model="renewForm.salary_annual" class="nks-input" min="0" step="0.01">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Notice Period (days)</label>
                        <input type="number" wire:model="renewForm.notice_period_days" class="nks-input" min="0">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Position</label>
                        <input type="text" wire:model="renewForm.position" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Contract Type</label>
                        <select wire:model="renewForm.contract_type" class="nks-select">
                            <option value="permanent">Permanent</option>
                            <option value="fixed_term">Fixed Term</option>
                            <option value="consulting">Consulting</option>
                            <option value="internship">Internship</option>
                        </select>
                    </div>
                    <div class="ac-note">
                        <i class="fa-solid fa-circle-info"></i>
                        <span>The current version is superseded and a new version is created in <em>pending_signature</em>. Upload the signed PDF to activate the renewal.</span>
                    </div>
                </div>
                <footer class="ac-modal-foot">
                    <button type="button" wire:click="closeRenew" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitRenew" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-arrows-rotate"></i> Renew Contract
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- status --}}
    @if ($showStatus)
        <div class="ac-modal-backdrop" wire:click.self="closeStatus">
            <div class="ac-modal">
                <header class="ac-modal-head">
                    <h3>Update Contract Status</h3>
                    <button type="button" wire:click="closeStatus" class="ac-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ac-modal-body">
                    <div class="ac-modal-asset">
                        <i class="fa-solid fa-file-contract"></i>
                        <span>{{ $statusLabel }}</span>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">New Status <span class="nks-required">*</span></label>
                        <select wire:model="statusForm.status" class="nks-select">
                            <option value="active">Active</option>
                            <option value="terminated">Terminated</option>
                            <option value="expired">Expired</option>
                            <option value="superseded">Superseded</option>
                        </select>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Effective Date</label>
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
                                  placeholder="Required when terminating. Explain the reason for the audit trail."></textarea>
                    </div>
                </div>
                <footer class="ac-modal-foot">
                    <button type="button" wire:click="closeStatus" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitStatus" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i> Update Status
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- delete --}}
    @if ($showDelete)
        <div class="ac-modal-backdrop" wire:click.self="closeDelete">
            <div class="ac-modal">
                <header class="ac-modal-head">
                    <h3>Delete Contract</h3>
                    <button type="button" wire:click="closeDelete" class="ac-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ac-modal-body">
                    <p class="ac-text">
                        Delete <strong>{{ $deleteLabel }}</strong>?
                        Only draft and pending-signature contracts can be deleted. The file is removed from storage.
                        This action cannot be undone.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Admin Password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="deletePassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="ac-modal-foot">
                    <button type="button" wire:click="closeDelete" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitDelete" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-trash-can"></i> Delete Contract
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- view --}}
    @if ($showView && $viewData)
        @php
            $vStatus = $viewData['status'] ?? '';
            $canRenewV = in_array($vStatus, ['active', 'expired'], true);
        @endphp
        <div class="ac-modal-backdrop" wire:click.self="closeView">
            <div class="ac-modal ac-modal--lg">
                <header class="ac-modal-head">
                    <div>
                        <h3>
                            v{{ $viewData['version'] ?? '—' }} —
                            {{ $viewData['user']['first_name'] ?? '' }}
                            {{ $viewData['user']['last_name'] ?? '' }}
                        </h3>
                        <span class="ac-modal-sub">{{ $viewData['user']['position'] ?? '' }} · {{ $viewData['user']['department'] ?? '' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="ac-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="ac-modal-body">
                    <div class="ac-detail-grid">
                        <div>
                            <span class="ac-detail-label">Status</span>
                            <span class="ac-detail-value">
                                <x-nks.badge :tone="\App\Livewire\Admin\ContractManagement::statusTone($vStatus)">
                                    {{ ucfirst(str_replace('_', ' ', $vStatus)) }}
                                </x-nks.badge>
                            </span>
                        </div>
                        <div>
                            <span class="ac-detail-label">Type</span>
                            <span class="ac-detail-value">{{ ucfirst(str_replace('_', ' ', $viewData['contract_type'] ?? '—')) }}</span>
                        </div>
                        <div>
                            <span class="ac-detail-label">Effective</span>
                            <span class="ac-detail-value">
                                {{ !empty($viewData['effective_date']) ? \Carbon\Carbon::parse($viewData['effective_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="ac-detail-label">Expiry</span>
                            <span class="ac-detail-value">
                                {{ !empty($viewData['expiry_date']) ? \Carbon\Carbon::parse($viewData['expiry_date'])->format('d M Y') : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="ac-detail-label">Annual Salary</span>
                            <span class="ac-detail-value">
                                @if (!empty($viewData['salary_annual']))
                                    R {{ number_format((float) $viewData['salary_annual'], 2) }}
                                @else — @endif
                            </span>
                        </div>
                        <div>
                            <span class="ac-detail-label">Notice Period</span>
                            <span class="ac-detail-value">
                                {{ !empty($viewData['notice_period_days']) ? $viewData['notice_period_days'] . ' days' : '—' }}
                            </span>
                        </div>
                        <div>
                            <span class="ac-detail-label">Uploaded By</span>
                            <span class="ac-detail-value">
                                {{ $viewData['uploaded_by']['first_name'] ?? '—' }}
                                {{ $viewData['uploaded_by']['last_name'] ?? '' }}
                            </span>
                        </div>
                        <div>
                            <span class="ac-detail-label">Signed</span>
                            <span class="ac-detail-value">
                                {{ !empty($viewData['signed_by_employee']) ? 'Yes' : 'No' }}
                            </span>
                        </div>
                    </div>

                    @if (!empty($viewData['termination_reason']))
                        <div class="ac-detail-section">
                            <span class="ac-detail-label">Termination Reason</span>
                            <p class="ac-detail-text">{{ $viewData['termination_reason'] }}</p>
                        </div>
                    @endif

                    <div class="ac-detail-section">
                        <span class="ac-detail-label">Documents</span>
                        <div class="ac-file-row">
                            @if (!empty($viewData['download_url']))
                                <a href="{{ $viewData['download_url'] }}" target="_blank" rel="noopener"
                                   class="nks-btn nks-btn--outline nks-btn--sm">
                                    <i class="fa-solid fa-file-pdf"></i>
                                    Original PDF
                                </a>
                            @endif
                            @if (!empty($viewData['signed_download_url']))
                                <a href="{{ $viewData['signed_download_url'] }}" target="_blank" rel="noopener"
                                   class="nks-btn nks-btn--primary nks-btn--sm">
                                    <i class="fa-solid fa-file-signature"></i>
                                    Signed PDF
                                </a>
                            @endif
                            @if (empty($viewData['download_url']) && empty($viewData['signed_download_url']))
                                <span class="ac-muted">No files attached.</span>
                            @endif
                        </div>
                    </div>

                    @if (!empty($viewData['parent_contract']))
                        <div class="ac-detail-section">
                            <span class="ac-detail-label">Renewed From</span>
                            <p class="ac-detail-text">
                                v{{ $viewData['parent_contract']['version'] ?? '—' }}
                                (effective {{ !empty($viewData['parent_contract']['effective_date'])
                                    ? \Carbon\Carbon::parse($viewData['parent_contract']['effective_date'])->format('d M Y')
                                    : '—' }})
                            </p>
                        </div>
                    @endif
                </div>
                <footer class="ac-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    @if ($canRenewV)
                        <button type="button" wire:click="openRenew({{ $viewData['id'] }})" class="nks-btn nks-btn--primary">
                            <i class="fa-solid fa-arrows-rotate"></i> Renew
                        </button>
                    @endif
                </footer>
            </div>
        </div>
    @endif
</div>