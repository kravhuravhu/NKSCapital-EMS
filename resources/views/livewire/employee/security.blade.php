<div class="page-security">

    @if ($error)
        <div class="sc-flash sc-flash--error">
            <i class="fa-solid fa-circle-exclamation"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    @if ($flash)
        <div class="sc-flash sc-flash--success">
            <i class="fa-solid fa-circle-check"></i>
            <span>{{ $flash }}</span>
        </div>
    @endif

    <div class="sc-layout">

        <div class="sc-left">

            {{-- tabs --}}
            <nav class="nks-tabs sc-tabs" aria-label="Profile views">
                <a href="{{ route('portal.' . auth()->user()->role . '.profile') }}" class="nks-tab">
                    Profile
                </a>
                <a href="{{ route('portal.' . auth()->user()->role . '.security') }}" class="nks-tab is-active">
                    Security
                </a>
            </nav>

            {{-- account security --}}
            <div class="panel">
                <h2>Account Security</h2>
                <p class="sc-subtitle">Manage and monitor your account security settings</p>

                <div class="sc-row">
                    <div class="sc-row-icon">
                        <i class="fa-solid fa-key"></i>
                    </div>
                    <div class="sc-row-text">
                        <div class="sc-row-title">Change Password</div>
                        <div class="sc-row-desc">Update your password regularly to keep your account secure</div>
                    </div>
                    <button type="button" wire:click="openPassword" class="nks-btn nks-btn--outline nks-btn--sm">
                        <i class="fa-solid fa-pen-to-square"></i>
                        Change Password
                    </button>
                </div>

                <div class="sc-row">
                    <div class="sc-row-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div class="sc-row-text">
                        <div class="sc-row-title">
                            Two-Factor Authentication
                            @if ($passwordInfo['two_factor_enabled'] ?? false)
                                <x-nks.badge tone="success">Enabled</x-nks.badge>
                            @endif
                        </div>
                        <div class="sc-row-desc">Add an extra layer of security to your account</div>
                    </div>
                    <button type="button" wire:click="open2fa" class="nks-btn nks-btn--outline nks-btn--sm">
                        <i class="fa-solid fa-pen-to-square"></i>
                        Manage 2FA
                    </button>
                </div>

                <div class="sc-row">
                    <div class="sc-row-icon">
                        <i class="fa-solid fa-desktop"></i>
                    </div>
                    <div class="sc-row-text">
                        <div class="sc-row-title">Active Sessions</div>
                        <div class="sc-row-desc">{{ count($sessions) }} active login sessions</div>
                    </div>
                    <button type="button" wire:click="$refresh" class="nks-btn nks-btn--outline nks-btn--sm">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        Refresh
                    </button>
                </div>
            </div>

            {{-- activity table --}}
            <div class="panel">
                <h2>Recent Activity</h2>

                @if (count($activity) === 0)
                    <div class="sc-empty-row">
                        <i class="fa-solid fa-circle-info"></i>
                        No recent activity recorded.
                    </div>
                @else
                    <div class="sc-table-wrapper">
                        <table class="sc-table">
                            <thead>
                                <tr>
                                    <th>Activity</th>
                                    <th>IP Address</th>
                                    <th>Date/Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($activity as $log)
                                    <tr>
                                        <td>
                                            <div class="sc-activity">
                                                <span class="sc-activity-dot {{ $log['severity'] === 'error' ? 'is-error' : '' }}"></span>
                                                {{ $log['action'] }}
                                            </div>
                                        </td>
                                        <td>{{ $log['ip_address'] ?? '—' }}</td>
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

            {{-- bottom notice --}}
            <div class="sc-notice">
                <i class="fa-solid fa-shield-halved"></i>
                <div>
                    <strong>Keep Your Account Secure</strong>
                    Never share your password or verification codes with anyone. NKS Capital will never ask for your password.
                </div>
            </div>
        </div>

        <div class="sc-right">
            <div class="panel sc-password-info">
                <h2>Password Information</h2>

                <div class="sc-pwd-date">
                    <i class="fa-solid fa-calendar-days"></i>
                    <div>
                        <div class="sc-pwd-label">Your password was last changed on</div>
                        <div class="sc-pwd-value">{{ $passwordInfo['last_changed_fmt'] ?? '—' }}</div>
                    </div>
                </div>

                <div class="sc-pwd-strength">
                    <div class="sc-pwd-strength-head">
                        <strong>Password Strength</strong>
                        <x-nks.badge tone="success">{{ $passwordInfo['strength_label'] ?? 'Strong' }}</x-nks.badge>
                    </div>

                    <div class="sc-strength-bars">
                        @for ($i = 1; $i <= 5; $i++)
                            <i class="{{ $i <= ($passwordInfo['strength'] ?? 5) ? 'is-filled' : '' }}"></i>
                        @endfor
                    </div>

                    <div class="sc-pwd-hint">Use a strong password to keep your account safe.</div>
                </div>

                <div class="sc-tips">
                    <div class="sc-tips-title">
                        <i class="fa-solid fa-shield-halved"></i>
                        Security Tips
                    </div>
                    <ul>
                        <li><i class="fa-solid fa-circle-check"></i> Unique password</li>
                        <li><i class="fa-solid fa-circle-check"></i> Enable 2FA for extra security</li>
                        <li><i class="fa-solid fa-circle-check"></i> Log out of unused devices</li>
                        <li><i class="fa-solid fa-circle-check"></i> Report any suspicious activity</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    {{-- change password modal --}}
    @if ($showPassword)
        <div class="sc-modal-backdrop" wire:click.self="closePassword">
            <div class="sc-modal">
                <header class="sc-modal-head">
                    <h3>Change Password</h3>
                    <button type="button" wire:click="closePassword" class="sc-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>

                <div class="sc-modal-body">
                    <div class="nks-field">
                        <label class="nks-label">Current Password</label>
                        <input type="password" wire:model="currentPassword" class="nks-input" autocomplete="current-password">
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">New Password</label>
                        <input type="password" wire:model="newPassword" class="nks-input" autocomplete="new-password">
                        <span class="nks-help">Minimum 8 characters</span>
                    </div>
                    <div class="nks-field">
                        <label class="nks-label">Confirm New Password</label>
                        <input type="password" wire:model="newPasswordConfirm" class="nks-input" autocomplete="new-password">
                    </div>
                </div>

                <footer class="sc-modal-foot">
                    <button type="button" wire:click="closePassword" class="nks-btn nks-btn--outline">
                        Cancel
                    </button>
                    <button type="button" wire:click="submitPassword" class="nks-btn nks-btn--primary">
                        <i class="fa-solid fa-check"></i>
                        Change Password
                    </button>
                </footer>
            </div>
        </div>
    @endif

    {{-- 2FA modal --}}
    @if ($show2fa)
        <div class="sc-modal-backdrop" wire:click.self="close2fa">
            <div class="sc-modal sc-modal--lg">
                <header class="sc-modal-head">
                    <h3>Two-Factor Authentication</h3>
                    <button type="button" wire:click="close2fa" class="sc-modal-close">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </header>

                <div class="sc-modal-body">
                    @if ($passwordInfo['two_factor_enabled'] ?? false)
                        <p class="sc-2fa-enabled-msg">
                            <i class="fa-solid fa-circle-check" style="color: var(--nks-success);"></i>
                            2FA is enabled on your account.
                        </p>
                        <div class="nks-field">
                            <label class="nks-label">Enter your password to disable 2FA</label>
                            <input type="password" wire:model="disablePassword" class="nks-input">
                        </div>
                        <button type="button" wire:click="disable2fa" class="nks-btn nks-btn--danger nks-btn--block">
                            <i class="fa-solid fa-shield-slash"></i>
                            Disable Two-Factor
                        </button>
                    @else
                        <p>Scan this QR code with an authenticator app (Google Authenticator, Authy, 1Password):</p>

                        @if (!empty($twoFactorSetup['qr_url']))
                            <div class="sc-2fa-qr" style="text-align:center;margin:16px 0;">
                                <img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($twoFactorSetup['qr_url']) }}"
                                     alt="QR Code" width="200" height="200">
                            </div>
                        @endif

                        @if (!empty($twoFactorSetup['recovery_codes']))
                            <div class="sc-recovery-codes">
                                <strong>Save these recovery codes somewhere safe:</strong>
                                <div class="sc-recovery-grid">
                                    @foreach ($twoFactorSetup['recovery_codes'] as $code)
                                        <span class="sc-recovery-code">{{ $code }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>

                <footer class="sc-modal-foot">
                    <button type="button" wire:click="close2fa" class="nks-btn nks-btn--outline">
                        Close
                    </button>
                </footer>
            </div>
        </div>
    @endif
</div>