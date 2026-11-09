<x-guest-layout>
    <header class="nks-auth-heading">
        <h1>Sign in</h1>
        <p>Enter your credentials to continue</p>
    </header>

    @if (session('status'))
        <div class="nks-auth-status" role="status">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <span>{{ session('status') }}</span>
        </div>
    @endif

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

    <form method="POST" action="{{ route('login') }}" class="nks-auth-form" novalidate
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <div class="nks-auth-field">
            <label for="email">Email</label>
            <div class="nks-auth-input-wrap">
                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                <input id="email"
                       type="email"
                       name="email"
                       value="{{ old('email') }}"
                       required
                       autofocus
                       autocomplete="username"
                       inputmode="email"
                       placeholder="your.name@nkscapital.co.za"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
            </div>
            @error('email')
                <small class="nks-auth-error" id="email-error" role="alert">{{ $message }}</small>
            @enderror
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
                       name="password"
                       required
                       autocomplete="current-password"
                       placeholder="Enter your password"
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            </div>
            @error('password')
                <small class="nks-auth-error" id="password-error" role="alert">{{ $message }}</small>
            @enderror
        </div>

        <label class="nks-auth-checkbox">
            <input type="checkbox" name="remember">
            <span>Keep me signed in</span>
        </label>

        <button type="submit" class="nks-auth-submit" :disabled="submitting">
            <span x-show="!submitting">Sign in</span>
            <span x-show="submitting" x-cloak style="display: inline-flex; align-items: center; gap: 8px;">
                <span class="nks-btn-spinner" style="display: inline-block;"></span>
                Signing in...
            </span>
            <i class="fa-solid fa-arrow-right" x-show="!submitting" aria-hidden="true"></i>
        </button>
    </form>

    <p style="margin-top: 24px; text-align: center; font-size: 12px; color: var(--nks-muted);">
        Need access? Contact your administrator.
    </p>
</x-guest-layout>