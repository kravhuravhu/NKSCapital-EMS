<div class="page-profile">
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
        <x-nks.spinner block label="Loading profile..." />
    @else
        <div class="pf-layout">
            <div class="pf-left">
                <div class="panel pf-hero">
                    <div class="pf-photo-block">
                        <div class="pf-avatar">
                            @if (! empty($profile['profile_photo_url']))
                                <img src="{{ $profile['profile_photo_url'] }}" alt="">
                            @else
                                <i class="fa-solid fa-user" aria-hidden="true"></i>
                            @endif
                        </div>
                        <label class="nks-btn nks-btn--outline nks-btn--sm pf-photo-btn">
                            <i class="fa-solid fa-cloud-arrow-up"></i>
                            Change photo
                            <input type="file" wire:model="photo" accept="image/*" hidden>
                        </label>
                        @if ($photo)
                            <button type="button" wire:click="uploadPhoto"
                                    class="nks-btn nks-btn--primary nks-btn--sm"
                                    wire:loading.attr="disabled" wire:target="uploadPhoto">
                                <i class="fa-solid fa-check" wire:loading.remove wire:target="uploadPhoto"></i>
                                <span class="nks-btn-spinner" wire:loading wire:target="uploadPhoto"></span>
                                Save photo
                            </button>
                        @endif
                        <span class="pf-photo-hint">JPG, PNG up to 5 MB</span>
                    </div>

                    <div class="pf-hero-details">
                        <h1>{{ $profile['full_name'] ?? '—' }}</h1>
                        <p class="pf-hero-role">
                            {{ $profile['position'] ?? '—' }}
                            @if (! empty($profile['department']))
                                &middot; {{ $profile['department'] }}
                            @endif
                        </p>

                        <div class="pf-hero-chips">
                            <span class="pf-chip">
                                <i class="fa-solid fa-hashtag" aria-hidden="true"></i>
                                {{ $profile['employee_number'] ?? '—' }}
                            </span>
                            @if (! empty($profile['employee_type']))
                                <span class="pf-chip">{{ strtoupper($profile['employee_type']) }}</span>
                            @endif
                            @if (! empty($profile['service_type']))
                                <span class="pf-chip">{{ ucfirst($profile['service_type']) }}</span>
                            @endif
                            <span class="pf-chip">
                                <i class="fa-solid fa-clock" aria-hidden="true"></i>
                                {{ $this->tenure() }} with the company
                            </span>
                        </div>

                        <div class="pf-hero-actions">
                            <button type="button" wire:click="openEdit" class="nks-btn nks-btn--outline nks-btn--sm">
                                <i class="fa-solid fa-pen"></i>
                                Edit contact info
                            </button>
                            <a href="{{ route('portal.employee.security') }}" wire:navigate
                               class="nks-btn nks-btn--ghost nks-btn--sm">
                                <i class="fa-solid fa-shield-halved"></i>
                                Security settings
                            </a>
                        </div>
                    </div>
                </div>

                <div class="panel">
                    <h2>Contact details</h2>
                    <dl class="pf-details">
                        <div>
                            <dt>Work email</dt>
                            <dd>{{ $profile['email'] ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt>Phone</dt>
                            <dd>{{ $profile['phone'] ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt>Home address</dt>
                            <dd>{{ $profile['address'] ?? 'Not set' }}</dd>
                        </div>
                        <div>
                            <dt>Emergency contact</dt>
                            <dd>
                                @if (! empty($profile['emergency_contact_name']))
                                    {{ $profile['emergency_contact_name'] }}
                                    @if (! empty($profile['emergency_contact_phone']))
                                        &middot; {{ $profile['emergency_contact_phone'] }}
                                    @endif
                                @else
                                    Not set
                                @endif
                            </dd>
                        </div>
                    </dl>
                </div>

                <div class="nks-notice" style="background: var(--nks-elevated);">
                    <i class="fa-solid fa-circle-info" style="color: var(--nks-gold-deep);"></i>
                    <div class="nks-notice-body">
                        Employment details and contract are managed by HR. Contact your manager if anything is incorrect.
                    </div>
                </div>
            </div>

            <div class="pf-right">
                <div class="panel">
                    <h2>Employment</h2>
                    <dl class="pf-details pf-details--compact">
                        <div><dt>Manager</dt><dd>{{ $profile['manager'] ?? '—' }}</dd></div>
                        <div><dt>Client</dt><dd>{{ $profile['client'] ?? '—' }}</dd></div>
                        <div><dt>Project</dt><dd>{{ $profile['project'] ?? '—' }}</dd></div>
                        <div>
                            <dt>Hire date</dt>
                            <dd>
                                {{ ! empty($profile['hire_date']) ? \Carbon\Carbon::parse($profile['hire_date'])->format('d M Y') : '—' }}
                            </dd>
                        </div>
                        <div>
                            <dt>Status</dt>
                            <dd>
                                <x-nks.badge :tone="! empty($profile['is_active']) ? 'success' : 'muted'">
                                    {{ ! empty($profile['is_active']) ? 'Active' : 'Inactive' }}
                                </x-nks.badge>
                            </dd>
                        </div>
                    </dl>
                </div>

                @if (! empty($contract))
                    <div class="panel">
                        <h2>Contract</h2>
                        <dl class="pf-details pf-details--compact">
                            <div><dt>Type</dt><dd>{{ $contract['type'] }}</dd></div>
                            <div><dt>Effective</dt><dd>{{ $contract['start'] }}</dd></div>
                            <div><dt>Expires</dt><dd>{{ $contract['end'] }}</dd></div>
                            <div><dt>Notice</dt><dd>{{ $contract['notice'] }}</dd></div>
                            <div><dt>Salary</dt><dd>{{ $contract['salary'] }}</dd></div>
                            <div>
                                <dt>Status</dt>
                                <dd>
                                    <x-nks-badge :tone="$contract['status'] === 'active' ? 'success' : 'muted'">
                                        {{ ucfirst($contract['status']) }}
                                    </x-nks-badge>
                                </dd>
                            </div>
                        </dl>
                    </div>
                @endif

                <a href="{{ route('portal.employee.leave') }}" wire:navigate class="panel pf-quick-link">
                    <div class="pf-quick-link-icon"><i class="fa-solid fa-umbrella-beach"></i></div>
                    <div class="pf-quick-link-body">
                        <strong>Leave balance</strong>
                        <span>
                            {{ (float) ($profile['leave_balance_annual'] ?? 0) }} days annual remaining
                        </span>
                    </div>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>

                <a href="{{ route('portal.employee.documents') }}" wire:navigate class="panel pf-quick-link">
                    <div class="pf-quick-link-icon"><i class="fa-solid fa-file-lines"></i></div>
                    <div class="pf-quick-link-body">
                        <strong>My documents</strong>
                        <span>Contracts, payslips, policies</span>
                    </div>
                    <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
            </div>
        </div>
    @endif

    @if ($showEdit)
        <div class="nks-modal-backdrop" wire:click.self="closeEdit" @keydown.escape.window="$wire.closeEdit()">
            <div class="nks-modal nks-modal--md" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Edit contact information</h3>
                    <button type="button" wire:click="closeEdit" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Phone</label>
                        <input type="text" wire:model="editForm.phone" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Home address</label>
                        <textarea wire:model="editForm.address" class="nks-textarea"></textarea>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Emergency contact name</label>
                        <input type="text" wire:model="editForm.emergency_contact_name" class="nks-input">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Emergency contact phone</label>
                        <input type="text" wire:model="editForm.emergency_contact_phone" class="nks-input">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closeEdit" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="saveEdit" class="nks-btn nks-btn--primary"
                            wire:loading.attr="disabled" wire:target="saveEdit">
                        <i class="fa-solid fa-check" wire:loading.remove wire:target="saveEdit"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="saveEdit"></span>
                        Save
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>