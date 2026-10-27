<div class="page-roles">
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

    <div class="panel rl-panel">
        <div class="rl-header">
            <i class="fa-solid fa-shield-halved rl-header-icon"></i>
            <div>
                <h2>Roles & Permissions</h2>
                <p>Manage roles and the granular permissions attached to each</p>
            </div>
            <button type="button" wire:click="openCreate" class="nks-btn nks-btn--primary rl-create-btn">
                <i class="fa-solid fa-plus"></i>
                Create Role
            </button>
        </div>

        @if ($loading)
            <x-nks.spinner block label="Loading roles…" />
        @elseif (count($roles) === 0)
            <div class="rl-empty">
                <i class="fa-solid fa-shield-halved"></i>
                <strong>No roles configured</strong>
                <span>Create your first role to start assigning permissions.</span>
            </div>
        @else
            <div class="rl-grid">
                @foreach ($roles as $role)
                    <article class="rl-card" wire:key="rl-{{ $role['id'] }}">
                        <header class="rl-card-head">
                            <div class="rl-card-icon">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <div class="rl-card-title">
                                <h3>{{ $role['name'] ?? '—' }}</h3>
                                <span class="rl-card-guard">{{ $role['guard_name'] ?? 'web' }}</span>
                            </div>
                            @if ($this->isSystemRole($role['name'] ?? ''))
                                <span class="rl-card-system">
                                    <i class="fa-solid fa-lock"></i>
                                </span>
                            @endif
                        </header>

                        <div class="rl-card-body">
                            <div class="rl-perms-head">
                                <span class="rl-perms-label">
                                    Permissions ({{ count($role['permissions'] ?? []) }})
                                </span>
                            </div>
                            <div class="rl-perm-chips">
                                @forelse (array_slice($role['permissions'] ?? [], 0, 5) as $p)
                                    <span class="rl-chip">{{ $p['name'] ?? '—' }}</span>
                                @empty
                                    <span class="rl-muted">No permissions assigned</span>
                                @endforelse
                                @if (count($role['permissions'] ?? []) > 5)
                                    <span class="rl-chip rl-chip--more">
                                        +{{ count($role['permissions']) - 5 }} more
                                    </span>
                                @endif
                            </div>
                        </div>

                        <footer class="rl-card-foot">
                            <button type="button" wire:click="openPermissions({{ $role['id'] }})"
                                    class="nks-btn nks-btn--outline nks-btn--sm">
                                <i class="fa-solid fa-key"></i>
                                Permissions
                            </button>
                            <div class="rl-card-actions">
                                <button type="button" wire:click="openEdit({{ $role['id'] }})"
                                        class="rl-icon-btn" title="Edit">
                                    <i class="fa-solid fa-pen"></i>
                                </button>
                                @unless ($this->isSystemRole($role['name'] ?? ''))
                                    <button type="button"
                                            wire:click="openDelete({{ $role['id'] }}, '{{ addslashes($role['name'] ?? '') }}')"
                                            class="rl-icon-btn rl-icon-btn--danger"
                                            title="Delete">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                @endunless
                            </div>
                        </footer>
                    </article>
                @endforeach
            </div>
        @endif
    </div>

    @if ($showCreate)
        <div class="rl-modal-backdrop" wire:click.self="closeCreate">
            <div class="rl-modal">
                <header class="rl-modal-head">
                    <h3>Create Role</h3>
                    <button type="button" wire:click="closeCreate" class="rl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="rl-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Role Name <span class="nks-required">*</span></label>
                        <input type="text" wire:model="createForm.name" class="nks-input"
                               placeholder="e.g. finance, hr_manager">
                        <span class="nks-help">Lowercase, no spaces. Used by the backend for role checks.</span>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Guard</label>
                        <select wire:model="createForm.guard_name" class="nks-select">
                            <option value="web">web (default)</option>
                            <option value="api">api</option>
                        </select>
                    </div>
                </div>
                <footer class="rl-modal-foot">
                    <button type="button" wire:click="closeCreate" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitCreate" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Create Role
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showEdit && $editId)
        <div class="rl-modal-backdrop" wire:click.self="closeEdit">
            <div class="rl-modal">
                <header class="rl-modal-head">
                    <h3>Edit Role</h3>
                    <button type="button" wire:click="closeEdit" class="rl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="rl-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Role Name <span class="nks-required">*</span></label>
                        <input type="text" wire:model="editForm.name" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Guard</label>
                        <select wire:model="editForm.guard_name" class="nks-select">
                            <option value="web">web</option>
                            <option value="api">api</option>
                        </select>
                    </div>
                </div>
                <footer class="rl-modal-foot">
                    <button type="button" wire:click="closeEdit" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitEdit" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Save Changes
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showDelete)
        <div class="rl-modal-backdrop" wire:click.self="closeDelete">
            <div class="rl-modal">
                <header class="rl-modal-head">
                    <h3>Delete Role</h3>
                    <button type="button" wire:click="closeDelete" class="rl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="rl-modal-body">
                    <p class="rl-delete-text">
                        Delete the <strong>{{ $deleteName }}</strong> role?
                        Any users assigned to this role will lose its permissions immediately.
                    </p>
                </div>
                <footer class="rl-modal-foot">
                    <button type="button" wire:click="closeDelete" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitDelete" class="nks-btn nks-btn--danger">
                        <i class="fa-solid fa-trash-can"></i>
                        Delete Role
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($showPermissions && $permRole)
        <div class="rl-modal-backdrop" wire:click.self="closePermissions">
            <div class="rl-modal rl-modal--lg">
                <header class="rl-modal-head">
                    <div>
                        <h3>Permissions for {{ $permRole['name'] }}</h3>
                        <span class="rl-modal-sub">{{ count($permSelected) }} selected</span>
                    </div>
                    <button type="button" wire:click="closePermissions" class="rl-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="rl-modal-body">
                    <div class="rl-perm-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text"
                               wire:model.live.debounce.300ms="permSearch"
                               class="rl-perm-search-input"
                               placeholder="Filter permissions">
                    </div>

                    @if (count($this->filteredPermissions) === 0)
                        <div class="rl-perm-empty">
                            <i class="fa-solid fa-circle-info"></i>
                            No permissions match your search.
                        </div>
                    @else
                        <div class="rl-perm-list">
                            @foreach ($this->filteredPermissions as $perm)
                                <label class="rl-perm-item" wire:key="perm-{{ $perm }}">
                                    <input type="checkbox"
                                           value="{{ $perm }}"
                                           @checked(in_array($perm, $permSelected, true))
                                           wire:click="togglePermission('{{ $perm }}')">
                                    <span class="rl-perm-name">{{ $perm }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>
                <footer class="rl-modal-foot">
                    <button type="button" wire:click="closePermissions" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitPermissions" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Save Permissions
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>