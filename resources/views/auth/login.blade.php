<x-guest-layout>
    <x-validation-errors class="mb-4" />
    @if (session('status'))
        <div class="nks-notice" role="status" aria-live="polite">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <div class="nks-notice-body">{{ session('status') }}</div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="nks-auth-form" novalidate>
        @csrf

        <div class="nks-auth-field">
            <label for="email">Email</label>
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
            @error('email')
                <small class="nks-auth-error" id="email-error" role="alert">{{ $message }}</small>
            @enderror
        </div>

        <div class="nks-auth-field">
            <label for="password">Password</label>
            <input id="password"
                   type="password"
                   name="password"
                   required
                   autocomplete="current-password"
                   placeholder="Enter your password"
                   @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
            @error('password')
                <small class="nks-auth-error" id="password-error" role="alert">{{ $message }}</small>
            @enderror
        </div>

        <div class="nks-auth-row">
            <label class="nks-auth-checkbox">
                <input type="checkbox" name="remember">
                <span>Remember me</span>
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="nks-auth-link">
                    Forgot your password?
                </a>
            @endif
        </div>

        <button type="submit" class="nks-auth-submit">
            Log in
        </button>
    </form>
</x-guest-layout>
