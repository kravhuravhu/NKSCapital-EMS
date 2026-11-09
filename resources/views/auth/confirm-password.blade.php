<x-guest-layout>
    <header class="nks-auth-heading">
        <h1>Confirm password</h1>
        <p>For your security, please confirm your password to continue</p>
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

    <form method="POST" action="{{ route('password.confirm') }}" class="nks-auth-form" novalidate
          x-data="{ submitting: false }" @submit="submitting = true">
        @csrf

        <div class="nks-auth-field">
            <label for="password">Password</label>
            <div class="nks-auth-input-wrap">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <input id="password" type="password" name="password" required autofocus
                       autocomplete="current-password" placeholder="Enter your password">
            </div>
        </div>

        <button type="submit" class="nks-auth-submit" :disabled="submitting">
            <span x-show="!submitting">Confirm</span>
            <span x-show="submitting" x-cloak style="display: inline-flex; align-items: center; gap: 8px;">
                <span class="nks-btn-spinner" style="display: inline-block;"></span>
                Confirming...
            </span>
        </button>
    </form>

    <div class="nks-auth-links">
        <a href="{{ route('login') }}">Back to sign in</a>
    </div>
</x-guest-layout>