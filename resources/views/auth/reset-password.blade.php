<x-guest-layout>
    <header class="nks-auth-heading">
        <h1>Reset password</h1>
        <p>Choose a new password for your account</p>
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

    <form method="POST" action="{{ route('password.update') }}" class="nks-auth-form" novalidate
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div class="nks-auth-field">
            <label for="email">Email</label>
            <div class="nks-auth-input-wrap">
                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}"
                       required autocomplete="username" placeholder="your.name@nkscapital.co.za">
            </div>
        </div>

        <div class="nks-auth-field">
            <label for="password">New password</label>
            <div class="nks-auth-input-wrap">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <input id="password" type="password" name="password" required autocomplete="new-password"
                       placeholder="At least 8 characters">
            </div>
        </div>

        <div class="nks-auth-field">
            <label for="password_confirmation">Confirm new password</label>
            <div class="nks-auth-input-wrap">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <input id="password_confirmation" type="password" name="password_confirmation"
                       required autocomplete="new-password" placeholder="Re-enter your password">
            </div>
        </div>

        <button type="submit" class="nks-auth-submit" :disabled="submitting">
            <span x-show="!submitting">Reset password</span>
            <span x-show="submitting" x-cloak style="display: inline-flex; align-items: center; gap: 8px;">
                <span class="nks-btn-spinner" style="display: inline-block;"></span>
                Resetting...
            </span>
        </button>
    </form>

    <div class="nks-auth-links">
        <a href="{{ route('login') }}">Back to sign in</a>
    </div>
</x-guest-layout>