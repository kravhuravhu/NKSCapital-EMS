<div>
    @if ($error)
        <div class="nks-auth-status nks-auth-status--error" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

    <nav class="nks-auth-tabs" role="tablist">
        <button type="button"
                wire:click="selectTab('password')"
                class="nks-auth-tab {{ $activeTab === 'password' ? 'is-active' : '' }}"
                role="tab"
                aria-selected="{{ $activeTab === 'password' ? 'true' : 'false' }}">
            <i class="fa-solid fa-key" aria-hidden="true"></i>
            Password
        </button>
        <button type="button"
                wire:click="selectTab('qr')"
                class="nks-auth-tab {{ $activeTab === 'qr' ? 'is-active' : '' }}"
                role="tab"
                aria-selected="{{ $activeTab === 'qr' ? 'true' : 'false' }}">
            <i class="fa-solid fa-qrcode" aria-hidden="true"></i>
            QR code
        </button>
    </nav>

    @if ($activeTab === 'password')
        @if ($step === 'password')
            <header class="nks-auth-heading">
                <h1>Sign in</h1>
                <p>Enter your email and password to receive a verification code</p>
            </header>

            <form wire:submit="submitPassword" class="nks-auth-form" novalidate>
                <div class="nks-auth-field">
                    <label for="email">Email</label>
                    <div class="nks-auth-input-wrap">
                        <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                        <input id="email"
                               type="email"
                               wire:model="email"
                               required
                               autofocus
                               autocomplete="username"
                               inputmode="email"
                               placeholder="your.name@nkscapital.co.za">
                    </div>
                    @error('email') <small class="nks-auth-error">{{ $message }}</small> @enderror
                </div>

                <div class="nks-auth-field">
                    <div class="nks-auth-field-head">
                        <label for="password">Password</label>
                        @if (Route::has('password.request'))
                            <a href="{{ route('password.request') }}" class="nks-auth-forgot">Forgot password?</a>
                        @endif
                    </div>
                    <div class="nks-auth-input-wrap">
                        <i class="fa-solid fa-lock" aria-hidden="true"></i>
                        <input id="password"
                               type="password"
                               wire:model="password"
                               required
                               autocomplete="current-password"
                               placeholder="Enter your password">
                    </div>
                    @error('password') <small class="nks-auth-error">{{ $message }}</small> @enderror
                </div>

                <button type="submit" class="nks-auth-submit" wire:loading.attr="disabled" wire:target="submitPassword">

                    <span wire:loading.remove wire:target="submitPassword">
                        Continue
                    </span>

                    <span wire:loading wire:target="submitPassword" class="nks-auth-loading">
                        <span class="nks-btn-spinner"></span>
                        Verifying...
                    </span>

                    <i class="fa-solid fa-arrow-right" wire:loading.remove wire:target="submitPassword" aria-hidden="true"></i>
                </button>
            </form>
        @else
            <header class="nks-auth-heading">
                <h1>Enter your code</h1>
                <p>We sent a 6-digit code to <strong>{{ $email }}</strong></p>
            </header>

            <form wire:submit="submitOtp" class="nks-auth-form" novalidate>
                <div class="nks-auth-field">
                    <label for="code">Verification code</label>
                    <input id="code"
                           type="text"
                           wire:model="code"
                           inputmode="numeric"
                           maxlength="6"
                           autocomplete="one-time-code"
                           placeholder="000000"
                           class="nks-auth-code-input"
                           autofocus>
                    @error('code') <small class="nks-auth-error">{{ $message }}</small> @enderror
                </div>

                <button type="submit" class="nks-auth-submit" wire:loading.attr="disabled" wire:target="submitOtp">
                    <span wire:loading.remove wire:target="submitOtp">
                        Sign in
                    </span>

                    <span wire:loading wire:target="submitOtp"
                        class="nks-auth-loading">
                        <span class="nks-btn-spinner"></span>
                        Signing in...
                    </span>

                    <i class="fa-solid fa-arrow-right" wire:loading.remove wire:target="submitOtp" aria-hidden="true"></i>
                </button>
            </form>

            <div class="nks-auth-links">
                <a href="#" wire:click.prevent="resendOtp" wire:loading.attr="disabled" wire:target="resendOtp">
                    <span wire:loading.remove wire:target="resendOtp">Resend code</span>
                    <span wire:loading wire:target="resendOtp">Sending...</span>
                </a>
                <a href="#" wire:click.prevent="backToPassword">Use a different email</a>
            </div>
        @endif
    @else
        <header class="nks-auth-heading">
            <h1>Sign in with your phone</h1>
            <p>Scan the QR code with your phone camera. On the phone, sign in as usual and approve the desktop.</p>
        </header>

        <div class="nks-auth-qr" wire:poll.2s="pollQrStatus">
            @if ($qrLoading)
                <div class="nks-auth-qr-loading">
                    <span class="nks-btn-spinner" style="display: inline-block; width: 20px; height: 20px;"></span>
                    <span>Generating QR code...</span>
                </div>
            @elseif ($qrExpired)
                <div class="nks-auth-qr-expired">
                    <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                    <p>This QR code has expired.</p>
                    <button type="button" wire:click="refreshQr" class="nks-btn nks-btn--primary nks-btn--sm">
                        <i class="fa-solid fa-rotate-right"></i>
                        Generate a new QR code
                    </button>
                </div>
            @elseif ($qrData)
                <div class="nks-auth-qr-image-wrap">
                    @if (! empty($qrData['qr_image']))
                        <img src="{{ $qrData['qr_image'] }}" alt="QR code" class="nks-auth-qr-image">
                    @else
                        <p class="nks-auth-qr-fallback">Open on your phone: <br><code>{{ $qrData['qr_url'] }}</code></p>
                    @endif
                </div>

                <p class="nks-auth-qr-status">
                    @if ($qrStatus === 'pending')
                        <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                        Waiting for your phone...
                    @elseif ($qrStatus === 'approved')
                        <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                        Approved. Signing in...
                    @endif
                </p>

                <p class="nks-auth-qr-hint">
                    Expires in {{ (int) ceil(now()->diffInSeconds(\Carbon\Carbon::parse($qrData['expires_at']), false)) }} seconds.
                </p>
            @endif
        </div>

        <div class="nks-auth-links">
            <a href="#" wire:click.prevent="selectTab('password')">Back to password sign in</a>
        </div>
    @endif

    <p style="margin-top: 24px; text-align: center; font-size: 12px; color: var(--nks-muted);">
        Need access? Contact your administrator.
    </p>
</div>