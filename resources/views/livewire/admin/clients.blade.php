<div class="page-admin-clients">
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
        <x-nks.spinner block label="Loading clients..." />
    @else
        <div class="ac-stats">
            <div class="ac-stat">
                <div class="ac-stat-icon"><i class="fa-solid fa-building" aria-hidden="true"></i></div>
                <div>
                    <div class="ac-stat-label">Total</div>
                    <div class="ac-stat-value">{{ $stats['total'] }}</div>
                </div>
            </div>
            <div class="ac-stat ac-stat--success">
                <div class="ac-stat-icon"><i class="fa-solid fa-circle-check" aria-hidden="true"></i></div>
                <div>
                    <div class="ac-stat-label">Active</div>
                    <div class="ac-stat-value">{{ $stats['active'] }}</div>
                </div>
            </div>
            <div class="ac-stat">
                <div class="ac-stat-icon"><i class="fa-solid fa-circle-pause" aria-hidden="true"></i></div>
                <div>
                    <div class="ac-stat-label">Inactive</div>
                    <div class="ac-stat-value">{{ $stats['inactive'] }}</div>
                </div>
            </div>
            <div class="ac-stat ac-stat--warning">
                <div class="ac-stat-icon"><i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i></div>
                <div>
                    <div class="ac-stat-label">Suspended</div>
                    <div class="ac-stat-value">{{ $stats['suspended'] }}</div>
                </div>
            </div>
            <div class="ac-stat ac-stat--gold">
                <div class="ac-stat-icon"><i class="fa-solid fa-coins" aria-hidden="true"></i></div>
                <div>
                    <div class="ac-stat-label">Contract value</div>
                    <div class="ac-stat-value">R {{ number_format($stats['total_value'], 0) }}</div>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="page-toolbar">
                <div>
                    <h2>Clients</h2>
                    <p>Manage client companies, contacts, and contracts</p>
                </div>
                <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary">
                    <i class="fa-solid fa-plus"></i>
                    Create client
                </button>
            </div>

            <div class="ac-filters">
                <div class="nks-field ac-filter-search">
                    <label class="nks-label">Search</label>
                    <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                           class="nks-input" placeholder="Company, reg number, or contact">
                </div>
                <div class="nks-field">
                    <label class="nks-label">Status</label>
                    <select wire:model="statusFilter" class="nks-select">
                        <option value="">Any</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="suspended">Suspended</option>
                    </select>
                </div>
                <div class="nks-field">
                    <label class="nks-label">Active</label>
                    <select wire:model="activeFilter" class="nks-select">
                        <option value="">Any</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="ac-filter-actions">
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

            @if (count($clients) === 0)
                <div class="nks-card-empty">
                    <i class="fa-solid fa-building"></i>
                    <p>No clients match your filters.</p>
                </div>
            @else
                <div class="nks-table-wrapper">
                    <table class="nks-table">
                        <thead>
                            <tr>
                                <th>Company</th>
                                <th>Primary contact</th>
                                <th>Contract value</th>
                                <th>Projects</th>
                                <th>Status</th>
                                <th style="width: 160px;"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($clients as $client)
                                <tr wire:key="cl-{{ $client['id'] }}" wire:click="openView({{ $client['id'] }})" class="ac-row">
                                    <td>
                                        <strong>{{ $client['company_name'] ?? '—' }}</strong>
                                        <div class="ac-meta">{{ $client['registration_number'] ?? '' }}</div>
                                    </td>
                                    <td>
                                        <div>{{ $client['primary_contact_name'] ?? '—' }}</div>
                                        <div class="ac-meta">{{ $client['primary_contact_email'] ?? '' }}</div>
                                    </td>
                                    <td>
                                        @if (! empty($client['contract_value']))
                                            R {{ number_format((float) $client['contract_value'], 2) }}
                                        @else
                                            <span class="ac-muted">—</span>
                                        @endif
                                    </td>
                                    <td>{{ $client['projects_count'] ?? 0 }}</td>
                                    <td>
                                        <x-nks.badge :tone="self::statusTone($client['status'] ?? '')">
                                            {{ ucfirst($client['status'] ?? '—') }}
                                        </x-nks.badge>
                                    </td>
                                    <td @click.stop>
                                        <div class="ac-row-actions">
                                            <button type="button" wire:click="openEdit({{ $client['id'] }})"
                                                    class="nks-btn nks-btn--outline nks-btn--sm" title="Edit">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>
                                            @if (! empty($client['is_active']))
                                                <button type="button" wire:click="openDeactivate({{ $client['id'] }})"
                                                        class="nks-btn nks-btn--danger nks-btn--sm" title="Deactivate">
                                                    <i class="fa-solid fa-ban"></i>
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
                            <i class="fa-solid fa-chevron-left"></i>
                            Previous
                        </button>
                        <span class="ac-page-info">
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

    {{-- View modal --}}
    @if ($showView && $viewClient)
        <div class="nks-modal-backdrop" wire:click.self="closeView" @keydown.escape.window="$wire.closeView()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <div>
                        <h3>{{ $viewClient['company_name'] ?? 'Client' }}</h3>
                        <span class="nks-modal-sub">{{ $viewClient['registration_number'] ?? '' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="ac-detail-grid">
                        <div>
                            <span class="ac-detail-label">Status</span>
                            <span class="ac-detail-value">
                                <x-nks.badge :tone="self::statusTone($viewClient['status'] ?? '')">
                                    {{ ucfirst($viewClient['status'] ?? '—') }}
                                </x-nks.badge>
                            </span>
                        </div>
                        <div>
                            <span class="ac-detail-label">VAT number</span>
                            <span class="ac-detail-value">{{ $viewClient['vat_number'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ac-detail-label">Contract value</span>
                            <span class="ac-detail-value">
                                @if (! empty($viewClient['contract_value']))
                                    R {{ number_format((float) $viewClient['contract_value'], 2) }}
                                @else
                                    —
                                @endif
                            </span>
                        </div>
                        <div>
                            <span class="ac-detail-label">Payment terms</span>
                            <span class="ac-detail-value">{{ $viewClient['payment_terms'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="ac-detail-label">Projects</span>
                            <span class="ac-detail-value">{{ $viewClient['projects_count'] ?? 0 }}</span>
                        </div>
                        <div>
                            <span class="ac-detail-label">Assigned employees</span>
                            <span class="ac-detail-value">{{ $viewClient['users_count'] ?? 0 }}</span>
                        </div>
                    </div>

                    <div class="ac-detail-section">
                        <h4 class="ac-detail-title">Primary contact</h4>
                        <div class="ac-contact-card">
                            <div class="ac-contact-name">{{ $viewClient['primary_contact_name'] ?? '—' }}</div>
                            <div class="ac-contact-line">
                                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                                {{ $viewClient['primary_contact_email'] ?? '—' }}
                            </div>
                            <div class="ac-contact-line">
                                <i class="fa-solid fa-phone" aria-hidden="true"></i>
                                {{ $viewClient['primary_contact_phone'] ?? '—' }}
                            </div>
                        </div>
                    </div>

                    @if (! empty($viewClient['billing_address']))
                        <div class="ac-detail-section">
                            <h4 class="ac-detail-title">Billing address</h4>
                            <p class="ac-detail-text">{{ $viewClient['billing_address'] }}</p>
                        </div>
                    @endif

                    <div class="ac-detail-section">
                        <div class="ac-detail-title-row">
                            <h4 class="ac-detail-title">Client managers</h4>
                            <button type="button" wire:click="openAddManager({{ $viewClient['id'] }})"
                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                <i class="fa-solid fa-user-plus"></i>
                                Add manager
                            </button>
                        </div>

                        @if (empty($viewClient['managers']))
                            <div class="nks-card-empty" style="padding: 20px 12px;">
                                <i class="fa-solid fa-circle-info"></i>
                                <p>No client managers on file.</p>
                            </div>
                        @else
                            <div class="ac-manager-list">
                                @foreach ($viewClient['managers'] as $mgr)
                                    <div class="ac-manager-row">
                                        <div class="ac-manager-avatar">
                                            <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                                        </div>
                                        <div class="ac-manager-body">
                                            <strong>
                                                {{ $mgr['name'] ?? '—' }}
                                                @if (! empty($mgr['is_primary']))
                                                    <x-nks.badge tone="gold">Primary</x-nks.badge>
                                                @endif
                                            </strong>
                                            <span>{{ $mgr['email'] ?? '—' }}</span>
                                            @if (! empty($mgr['phone']))
                                                <span>{{ $mgr['phone'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    <button type="button" wire:click="openEdit({{ $viewClient['id'] }})" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-pen"></i>
                        Edit client
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Create modal --}}
    @if ($showCreate)
        <div class="nks-modal-backdrop" wire:click.self="closeCreate" @keydown.escape.window="$wire.closeCreate()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Create client</h3>
                    <button type="button" wire:click="closeCreate" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <h4 class="ac-form-section">Company details</h4>
                    <div class="ac-form-grid">
                        <div class="nks-field ac-form-full">
                            <label class="nks-label">Company name <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.company_name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Registration number <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.registration_number" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">VAT number</label>
                            <input type="text" wire:model="createForm.vat_number" class="nks-input">
                        </div>
                        <div class="nks-field ac-form-full">
                            <label class="nks-label">Billing address</label>
                            <textarea wire:model="createForm.billing_address" class="nks-textarea"></textarea>
                        </div>
                    </div>

                    <h4 class="ac-form-section">Primary contact</h4>
                    <div class="ac-form-grid">
                        <div class="nks-field ac-form-full">
                            <label class="nks-label">Contact name <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.primary_contact_name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Email <span class="nks-required">*</span></label>
                            <input type="email" wire:model="createForm.primary_contact_email" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Phone <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.primary_contact_phone" class="nks-input">
                        </div>
                    </div>

                    <h4 class="ac-form-section">Commercials</h4>
                    <div class="ac-form-grid">
                        <div class="nks-field">
                            <label class="nks-label">Contract value (R)</label>
                            <input type="number" wire:model="createForm.contract_value" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Payment terms</label>
                            <select wire:model="createForm.payment_terms" class="nks-select">
                                <option value="15 days">15 days</option>
                                <option value="30 days">30 days</option>
                                <option value="45 days">45 days</option>
                                <option value="60 days">60 days</option>
                                <option value="COD">Cash on delivery</option>
                            </select>
                        </div>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeCreate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitCreate" class="nks-btn nks-btn--primary"
                            wire:loading.attr="disabled" wire:target="submitCreate">
                        <i class="fa-solid fa-check" wire:loading.remove wire:target="submitCreate"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="submitCreate"></span>
                        Create client
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Edit modal --}}
    @if ($showEdit && $editId)
        <div class="nks-modal-backdrop" wire:click.self="closeEdit" @keydown.escape.window="$wire.closeEdit()">
            <div class="nks-modal nks-modal--lg" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Edit client</h3>
                    <button type="button" wire:click="closeEdit" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="ac-form-grid">
                        <div class="nks-field ac-form-full">
                            <label class="nks-label">Company name</label>
                            <input type="text" wire:model="editForm.company_name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Registration number</label>
                            <input type="text" wire:model="editForm.registration_number" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">VAT number</label>
                            <input type="text" wire:model="editForm.vat_number" class="nks-input">
                        </div>
                        <div class="nks-field ac-form-full">
                            <label class="nks-label">Billing address</label>
                            <textarea wire:model="editForm.billing_address" class="nks-textarea"></textarea>
                        </div>
                        <div class="nks-field ac-form-full">
                            <label class="nks-label">Primary contact name</label>
                            <input type="text" wire:model="editForm.primary_contact_name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Email</label>
                            <input type="email" wire:model="editForm.primary_contact_email" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Phone</label>
                            <input type="text" wire:model="editForm.primary_contact_phone" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Contract value (R)</label>
                            <input type="number" wire:model="editForm.contract_value" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Payment terms</label>
                            <input type="text" wire:model="editForm.payment_terms" class="nks-input">
                        </div>
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeEdit" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitEdit" class="nks-btn nks-btn--primary"
                            wire:loading.attr="disabled" wire:target="submitEdit">
                        <i class="fa-solid fa-check" wire:loading.remove wire:target="submitEdit"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="submitEdit"></span>
                        Save changes
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Deactivate modal --}}
    @if ($showDeactivate)
        <div class="nks-modal-backdrop" wire:click.self="closeDeactivate" @keydown.escape.window="$wire.closeDeactivate()">
            <div class="nks-modal nks-modal--sm" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Deactivate client</h3>
                    <button type="button" wire:click="closeDeactivate" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <p>Deactivate <strong>{{ $deactivateName }}</strong>? All associated projects will be archived. The client can be reactivated later.</p>
                    <div class="nks-field">
                        <label class="nks-label">Admin password <span class="nks-required">*</span></label>
                        <input type="password" wire:model="deactivatePassword" class="nks-input" autocomplete="current-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeDeactivate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitDeactivate" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-ban"></i>
                        Deactivate
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- Add manager modal --}}
    @if ($showAddManager)
        <div class="nks-modal-backdrop" wire:click.self="closeAddManager" @keydown.escape.window="$wire.closeAddManager()">
            <div class="nks-modal nks-modal--sm" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Add client manager</h3>
                    <button type="button" wire:click="closeAddManager" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Name <span class="nks-required">*</span></label>
                        <input type="text" wire:model="managerForm.name" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Email <span class="nks-required">*</span></label>
                        <input type="email" wire:model="managerForm.email" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Phone</label>
                        <input type="text" wire:model="managerForm.phone" class="nks-input">
                    </div>
                    <label class="nks-checkbox">
                        <input type="checkbox" wire:model="managerForm.is_primary">
                        <span>Set as primary contact</span>
                    </label>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeAddManager" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitAddManager" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-user-plus"></i>
                        Add manager
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>