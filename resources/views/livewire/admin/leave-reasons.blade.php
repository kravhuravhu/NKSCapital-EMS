<div class="page-admin-leave-reasons">
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

    <div class="panel">
        <div class="page-toolbar">
            <div>
                <h2>Leave Reasons</h2>
                <p>Dropdown options shown to employees when applying for each leave type</p>
            </div>
            <div class="page-toolbar-actions">
                <select wire:model.live="filterType" class="nks-select">
                    <option value="">All types</option>
                    <option value="annual">Annual</option>
                    <option value="sick">Sick</option>
                    <option value="family">Family</option>
                    <option value="unpaid">Unpaid</option>
                    <option value="study">Study</option>
                    <option value="maternity">Maternity</option>
                    <option value="paternity">Paternity</option>
                </select>
                <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary">
                    <i class="fa-solid fa-plus"></i>
                    Add reason
                </button>
            </div>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading reasons..." />
        @else
            <div class="nks-table-wrapper">
                <table class="nks-table">
                    <thead>
                        <tr>
                            <th>Leave type</th>
                            <th>Label</th>
                            <th>Order</th>
                            <th>Active</th>
                            <th>Other</th>
                            <th style="width: 160px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->filteredReasons as $reason)
                            <tr wire:key="lr-{{ $reason['id'] }}">
                                <td>{{ ucfirst($reason['leave_type']) }}</td>
                                <td><strong>{{ $reason['label'] }}</strong></td>
                                <td>{{ $reason['sort_order'] }}</td>
                                <td>
                                    <x-nks.badge :tone="!empty($reason['is_active']) ? 'success' : 'muted'">
                                        {{ !empty($reason['is_active']) ? 'Yes' : 'No' }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    <x-nks.badge :tone="!empty($reason['is_other']) ? 'gold' : 'muted'">
                                        {{ !empty($reason['is_other']) ? 'Yes' : 'No' }}
                                    </x-nks.badge>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 4px;">
                                        <button type="button" wire:click="openEdit({{ $reason['id'] }})"
                                                class="nks-btn nks-btn--outline nks-btn--sm">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                        <button type="button" wire:click="openDelete({{ $reason['id'] }})"
                                                class="nks-btn nks-btn--danger nks-btn--sm">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6">
                                    <div class="nks-card-empty">
                                        <i class="fa-solid fa-list"></i>
                                        <p>No reasons yet. Click Add reason to start.</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @if ($showForm)
        <div class="nks-modal-backdrop" wire:click.self="closeForm">
            <div class="nks-modal nks-modal--md">
                <header class="nks-modal-head">
                    <h3>{{ $editingId ? 'Edit reason' : 'Add reason' }}</h3>
                    <button type="button" wire:click="closeForm" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Leave type</label>
                        <select wire:model="form.leave_type" class="nks-select" @if($editingId) disabled @endif>
                            <option value="annual">Annual</option>
                            <option value="sick">Sick</option>
                            <option value="family">Family</option>
                            <option value="unpaid">Unpaid</option>
                            <option value="study">Study</option>
                            <option value="maternity">Maternity</option>
                            <option value="paternity">Paternity</option>
                        </select>
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Label</label>
                        <input type="text" wire:model="form.label" class="nks-input" maxlength="255">
                    </div>

                    <div class="nks-field">
                        <label class="nks-label">Sort order</label>
                        <input type="number" wire:model="form.sort_order" class="nks-input" min="0">
                    </div>

                    <label class="nks-checkbox">
                        <input type="checkbox" wire:model="form.is_active">
                        <span>Active</span>
                    </label>

                    <label class="nks-checkbox">
                        <input type="checkbox" wire:model="form.is_other">
                        <span>Is the "Other" option that reveals a text field</span>
                    </label>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeForm" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="save" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Save
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showDelete)
        <div class="nks-modal-backdrop" wire:click.self="closeDelete">
            <div class="nks-modal nks-modal--sm">
                <header class="nks-modal-head">
                    <h3>Delete reason</h3>
                    <button type="button" wire:click="closeDelete" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <p>Are you sure you want to delete this reason? Existing leave requests are not affected.</p>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeDelete" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="destroy" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-trash-can"></i>
                        Delete
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>