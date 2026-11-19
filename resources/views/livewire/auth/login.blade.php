<div>
    <x-nks.notice-stack :error="$error" :success="$flash" />

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
                    <span>Continue</span>
                    <span class="nks-btn-spinner" wire:loading wire:target="submitPassword"></span>
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
                    <span>Sign in</span>
                    <span class="nks-btn-spinner" wire:loading wire:target="submitOtp"></span>
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
        @if ($qrState === 'identify')
            <header class="nks-auth-heading">
                <h1>Sign in with your phone</h1>
                <p>Enter your email or employee number. You will get a QR code to scan with the phone you are already signed in on.</p>
            </header>

            <form wire:submit="startQr" class="nks-auth-form" novalidate>
                <div class="nks-auth-field">
                    <label for="qr-identifier">Email or employee number</label>
                    <div class="nks-auth-input-wrap">
                        <i class="fa-solid fa-user" aria-hidden="true"></i>
                        <input id="qr-identifier"
                               type="text"
                               wire:model="qrIdentifier"
                               required
                               autofocus
                               autocomplete="username"
                               placeholder="Email or employee number">
                    </div>
                    @error('qrIdentifier') <small class="nks-auth-error">{{ $message }}</small> @enderror
                </div>

                <button type="submit" class="nks-auth-submit" wire:loading.attr="disabled" wire:target="startQr">
                    <span>Get QR code</span>
                    <span class="nks-btn-spinner" wire:loading wire:target="startQr"></span>
                    <i class="fa-solid fa-qrcode" wire:loading.remove wire:target="startQr" aria-hidden="true"></i>
                </button>
            </form>
        @elseif ($qrState === 'pending' && $qrData)
            <header class="nks-auth-heading">
                <h1>Scan to sign in</h1>
                <p>Scan the QR code with your phone camera. On the phone, confirm with your password to approve this sign in.</p>
            </header>

            <div class="nks-auth-qr" wire:poll.3s="pollQrStatus">
                <div class="nks-auth-qr-image-wrap">
                    @if (! empty($qrData['qr_image']))
                        <img src="{{ $qrData['qr_image'] }}" alt="QR code" class="nks-auth-qr-image">
                    @else
                        <p class="nks-auth-qr-fallback">Open on your phone: <br><code>{{ $qrData['qr_url'] }}</code></p>
                    @endif
                </div>

                <p class="nks-auth-qr-status">
                    <i class="fa-solid fa-hourglass-half" aria-hidden="true"></i>
                    Waiting for your phone...
                </p>

                <p class="nks-auth-qr-hint">
                    Expires in {{ max(0, (int) ceil(now()->diffInSeconds(\Carbon\Carbon::parse($qrData['expires_at']), false))) }} seconds.
                </p>
            </div>

            <div class="nks-auth-links">
                <a href="#" wire:click.prevent="cancelQr" wire:loading.attr="disabled" wire:target="cancelQr">
                    <span wire:loading.remove wire:target="cancelQr">Use a different identity</span>
                    <span wire:loading wire:target="cancelQr">Cancelling...</span>
                </a>
            </div>
        @elseif ($qrState === 'approved')
            <header class="nks-auth-heading">
                <h1>Approved</h1>
                <p>Your phone approved this sign in.</p>
            </header>

            <div class="nks-auth-qr">
                <p class="nks-auth-qr-status">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    Approved. Signing in...
                </p>
            </div>
        @elseif ($qrState === 'expired')
            <header class="nks-auth-heading">
                <h1>QR code expired</h1>
                <p>The code was not approved in time.</p>
            </header>

            <div class="nks-auth-qr-expired">
                <i class="fa-solid fa-clock-rotate-left" aria-hidden="true"></i>
                <p>This QR code has expired.</p>
                <button type="button" wire:click="regenerateQr" wire:loading.attr="disabled" wire:target="regenerateQr" class="nks-btn nks-btn--primary nks-btn--sm">
                    <span class="nks-btn-spinner" wire:loading wire:target="regenerateQr"></span>
                    <i class="fa-solid fa-rotate-right" wire:loading.remove wire:target="regenerateQr" aria-hidden="true"></i>
                    Generate a new QR code
                </button>
            </div>

            <div class="nks-auth-links">
                <a href="#" wire:click.prevent="cancelQr">Use a different identity</a>
            </div>
        @else
            <header class="nks-auth-heading">
                <h1>QR sign in failed</h1>
                <p>Something went wrong while setting up QR sign in.</p>
            </header>

            <div class="nks-auth-qr-expired">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <p>We could not complete QR sign in.</p>
                <button type="button" wire:click="retryQr" wire:loading.attr="disabled" wire:target="retryQr" class="nks-btn nks-btn--primary nks-btn--sm">
                    <span class="nks-btn-spinner" wire:loading wire:target="retryQr"></span>
                    <i class="fa-solid fa-rotate-right" wire:loading.remove wire:target="retryQr" aria-hidden="true"></i>
                    Try again
                </button>
            </div>

            <div class="nks-auth-links">
                <a href="#" wire:click.prevent="cancelQr">Use a different identity</a>
            </div>
        @endif

        <div class="nks-auth-links">
            <a href="#" wire:click.prevent="selectTab('password')">Back to password sign in</a>
        </div>
    @endif

    <p style="margin-top: 24px; text-align: center; font-size: 12px; color: var(--nks-muted);">
        Need access? Contact your administrator.
    </p>
</div>
