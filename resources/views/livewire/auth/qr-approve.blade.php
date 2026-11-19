<div>
    <x-nks.notice-stack :error="$error" :success="$flash" />

    @if ($state === 'ready')
        <header class="nks-auth-heading">
            <h1>Approve desktop sign in</h1>
            <p>A desktop is asking to sign in as <strong>{{ $targetName }}</strong>. Confirm with your password to approve it.</p>
        </header>

        <div class="nks-auth-hint">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <span>Only approve if you started this sign in on a device you control.</span>
        </div>

        <form wire:submit="approve" class="nks-auth-form" novalidate>
            <div class="nks-auth-field">
                <label for="qr-approve-password">Password</label>
                <div class="nks-auth-input-wrap">
                    <i class="fa-solid fa-lock" aria-hidden="true"></i>
                    <input id="qr-approve-password"
                           type="password"
                           wire:model="password"
                           required
                           autocomplete="current-password"
                           placeholder="Enter your password">
                </div>
                @error('password') <small class="nks-auth-error">{{ $message }}</small> @enderror
            </div>

            <button type="submit" class="nks-auth-submit" wire:loading.attr="disabled" wire:target="approve">
                <span>Approve desktop</span>
                <span class="nks-btn-spinner" wire:loading wire:target="approve"></span>
                <i class="fa-solid fa-check" wire:loading.remove wire:target="approve" aria-hidden="true"></i>
            </button>
        </form>

        <div class="nks-auth-links">
            <a href="#" wire:click.prevent="cancel" wire:loading.attr="disabled" wire:target="cancel">
                <span wire:loading.remove wire:target="cancel">Cancel this request</span>
                <span wire:loading wire:target="cancel">Cancelling...</span>
            </a>
        </div>
    @elseif ($state === 'approved')
        <header class="nks-auth-heading">
            <h1>Desktop approved</h1>
            <p>Your desktop is signing in now. You can close this page.</p>
        </header>

        <div class="nks-auth-status">
            <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
            <span>Sign in approved.</span>
        </div>
    @elseif ($state === 'cancelled')
        <header class="nks-auth-heading">
            <h1>Request cancelled</h1>
            <p>The desktop sign in was cancelled. Nothing was approved.</p>
        </header>

        <div class="nks-auth-status">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>Request cancelled.</span>
        </div>
    @elseif ($state === 'mismatch')
        <header class="nks-auth-heading">
            <h1>Wrong account</h1>
            <p>This sign in request was started for a different account than the one you are signed in with on this device.</p>
        </header>

        <div class="nks-auth-status nks-auth-status--error" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>You cannot approve a sign in for another account.</span>
        </div>

        <div class="nks-auth-links">
            <a href="{{ route('portal.dashboard') }}">Back to the portal</a>
        </div>
    @else
        <header class="nks-auth-heading">
            <h1>Request unavailable</h1>
            <p>This sign in request has expired, was cancelled, or is no longer valid. Start a new one from your desktop.</p>
        </header>

        <div class="nks-auth-status nks-auth-status--error" role="alert">
            <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
            <span>This request can no longer be approved.</span>
        </div>

        <div class="nks-auth-links">
            <a href="{{ route('portal.dashboard') }}">Back to the portal</a>
        </div>
    @endif
</div>