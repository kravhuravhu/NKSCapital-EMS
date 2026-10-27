<div class="page-clients">
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

    <div class="panel cl-panel">
        <div class="cl-header">
            <i class="fa-solid fa-building cl-header-icon"></i>
            <div>
                <h2>Clients</h2>
                <p>Manage client companies, contacts, and contracts</p>
            </div>
            <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary cl-create-btn">
                <i class="fa-solid fa-plus"></i>
                Create Client
            </button>
        </div>

        <div class="cl-filters">
            <div class="nks-field cl-filter-search">
                <label class="nks-label">Search</label>
                <input type="text" wire:model="search" wire:keydown.enter="applyFilters"
                       class="nks-input" placeholder="Company name, reg number, or contact">
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
            <div class="cl-filter-actions">
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
            <x-nks.spinner block label="Loading clients…" />
        @elseif (count($clients) === 0)
            <div class="cl-empty">
                <i class="fa-solid fa-building"></i>
                <strong>No clients found</strong>
                <span>Adjust filters or create your first client.</span>
            </div>
        @else
            <div class="cl-table-wrapper">
                <table class="cl-table">
                    <thead>
                        <tr>
                            <th>Company</th>
                            <th>Primary Contact</th>
                            <th>Contract Value</th>
                            <th>Projects</th>
                            <th>Status</th>
                            <th style="width: 180px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clients as $client)
                            <tr wire:key="cl-{{ $client['id'] }}">
                                <td>
                                    <div class="cl-name">{{ $client['company_name'] ?? '—' }}</div>
                                    <div class="cl-meta">{{ $client['registration_number'] ?? '' }}</div>
                                </td>
                                <td>
                                    <div class="cl-name">{{ $client['primary_contact_name'] ?? '—' }}</div>
                                    <div class="cl-meta">{{ $client['primary_contact_email'] ?? '' }}</div>
                                </td>
                                <td>
                                    @if (!empty($client['contract_value']))
                                        R {{ number_format((float) $client['contract_value'], 2) }}
                                    @else
                                        <span class="cl-muted">—</span>
                                    @endif
                                </td>
                                <td>{{ $client['projects_count'] ?? 0 }}</td>
                                <td>
                                    <x-nks.badge :tone="\App\Livewire\Admin\Clients::statusTone($client['status'] ?? '')">
                                        {{ ucfirst($client['status'] ?? '—') }}
                                    </x-nks.badge>
                                    @unless (!empty($client['is_active']))
                                        <div class="cl-meta" style="margin-top: 2px;">Deactivated</div>
                                    @endunless
                                </td>
                                <td>
                                    <div class="cl-row-actions">
                                        <button type="button" wire:click="openView({{ $client['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-eye"></i>
                                            View
                                        </button>
                                        <button type="button" wire:click="openEdit({{ $client['id'] }})"
                                                class="nks-btn nks-btn--primary nks-btn--sm">
                                            <i class="fa-solid fa-pen"></i>
                                            Edit
                                        </button>
                                        @if (!empty($client['is_active']))
                                            <button type="button" wire:click="openDeactivate({{ $client['id'] }})"
                                                    class="cl-icon-btn cl-icon-btn--danger"
                                                    title="Deactivate">
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
                <div class="cl-pagination">
                    <button type="button" wire:click="gotoPage({{ max(1, $pagination['current_page'] - 1) }})"
                            class="nks-btn nks-btn--outline nks-btn--sm"
                            @if ($pagination['current_page'] <= 1) disabled @endif>
                        <i class="fa-solid fa-chevron-left"></i>
                        Previous
                    </button>
                    <span class="cl-page-info">
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

    @if ($showView && $viewClient)
        <div class="cl-modal-backdrop" wire:click.self="closeView">
            <div class="cl-modal cl-modal--lg">
                <header class="cl-modal-head">
                    <div>
                        <h3>{{ $viewClient['company_name'] ?? 'Client' }}</h3>
                        <span class="cl-modal-sub">{{ $viewClient['registration_number'] ?? '' }}</span>
                    </div>
                    <button type="button" wire:click="closeView" class="cl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="cl-modal-body">
                    <div class="cl-detail-grid">
                        <div><span class="cl-detail-label">Status</span><span class="cl-detail-value">
                            <x-nks.badge :tone="\App\Livewire\Admin\Clients::statusTone($viewClient['status'] ?? '')">
                                {{ ucfirst($viewClient['status'] ?? '—') }}
                            </x-nks.badge>
                        </span></div>
                        <div><span class="cl-detail-label">VAT Number</span><span class="cl-detail-value">{{ $viewClient['vat_number'] ?? '—' }}</span></div>
                        <div><span class="cl-detail-label">Contract Value</span><span class="cl-detail-value">
                            @if (!empty($viewClient['contract_value']))
                                R {{ number_format((float) $viewClient['contract_value'], 2) }}
                            @else — @endif
                        </span></div>
                        <div><span class="cl-detail-label">Payment Terms</span><span class="cl-detail-value">{{ $viewClient['payment_terms'] ?? '—' }}</span></div>
                        <div><span class="cl-detail-label">Projects</span><span class="cl-detail-value">{{ $viewClient['projects_count'] ?? 0 }}</span></div>
                        <div><span class="cl-detail-label">Assigned Employees</span><span class="cl-detail-value">{{ $viewClient['users_count'] ?? 0 }}</span></div>
                    </div>

                    <div class="cl-detail-section">
                        <h4 class="cl-detail-title">Primary Contact</h4>
                        <div class="cl-contact-card">
                            <div class="cl-contact-name">{{ $viewClient['primary_contact_name'] ?? '—' }}</div>
                            <div class="cl-contact-line"><i class="fa-solid fa-envelope"></i> {{ $viewClient['primary_contact_email'] ?? '—' }}</div>
                            <div class="cl-contact-line"><i class="fa-solid fa-phone"></i> {{ $viewClient['primary_contact_phone'] ?? '—' }}</div>
                        </div>
                    </div>

                    @if (!empty($viewClient['billing_address']))
                        <div class="cl-detail-section">
                            <h4 class="cl-detail-title">Billing Address</h4>
                            <p class="cl-detail-text">{{ $viewClient['billing_address'] }}</p>
                        </div>
                    @endif

                    <div class="cl-detail-section">
                        <div class="cl-detail-title-row">
                            <h4 class="cl-detail-title">Client Managers</h4>
                            <button type="button"
                                    wire:click="openAddManager({{ $viewClient['id'] }})"
                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                <i class="fa-solid fa-user-plus"></i>
                                Add Manager
                            </button>
                        </div>
                        @if (empty($viewClient['managers']))
                            <div class="cl-inline-empty">
                                <i class="fa-solid fa-circle-info"></i>
                                No client managers on file.
                            </div>
                        @else
                            <div class="cl-manager-list">
                                @foreach ($viewClient['managers'] as $mgr)
                                    <div class="cl-manager-row" wire:key="mgr-{{ $mgr['id'] }}">
                                        <div class="cl-manager-avatar">
                                            <i class="fa-solid fa-user-tie"></i>
                                        </div>
                                        <div class="cl-manager-body">
                                            <div class="cl-manager-name">
                                                {{ $mgr['name'] ?? '—' }}
                                                @if (!empty($mgr['is_primary']))
                                                    <x-nks.badge tone="gold">Primary</x-nks.badge>
                                                @endif
                                            </div>
                                            <div class="cl-manager-meta">{{ $mgr['email'] ?? '—' }}</div>
                                            @if (!empty($mgr['phone']))
                                                <div class="cl-manager-meta">{{ $mgr['phone'] }}</div>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    @if (!empty($viewClient['projects']))
                        <div class="cl-detail-section">
                            <h4 class="cl-detail-title">Projects ({{ count($viewClient['projects']) }})</h4>
                            <div class="cl-inline-table-wrapper">
                                <table class="cl-inline-table">
                                    <thead>
                                        <tr>
                                            <th>Project</th>
                                            <th>Code</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($viewClient['projects'] as $project)
                                            <tr>
                                                <td>{{ $project['name'] ?? '—' }}</td>
                                                <td><span class="cl-mono">{{ $project['project_code'] ?? '—' }}</span></td>
                                                <td>
                                                    <x-nks.badge :tone="($project['status'] ?? '') === 'active' ? 'success' : 'muted'">
                                                        {{ ucfirst($project['status'] ?? '—') }}
                                                    </x-nks.badge>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                </div>
                <footer class="cl-modal-foot">
                    <button type="button" wire:click="closeView" class="nks-btn nks-btn--outline">Close</button>
                    <button type="button" wire:click="openEdit({{ $viewClient['id'] }})" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-pen"></i>
                        Edit Client
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showCreate)
        <div class="cl-modal-backdrop" wire:click.self="closeCreate">
            <div class="cl-modal cl-modal--lg">
                <header class="cl-modal-head">
                    <h3>Create Client</h3>
                    <button type="button" wire:click="closeCreate" class="cl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="cl-modal-body">
                    <h4 class="cl-form-section">Company Details</h4>
                    <div class="cl-form-grid">
                        <div class="nks-field cl-form-full">
                            <label class="nks-label">Company Name <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.company_name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Registration Number <span class="nks-required">*</span></label>
                            <input type="text" wire:model="createForm.registration_number" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">VAT Number</label>
                            <input type="text" wire:model="createForm.vat_number" class="nks-input">
                        </div>
                        <div class="nks-field cl-form-full">
                            <label class="nks-label">Billing Address</label>
                            <textarea wire:model="createForm.billing_address" class="nks-textarea"></textarea>
                        </div>
                    </div>

                    <h4 class="cl-form-section">Primary Contact</h4>
                    <div class="cl-form-grid">
                        <div class="nks-field cl-form-full">
                            <label class="nks-label">Contact Name <span class="nks-required">*</span></label>
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

                    <h4 class="cl-form-section">Commercials</h4>
                    <div class="cl-form-grid">
                        <div class="nks-field">
                            <label class="nks-label">Contract Value (R)</label>
                            <input type="number" wire:model="createForm.contract_value" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Payment Terms</label>
                            <select wire:model="createForm.payment_terms" class="nks-select">
                                <option value="15 days">15 days</option>
                                <option value="30 days">30 days</option>
                                <option value="45 days">45 days</option>
                                <option value="60 days">60 days</option>
                                <option value="COD">Cash on Delivery</option>
                            </select>
                        </div>
                    </div>
                </div>
                <footer class="cl-modal-foot">
                    <button type="button" wire:click="closeCreate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitCreate" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Create Client
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showEdit && $editId)
        <div class="cl-modal-backdrop" wire:click.self="closeEdit">
            <div class="cl-modal cl-modal--lg">
                <header class="cl-modal-head">
                    <h3>Edit Client</h3>
                    <button type="button" wire:click="closeEdit" class="cl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="cl-modal-body">
                    <div class="cl-form-grid">
                        <div class="nks-field cl-form-full">
                            <label class="nks-label">Company Name</label>
                            <input type="text" wire:model="editForm.company_name" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Registration Number</label>
                            <input type="text" wire:model="editForm.registration_number" class="nks-input">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">VAT Number</label>
                            <input type="text" wire:model="editForm.vat_number" class="nks-input">
                        </div>
                        <div class="nks-field cl-form-full">
                            <label class="nks-label">Billing Address</label>
                            <textarea wire:model="editForm.billing_address" class="nks-textarea"></textarea>
                        </div>
                        <div class="nks-field cl-form-full">
                            <label class="nks-label">Primary Contact Name</label>
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
                            <label class="nks-label">Contract Value (R)</label>
                            <input type="number" wire:model="editForm.contract_value" class="nks-input" min="0" step="0.01">
                        </div>
                        <div class="nks-field">
                            <label class="nks-label">Payment Terms</label>
                            <input type="text" wire:model="editForm.payment_terms" class="nks-input">
                        </div>
                    </div>
                </div>
                <footer class="cl-modal-foot">
                    <button type="button" wire:click="closeEdit" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitEdit" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Save Changes
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showDeactivate)
        <div class="cl-modal-backdrop" wire:click.self="closeDeactivate">
            <div class="cl-modal">
                <header class="cl-modal-head">
                    <h3>Deactivate Client</h3>
                    <button type="button" wire:click="closeDeactivate" class="cl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="cl-modal-body">
                    <p class="cl-delete-text">
                        Deactivate <strong>{{ $deactivateName }}</strong>?
                        All associated projects will be archived. The client and their projects can be reactivated later.
                    </p>
                    <div class="nks-field">
                        <label class="nks-label">
                            Enter your admin password to confirm <span class="nks-required">*</span>
                        </label>
                        <input type="password" wire:model="deactivatePassword" class="nks-input"
                               autocomplete="current-password">
                    </div>
                </div>
                <footer class="cl-modal-foot">
                    <button type="button" wire:click="closeDeactivate" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitDeactivate" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-ban"></i>
                        Deactivate Client
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showAddManager)
        <div class="cl-modal-backdrop" wire:click.self="closeAddManager">
            <div class="cl-modal">
                <header class="cl-modal-head">
                    <h3>Add Client Manager</h3>
                    <button type="button" wire:click="closeAddManager" class="cl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="cl-modal-body">
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
                    <label class="cl-checkbox">
                        <input type="checkbox" wire:model="managerForm.is_primary">
                        <span>Set as primary contact for this client</span>
                    </label>
                </div>
                <footer class="cl-modal-foot">
                    <button type="button" wire:click="closeAddManager" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitAddManager" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-user-plus"></i>
                        Add Manager
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>