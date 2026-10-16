<x-guest-layout>
    <x-validation-errors class="mb-4" />

    @if (session('status'))
        <div class="nks-notice" style="margin-bottom: 16px;">
            <i class="fa-solid fa-circle-check"></i>
            <div class="nks-notice-body">
                {{ session('status') }}
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('login') }}" class="nks-auth-form">
        @csrf

        <div class="nks-auth-field">
            <label for="email">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   required autofocus autocomplete="username"
                   placeholder="your.name@nkscapital.co.za">
            @error('email')
                <small class="nks-auth-error">{{ $message }}</small>
            @enderror
        </div>

        <div class="nks-auth-field">
            <label for="password">Password</label>
            <input id="password" type="password" name="password"
                   required autocomplete="current-password"
                   placeholder="Enter your password">
            @error('password')
                <small class="nks-auth-error">{{ $message }}</small>
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
