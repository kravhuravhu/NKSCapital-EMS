<div class="page-admin-assets">
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

    <div class="aa-stats">
        <div class="aa-stat">
            <div class="aa-stat-head"><i class="fa-solid fa-laptop"></i><span>Total</span></div>
            <div class="aa-stat-value">{{ $stats['total'] ?? 0 }}</div>
        </div>
        <div class="aa-stat aa-stat--success">
            <div class="aa-stat-head"><i class="fa-solid fa-circle-check"></i><span>Available</span></div>
            <div class="aa-stat-value">{{ $stats['available'] ?? 0 }}</div>
        </div>
        <div class="aa-stat aa-stat--blue">
            <div class="aa-stat-head"><i class="fa-solid fa-user"></i><span>Loaned</span></div>
            <div class="aa-stat-value">{{ $stats['loaned'] ?? 0 }}</div>
        </div>
        <div class="aa-stat aa-stat--warning">
            <div class="aa-stat-head"><i class="fa-solid fa-triangle-exclamation"></i><span>Overdue</span></div>
            <div class="aa-stat-value">{{ $stats['overdue'] ?? 0 }}</div>
        </div>
        <div class="aa-stat">
            <div class="aa-stat-head"><i class="fa-solid fa-screwdriver-wrench"></i><span>In Repair</span></div>
            <div class="aa-stat-value">{{ $stats['in_repair'] ?? 0 }}</div>
        </div>
        <div class="aa-stat">
            <div class="aa-stat-head"><i class="fa-solid fa-coins"></i><span>Value</span></div>
            <div class="aa-stat-value">R {{ number_format($stats['total_value'] ?? 0, 0) }}</div>
        </div>
    </div>

    <div class="panel aa-panel">
        <div class="aa-header">
            <i class="fa-solid fa-laptop aa-header-icon"></i>
            <div>
                <h2>Asset Inventory</h2>
                <p>Register, loan, return, and track company devices</p>
            </div>
            <button type="button" wire:click="openRegister" class="nks-btn nks-btn--primary aa-create-btn">
                <i class="fa-solid fa-plus"></i>
                Register Asset
            </button>
        </div>

        <div class="aa-filters">
            <div class="nks-field aa-filter-search">
                <label class="nks-label">Search</label>
                <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                       class="nks-input" placeholder="Tag, serial, model, or assignee">
            </div>
            <div class="nks-field">
                <label class="nks-label">Status</label>
                <select wire:model="statusFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="available">Available</option>
                    <option value="loaned">Loaned</option>
                    <option value="repair_requested">Repair Requested</option>
                    <option value="maintenance">Maintenance</option>
                    <option value="retired">Retired</option>
                    <option value="lost">Lost</option>
                    <option value="damaged">Damaged</option>
                </select>
            </div>
            <div class="nks-field">
                <label class="nks-label">Condition</label>
                <select wire:model="conditionFilter" class="nks-select">
                    <option value="">Any</option>
                    <option value="new">New</option>
                    <option value="good">Good</option>
                    <option value="fair">Fair</option>
                    <option value="poor">Poor</option>
                    <option value="damaged">Damaged</option>
                    <option value="for_repair">For Repair</option>
                    <option value="refurbished">Refurbished</option>
                    <option value="obsolete">Obsolete</option>
                </select>
            </div>
            <div class="aa-filter-actions">
                <label class="aa-checkbox">
                    <input type="checkbox" wire:model="overdueOnly">
                    <span>Overdue only</span>
                </label>
                <button type="button" wire:click="resetFilters" class="nks-btn nks-btn--ghost nks-btn--sm">
                    <i class="fa-solid fa-rotate-left"></i> Reset
                </button>
                <button type="button" wire:click="applyFilters" class="nks-btn nks-btn--primary nks-btn--sm">
                    <i class="fa-solid fa-filter"></i> Apply
                </button>
            </div>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading assets…" />
        @elseif (count($assets) === 0)
            <div class="aa-empty">
                <i class="fa-solid fa-laptop-slash"></i>
                <strong>No assets found</strong>
                <span>Adjust filters or register your first asset.</span>
            </div>
        @else
            <div class="aa-table-wrapper">
                <table class="aa-table">
                    <thead>
                        <tr>
                            <th>Asset</th>
                            <th>Assignee</th>
                            <th>Condition</th>
                            <th>Status</th>
                            <th>Expected Return</th>
                            <th>Overdue</th>
                            <th style="width: 240px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($assets as $asset)
                            @php
                                $status = $asset['status'] ?? 'available';
                                $isLoaned = $status === 'loaned';
                                $isAvailable = $status === 'available';
                                $isRetired = $status === 'retired';
                            @endphp
                            <tr wire:key="aa-{{ $asset['id'] }}">
                                <td>
                                    <div class="aa-name">{{ $asset['model'] ?? '—' }}</div>
                                    <div class="aa-meta">
                                        {{ $asset['asset_tag'] ?? '' }}
                                        @if (!empty($asset['serial_number']))
                                            · {{ $asset['serial_number'] }}
                                        @endif
                                    </div>
                                </td>
                                <td>
                                    @if (!empty($asset['current_assignee']))
                                        <div class="aa-name">
                                            {{ $asset['current_assignee']['first_name'] ?? '' }}
                                            {{ $asset['current_assignee']['last_name'] ?? '' }}
                                        </div>
                                        <div class="aa-meta">
                                            {{ $asset['current_assignee']['employee_number'] ?? '' }}
                                        </div>
                                    @else
                                        <span class="aa-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Admin\AssetManagement::conditionTone($asset['condition'] ?? '')">
                                        {{ ucfirst(str_replace('_', ' ', $asset['condition'] ?? '')) }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Admin\AssetManagement::statusTone($status)">
                                        {{ ucfirst(str_replace('_', ' ', $status)) }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    @if (!empty($asset['active_loan'][0]['expected_return_date']))
                                        {{ \Carbon\Carbon::parse($asset['active_loan'][0]['expected_return_date'])->format('d M Y') }}
                                    @elseif (!empty($asset['expected_return_date']))
                                        {{ \Carbon\Carbon::parse($asset['expected_return_date'])->format('d M Y') }}
                                    @else
                                        <span class="aa-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!empty($asset['is_overdue']))
                                        <span class="aa-overdue">
                                            <i class="fa-solid fa-triangle-exclamation"></i>
                                            {{ (int) ($asset['days_overdue'] ?? 0) }}d
                                        </span>
                                    @else
                                        <span class="aa-muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="aa-row-actions">
                                        <button type="button" wire:click="openView({{ $asset['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i> View
                                        </button>
                                        @if ($isAvailable)
                                            <button type="button" wire:click="openLoan({{ $asset['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-arrow-right-from-bracket"></i> Loan
                                            </button>
                                        @endif
                                        @if ($isLoaned)
                                            <button type="button" wire:click="openReturn({{ $asset['id'] }})"
                                                    class="nks-btn nks-btn--primary nks-btn--sm">
                                                <i class="fa-solid fa-arrow-right-to-bracket"></i> Return
                                            </button>
                                        @endif
                                        <button type="button" wire:click="openEdit({{ $asset['id'] }})"
                                                class="aa-icon-btn" title="Edit">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        @unless ($isRetired)
                                            <button type="button" wire:click="openRetire({{ $asset['id'] }})"
                                                    class="aa-icon-btn aa-icon-btn--danger" title="Retire">
                                                <i class="fa-solid fa-box-archive"></i>
                                            </button>
                                        @endunless
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if (($pagination['last_page'] ?? 1) > 1)
                <div class="aa-pagination">
                    <button type="button" wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] <= 1) disabled @endif>
                        <i class="fa-solid fa-chevron-left"></i> Previous
                    </button>
                    <span class="aa-page-info">
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

    {{-- register --}}
    @if ($showRegister)
        <div class="aa-modal-backdrop" wire:click.self="closeRegister">
            <div class="aa-modal aa-modal--lg">
                <header class="aa-modal-head">
                    <h3>Register Asset</h3>
                    <button type="button" wire:click="closeRegister" class="aa-modal-close"><i class="fa-solid fa-xmark"></i></button>
                </header>
                <div class="aa-modal-body">
                    <div class="aa-form-grid">
                        <div class="nks-field">
                            <label class="nks-label">Asset Tag <span class="nks-required">*</span></label>
                            <input type="text" wire:model="registerForm.asset_tag" class="nks-input"
                                   placeholder="e.g. NKS-LAP-001">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Serial Number</label>
                            <input type="text" wire:model="registerForm.serial_number" class="nks-input">
                        </div>
                        <div class="nks-field aa-form-full">
                            <label class="nks-label">Model <span class="nks-required">*</span></label>
                            <input type="text" wire:model="registerForm.model" class="nks-input"
                                   placeholder="e.g. Dell Latitude 5540">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Manufacturer</label>
                            <input type="text" wire:model="registerForm.manufacturer" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Condition</label>
                            <select wire:model="registerForm.condition" class="nks-select">
                                <option value="new">New</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                                <option value="refurbished">Refurbished</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Purchase Date</label>
                            <input type="date" wire:model="registerForm.purchase_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Purchase Price (R)</label>
                            <input type="number" wire:model="registerForm.purchase_price" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Warranty Expiry</label>
                            <input type="date" wire:model="registerForm.warranty_expiry" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Location</label>
                            <input type="text" wire:model="registerForm.location" class="nks-input">
                        </div>
                        <div class="nks-field aa-form-full">
                            <label class="nks-label">Notes</label>
                            <textarea wire:model="registerForm.notes" class="nks-textarea"></textarea>
                        </div>
                    </div>
                </div>
                <footer class="aa-modal-foot">
                    <button type="button" wire:click="closeRegister" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitRegister" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i> Register
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- loan --}}
    @if ($showLoan)
        <div class="aa-modal-backdrop" wire:click.self="closeLoan">
            <div class="aa-modal">
                <header class="aa-modal-head">
                    <h3>Loan Asset</h3>
                    <button type="button" wire:click="closeLoan" class="aa-modal-close"><i class="fa-solid fa-xmark"></i></button>
                </header>
                <div class="aa-modal-body">
                    <div class="aa-modal-asset">
                        <i class="fa-solid fa-laptop"></i>
                        <span>{{ $loanAssetLabel }}</span>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Assign To <span class="nks-required">*</span></label>
                        <select wire:model="loanForm.assigned_to_id" class="nks-select">
                            <option value="">Select an employee</option>
                            @foreach ($employees as $emp)
                                <option value="{{ $emp['id'] }}">{{ $emp['first_name'] }} {{ $emp['last_name'] }} — {{ $emp['employee_number'] ?? '' }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="aa-form-row">
                        <div class="nks-field">
                            <label class="nks-label">Checkout Date</label>
                            <input type="date" wire:model="loanForm.checkout_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Expected Return</label>
                            <input type="date" wire:model="loanForm.expected_return_date" class="nks-input">
                        </div>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Checkout Condition</label>
                        <select wire:model="loanForm.checkout_condition" class="nks-select">
                            <option value="new">New</option>
                            <option value="good">Good</option>
                            <option value="fair">Fair</option>
                        </select>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Location</label>
                        <input type="text" wire:model="loanForm.location" class="nks-input" placeholder="Optional">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Notes</label>
                        <textarea wire:model="loanForm.notes" class="nks-textarea"></textarea>
                    </div>
                </div>
                <footer class="aa-modal-foot">
                    <button type="button" wire:click="closeLoan" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitLoan" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i> Loan Asset
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- return --}}
    @if ($showReturn)
        <div class="aa-modal-backdrop" wire:click.self="closeReturn">
            <div class="aa-modal">
                <header class="aa-modal-head">
                    <h3>Return Asset</h3>
                    <button type="button" wire:click="closeReturn" class="aa-modal-close"><i class="fa-solid fa-xmark"></i></button>
                </header>
                <div class="aa-modal-body">
                    <div class="aa-modal-asset">
                        <i class="fa-solid fa-laptop"></i>
                        <span>{{ $returnAssetLabel }}</span>
                    </div>
                    <div class="aa-form-row">
                        <div class="nks-field">
                            <label class="nks-label">Return Date</label>
                            <input type="date" wire:model="returnForm.actual_return_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Condition on Return</label>
                            <select wire:model="returnForm.return_condition" class="nks-select">
                                <option value="new">New</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                                <option value="damaged">Damaged</option>
                                <option value="for_repair">For Repair</option>
                            </select>
                        </div>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Repair Priority</label>
                        <select wire:model="returnForm.repair_priority" class="nks-select">
                            <option value="">Not needed</option>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Damage Photo (optional)</label>
                        <input type="file" wire:model="returnPhoto" accept="image/*" class="nks-input"
                               style="height: auto; padding: 8px;">
                        <span class="nks-help">JPG or PNG, up to 10MB. Auto-hashed for chain-of-custody.</span>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Return Location</label>
                        <input type="text" wire:model="returnForm.returned_to_location" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Notes</label>
                        <textarea wire:model="returnForm.notes" class="nks-textarea"></textarea>
                    </div>
                </div>
                <footer class="aa-modal-foot">
                    <button type="button" wire:click="closeReturn" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitReturn" wire:loading.attr="disabled" wire:target="submitReturn"
                            class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i> Confirm Return
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- edit --}}
    @if ($showEdit && $editId)
        <div class="aa-modal-backdrop" wire:click.self="closeEdit">
            <div class="aa-modal aa-modal--lg">
                <header class="aa-modal-head">
                    <h3>Edit Asset</h3>
                    <button type="button" wire:click="closeEdit" class="aa-modal-close"><i class="fa-solid fa-xmark"></i></button>
                </header>
                <div class="aa-modal-body">
                    <div class="aa-form-grid">
                        <div class="nks-field aa-form-full">
                            <label class="nks-label">Model</label>
                            <input type="text" wire:model="editForm.model" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Manufacturer</label>
                            <input type="text" wire:model="editForm.manufacturer" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Serial Number</label>
                            <input type="text" wire:model="editForm.serial_number" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Condition</label>
                            <select wire:model="editForm.condition" class="nks-select">
                                <option value="new">New</option>
                                <option value="good">Good</option>
                                <option value="fair">Fair</option>
                                <option value="poor">Poor</option>
                                <option value="damaged">Damaged</option>
                                <option value="for_repair">For Repair</option>
                                <option value="refurbished">Refurbished</option>
                                <option value="obsolete">Obsolete</option>
                            </select>
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Location</label>
                            <input type="text" wire:model="editForm.location" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Purchase Date</label>
                            <input type="date" wire:model="editForm.purchase_date" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Purchase Price (R)</label>
                            <input type="number" wire:model="editForm.purchase_price" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Warranty Expiry</label>
                            <input type="date" wire:model="editForm.warranty_expiry" class="nks-input">
                        </div>
                        <div class="nks-field aa-form-full">
                            <label class="nks-label">Notes</label>
                            <textarea wire:model="editForm.notes" class="nks-textarea"></textarea>
                        </div>
                    </div>
                </div>
                <footer class="aa-modal-foot">
                    <button type="button" wire:click="closeEdit" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitEdit" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i> Save Changes
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- retire --}}
    @if ($showRetire)
        <div class="aa-modal-backdrop" wire:click.self="closeRetire">
            <div class="aa-modal">
                <header class="aa-modal-head">
                    <h3>Retire Asset</h3>
                    <button type="button" wire:click="closeRetire" class="aa-modal-close"><i class="fa-solid fa-xmark"></i></button>
                </header>
                <div class="aa-modal-body">
                    <p class="aa-text">
                        Retire <strong>{{ $retireLabel }}</strong>? The asset will be marked as retired and excluded from active inventory.
                        Its custody history is preserved.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">Admin Password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="retirePassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="aa-modal-foot">
                    <button type="button" wire:click="closeRetire" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitRetire" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-box-archive"></i> Retire Asset
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- view history --}}
    @if ($showView && $viewData)
        @php $asset = $viewData['asset'] ?? []; @endphp
        <div class="aa-modal-backdrop" wire:click.self="closeView">
            <div class="aa-modal aa-modal--lg">
                <header class="aa-modal-head">
                    <div>
                        <h3>{{ $asset['model'] ?? 'Asset' }}</h3>
                        <span class="aa-modal-sub">{{ $asset['asset_tag'] ?? '' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="aa-modal-close"><i class="fa-solid fa-xmark"></i></button>
                </header>
                <div class="aa-modal-body">
                    <div class="aa-detail-grid">
                        <div><span class="aa-detail-label">Serial</span><span class="aa-detail-value">{{ $asset['serial_number'] ?? '—' }}</span></div>
                        <div><span class="aa-detail-label">Manufacturer</span><span class="aa-detail-value">{{ $asset['manufacturer'] ?? '—' }}</span></div>
                        <div><span class="aa-detail-label">Condition</span><span class="aa-detail-value">{{ ucfirst(str_replace('_', ' ', $asset['condition'] ?? '')) }}</span></div>
                        <div><span class="aa-detail-label">Status</span><span class="aa-detail-value">{{ ucfirst(str_replace('_', ' ', $asset['status'] ?? '')) }}</span></div>
                        <div><span class="aa-detail-label">Location</span><span class="aa-detail-value">{{ $asset['location'] ?? '—' }}</span></div>
                        <div><span class="aa-detail-label">Purchased</span><span class="aa-detail-value">
                            {{ !empty($asset['purchase_date']) ? \Carbon\Carbon::parse($asset['purchase_date'])->format('d M Y') : '—' }}
                        </span></div>
                        <div><span class="aa-detail-label">Warranty</span><span class="aa-detail-value">
                            {{ !empty($asset['warranty_expiry']) ? \Carbon\Carbon::parse($asset['warranty_expiry'])->format('d M Y') : '—' }}
                        </span></div>
                    </div>

                    <h4 class="aa-section-title">Custody History</h4>

                    @if (empty($viewData['custody_history']))
                        <div class="aa-inline-empty">
                            <i class="fa-solid fa-circle-info"></i>
                            No custody records yet.
                        </div>
                    @else
                        <div class="aa-history-wrapper">
                            <table class="aa-history-table">
                                <thead>
                                    <tr>
                                        <th>Assignee</th>
                                        <th>Checkout</th>
                                        <th>Expected</th>
                                        <th>Returned</th>
                                        <th>Condition</th>
                                        <th>Repair</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($viewData['custody_history'] as $h)
                                        <tr>
                                            <td>
                                                @if (!empty($h['assigned_to']))
                                                    {{ $h['assigned_to']['first_name'] ?? '' }}
                                                    {{ $h['assigned_to']['last_name'] ?? '' }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td>{{ !empty($h['checkout_date']) ? \Carbon\Carbon::parse($h['checkout_date'])->format('d M Y') : '—' }}</td>
                                            <td>{{ !empty($h['expected_return_date']) ? \Carbon\Carbon::parse($h['expected_return_date'])->format('d M Y') : '—' }}</td>
                                            <td>
                                                @if (!empty($h['actual_return_date']))
                                                    {{ \Carbon\Carbon::parse($h['actual_return_date'])->format('d M Y') }}
                                                @else
                                                    <span class="aa-muted">still out</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if (!empty($h['return_condition']))
                                                    <x-nks.badge :tone="\App\Livewire\Admin\AssetManagement::conditionTone($h['return_condition'])">
                                                        {{ ucfirst(str_replace('_', ' ', $h['return_condition'])) }}
                                                    </x-nks.badge>
                                                @else — @endif
                                            </td>
                                            <td>
                                                @if (!empty($h['repair_priority']))
                                                    <span class="aa-priority aa-priority--{{ $h['repair_priority'] }}">
                                                        {{ ucfirst($h['repair_priority']) }}
                                                    </span>
                                                @else — @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
                <footer class="aa-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                </footer>
            </div>
        </div>
    @endif
</div>