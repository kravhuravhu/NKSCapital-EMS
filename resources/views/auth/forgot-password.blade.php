<x-guest-layout>
    <header class="nks-auth-heading">
        <h1>Forgot password</h1>
        <p>Enter your email and we will send you a reset link</p>
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

    <form method="POST" action="{{ route('password.email') }}" class="nks-auth-form" novalidate
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <div class="nks-auth-field">
            <label for="email">Email</label>
            <div class="nks-auth-input-wrap">
                <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                <input id="email" type="email" name="email" value="{{ old('email') }}"
                       required autofocus autocomplete="username" inputmode="email"
                       placeholder="your.name@nkscapital.co.za">
            </div>
        </div>

        <button type="submit" class="nks-auth-submit" :disabled="submitting">
            <span x-show="!submitting">Send reset link</span>
            <span x-show="submitting" x-cloak style="display: inline-flex; align-items: center; gap: 8px;">
                <span class="nks-btn-spinner" style="display: inline-block;"></span>
                Sending...
            </span>
        </button>
    </form>

    <div class="nks-auth-links">
        <a href="{{ route('login') }}">Back to sign in</a>
    </div>
</x-guest-layout>