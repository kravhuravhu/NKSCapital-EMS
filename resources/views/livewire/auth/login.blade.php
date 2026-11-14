<div>
    @if ($error)
        <div class="nks-auth-status nks-auth-status--error" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>{{ $error }}</span>
        </div>
    @endif

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
                    <input id="email" type="email" wire:model="email" required autofocus autocomplete="username" inputmode="email" placeholder="your.name@nkscapital.co.za">
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

        <p style="margin-top: 24px; text-align: center; font-size: 12px; color: var(--nks-muted);">
            Need access? Contact your administrator.
        </p>
    @elseif ($step === 'otp')
        <header class="nks-auth-heading">
            <h1>Enter your code</h1>
            <p>We sent a 6-digit code to <strong>{{ $email }}</strong></p>
        </header>

        <form wire:submit="submitOtp" class="nks-auth-form" novalidate>
            <div class="nks-auth-field">
                <label for="code">Verification code</label>
                <input id="code" type="text" wire:model="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" placeholder="000000" class="nks-auth-code-input" autofocus>
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

        @if ($passkeyAvailable)
            <div style="margin-top: 20px; text-align: center; font-size: 12px; color: var(--nks-muted);">
                Prefer biometrics? Sign in from your phone by scanning the QR code shown on the desktop or by opening this app on your phone.
            </div>
        @endif
    @endif
</div>