<div class="page-security">
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
        <x-nks.spinner block label="Loading security settings..." />
    @else
        <div class="sc-layout">
            <div class="sc-left">
                <div class="panel">
                    <h2>Account security</h2>

                    <div class="sc-row">
                        <div class="sc-row-icon"><i class="fa-solid fa-key"></i></div>
                        <div class="sc-row-body">
                            <strong>Change password</strong>
                            <span>Update your password regularly to keep your account safe</span>
                        </div>
                        <button type="button" wire:click="openPassword" class="nks-btn nks-btn--outline nks-btn--sm">
                            Change
                        </button>
                    </div>

                    <div class="sc-row">
                        <div class="sc-row-icon"><i class="fa-solid fa-shield-halved"></i></div>
                        <div class="sc-row-body">
                            <strong>
                                Two factor authentication
                                @if ($passwordInfo['two_factor_enabled'] ?? false)
                                    <x-nks.badge tone="success">Enabled</x-nks.badge>
                                @else
                                    <x-nks.badge tone="muted">Not enabled</x-nks.badge>
                                @endif
                            </strong>
                            <span>Add an extra layer of security to your account</span>
                        </div>
                        <button type="button" wire:click="open2fa" class="nks-btn nks-btn--outline nks-btn--sm">
                            Manage
                        </button>
                    </div>

                    <div class="sc-row">
                        <div class="sc-row-icon"><i class="fa-solid fa-desktop"></i></div>
                        <div class="sc-row-body">
                            <strong>Active sessions</strong>
                            <span>{{ count($sessions) }} active {{ \Illuminate\Support\Str::plural('session', count($sessions)) }}</span>
                        </div>
                        <button type="button" wire:click="$refresh" class="nks-btn nks-btn--outline nks-btn--sm">
                            Refresh
                        </button>
                    </div>
                </div>

                <div class="panel">
                    <h2>Recent activity</h2>

                    @if (count($activity) === 0)
                        <div class="nks-card-empty">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                            <p>No recent security activity.</p>
                        </div>
                    @else
                        <div class="nks-table-wrapper">
                            <table class="nks-table">
                                <thead>
                                    <tr>
                                        <th>Action</th>
                                        <th>IP address</th>
                                        <th>Time</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($activity as $log)
                                        <tr>
                                            <td>{{ $log['action'] }}</td>
                                            <td><span style="font-family: 'Roboto Mono', monospace; font-size: 11px;">{{ $log['ip_address'] ?? '—' }}</span></td>
                                            <td>{{ isset($log['timestamp']) ? \Carbon\Carbon::parse($log['timestamp'])->format('d M Y H:i') : '—' }}</td>
                                            <td>
                                                <x-nks.badge :tone="$log['log_type'] === 'error' ? 'red' : ($log['log_type'] === 'warning' ? 'gold' : 'success')">
                                                    {{ ucfirst($log['log_type'] ?? 'info') }}
                                                </x-nks.badge>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <div class="sc-right">
                <div class="panel">
                    <h2>Password strength</h2>

                    <div class="sc-strength">
                        <div class="sc-strength-head">
                            <strong>{{ $passwordInfo['strength_label'] ?? 'Unknown' }}</strong>
                            <span>{{ $this->strengthPercent() }}%</span>
                        </div>
                        <div class="sc-strength-bar">
                            <div class="sc-strength-fill sc-strength-fill--{{ ($passwordInfo['strength'] ?? 3) >= 4 ? 'success' : (($passwordInfo['strength'] ?? 3) >= 3 ? 'warning' : 'danger') }}"
                                 style="width: {{ $this->strengthPercent() }}%"></div>
                        </div>
                        <p class="sc-strength-hint">
                            Last changed
                            {{ ! empty($passwordInfo['last_changed_fmt']) ? $passwordInfo['last_changed_fmt'] : '—' }}
                            @if (! empty($passwordInfo['days_since']))
                                ({{ (int) $passwordInfo['days_since'] }} days ago)
                            @endif
                        </p>
                    </div>

                    <div class="sc-tips">
                        <strong>Security tips</strong>
                        <ul>
                            <li><i class="fa-solid fa-circle-check"></i> Use a unique password for this account</li>
                            <li><i class="fa-solid fa-circle-check"></i> Enable 2FA for extra security</li>
                            <li><i class="fa-solid fa-circle-check"></i> Sign out of unused sessions</li>
                            <li><i class="fa-solid fa-circle-check"></i> Report any suspicious activity</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if ($showPassword)
        <div class="nks-modal-backdrop" wire:click.self="closePassword" @keydown.escape.window="$wire.closePassword()">
            <div class="nks-modal nks-modal--md" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Change password</h3>
                    <button type="button" wire:click="closePassword" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Current password</label>
                        <input type="password" wire:model="currentPassword" class="nks-input" autocomplete="current-password">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">New password</label>
                        <input type="password" wire:model="newPassword" class="nks-input" autocomplete="new-password">
                        <span class="nks-help">Minimum 8 characters</span>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Confirm new password</label>
                        <input type="password" wire:model="newPasswordConfirm" class="nks-input" autocomplete="new-password">
                    </div>
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="closePassword" class="nks-btn nks-btn--outline">Cancel</button>
                    <button type="button" wire:click="submitPassword" class="nks-btn nks-btn--primary"
                            wire:loading.attr="disabled" wire:target="submitPassword">
                        <i class="fa-solid fa-check" wire:loading.remove wire:target="submitPassword"></i>
                        <span class="nks-btn-spinner" wire:loading wire:target="submitPassword"></span>
                        Change password
                    </button>
                </footer>
            </div>
        </div>
    @endif

    @if ($show2fa)
        <div class="nks-modal-backdrop" wire:click.self="close2fa" @keydown.escape.window="$wire.close2fa()">
            <div class="nks-modal nks-modal--md" role="dialog" aria-modal="true" x-trap>
                <header class="nks-modal-head">
                    <h3>Two factor authentication</h3>
                    <button type="button" wire:click="close2fa" class="nks-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>
                <div class="nks-modal-body">
                    @if ($passwordInfo['two_factor_enabled'] ?? false)
                        <p>Enter your password to disable 2FA.</p>
                        <div class="nks-field">
                            <label class="nks-label">Password</label>
                            <input type="password" wire:model="disablePassword" class="nks-input">
                        </div>
                        <button type="button" wire:click="disable2fa" class="nks-btn nks-btn--danger nks-btn--block">
                            Disable two factor
                        </button>
                    @else
                        @if ($enabling2fa)
                            <x-nks.spinner label="Preparing QR code..." />
                        @elseif (! empty($twoFactorSetup['qr_url']))
                            <p>Scan this QR code with your authenticator app (Google Authenticator, Authy, 1Password).</p>
                            <div class="sc-qr">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=220x220&data={{ urlencode($twoFactorSetup['qr_url']) }}" alt="QR code">
                            </div>
                            <div class="nks-field">
                                <label class="nks-label">Verification code</label>
                                <input type="text" wire:model="twoFactorCode" class="nks-input"
                                       maxlength="6" inputmode="numeric" placeholder="000000">
                            </div>
                            <button type="button" wire:click="verify2fa" class="nks-btn nks-btn--primary nks-btn--block"
                                    wire:loading.attr="disabled" wire:target="verify2fa">
                                <i class="fa-solid fa-check" wire:loading.remove wire:target="verify2fa"></i>
                                <span class="nks-btn-spinner" wire:loading wire:target="verify2fa"></span>
                                Verify and enable
                            </button>

                            @if (! empty($twoFactorSetup['recovery_codes']))
                                <div class="sc-recovery">
                                    <strong>Save your recovery codes</strong>
                                    <div class="sc-recovery-grid">
                                        @foreach ($twoFactorSetup['recovery_codes'] as $code)
                                            <span>{{ $code }}</span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif
                    @endif
                </div>
                <footer class="nks-modal-foot">
                    <button type="button" wire:click="close2fa" class="nks-btn nks-btn--outline">Close</button>
                </footer>
            </div>
        </div>
    @endif
</div>