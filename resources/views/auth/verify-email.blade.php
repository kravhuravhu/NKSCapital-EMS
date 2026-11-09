<x-guest-layout>
    <header class="nks-auth-heading">
        <h1>Verify your email</h1>
        <p>Check your inbox for the verification link we sent you</p>
    </header>

    <div class="nks-auth-hint">
        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        <span>
            Once you click the link in the email, your account will be fully activated.
            If you did not receive the email, use the button below to resend it.
        </span>
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="nks-auth-status" role="status">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <span>A fresh verification link has been sent to your email.</span>
        </div>
    @endif

    <div class="nks-auth-actions">
        <form method="POST" action="{{ route('verification.send') }}" style="flex: 1;"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <button type="submit" class="nks-auth-submit" :disabled="submitting" style="width: 100%;">
                <span x-show="!submitting">Resend verification email</span>
                <span x-show="submitting" x-cloak style="display: inline-flex; align-items: center; gap: 8px;">
                    <span class="nks-btn-spinner" style="display: inline-block;"></span>
                    Sending...
                </span>
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="nks-auth-alt-btn">Sign out</button>
        </form>
    </div>

    <div class="nks-auth-links">
        <a href="{{ route('profile.show') }}">Edit profile</a>
    </div>
</x-guest-layout>