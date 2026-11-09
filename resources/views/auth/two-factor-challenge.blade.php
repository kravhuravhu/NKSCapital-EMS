<x-guest-layout>
    <div x-data="{ recovery: false }">
        <header class="nks-auth-heading">
            <h1 x-show="!recovery">Two factor authentication</h1>
            <h1 x-show="recovery" x-cloak>Recovery code</h1>
            <p x-show="!recovery">Enter the 6-digit code from your authenticator app</p>
            <p x-show="recovery" x-cloak>Enter one of your emergency recovery codes</p>
        </header>

        @if ($errors->any())
            <div class="nks-auth-status nks-auth-status--error" role="alert">
                <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                <div>
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('two-factor.login') }}" class="nks-auth-form" novalidate
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf

            <div class="nks-auth-field" x-show="!recovery">
                <label for="code">Authentication code</label>
                <input id="code" type="text" name="code" inputmode="numeric" autocomplete="one-time-code"
                       maxlength="6" placeholder="000000" class="nks-auth-code-input"
                       x-ref="code" autofocus>
            </div>

            <div class="nks-auth-field" x-show="recovery" x-cloak>
                <label for="recovery_code">Recovery code</label>
                <div class="nks-auth-input-wrap">
                    <i class="fa-solid fa-key" aria-hidden="true"></i>
                    <input id="recovery_code" type="text" name="recovery_code"
                           autocomplete="one-time-code" x-ref="recovery_code"
                           placeholder="Enter your recovery code">
                </div>
            </div>

            <button type="submit" class="nks-auth-submit" :disabled="submitting">
                <span x-show="!submitting">Verify and continue</span>
                <span x-show="submitting" x-cloak style="display: inline-flex; align-items: center; gap: 8px;">
                    <span class="nks-btn-spinner" style="display: inline-block;"></span>
                    Verifying...
                </span>
            </button>
        </form>

        <div class="nks-auth-links">
            <a href="#" x-show="!recovery"
               @click.prevent="recovery = true; $nextTick(() => { $refs.recovery_code.focus() })">
                Use a recovery code instead
            </a>
            <a href="#" x-show="recovery" x-cloak
               @click.prevent="recovery = false; $nextTick(() => { $refs.code.focus() })">
                Use an authentication code instead
            </a>
        </div>
    </div>
</x-guest-layout>