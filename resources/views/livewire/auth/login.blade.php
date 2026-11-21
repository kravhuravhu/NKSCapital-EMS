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
        <div x-data="nksPasskeyLogin">
            <header class="nks-auth-heading">
                <h1>Sign in with your phone</h1>
                <p>Click the button, scan the QR code your browser shows with your phone, then confirm with your fingerprint or face. No password or code needed.</p>
            </header>

            <div class="nks-auth-form">
                <button type="button"
                        class="nks-auth-submit"
                        @click="run()"
                        :disabled="busy || !supported">
                    <span x-text="label"></span>
                    <span class="nks-btn-spinner" x-show="busy" x-cloak></span>
                    <i class="fa-solid fa-qrcode" x-show="!busy" aria-hidden="true"></i>
                </button>

                <p class="nks-auth-qr-hint" x-show="supported && phase !== 'success'">
                    Keep Bluetooth on, on both your computer and your phone, so they can find each other.
                </p>

                <small class="nks-auth-error" x-show="!supported" x-cloak>
                    This browser does not support passkeys. Use the Password tab instead.
                </small>
            </div>

            <div class="nks-auth-links">
                <a href="#" wire:click.prevent="selectTab('password')">Back to password sign in</a>
            </div>
        </div>
    @endif

    <p style="margin-top: 24px; text-align: center; font-size: 12px; color: var(--nks-muted);">
        Need access? Contact your administrator.
    </p>
</div>