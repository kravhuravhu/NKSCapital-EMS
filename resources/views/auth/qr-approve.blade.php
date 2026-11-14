<x-guest-layout>
    <header class="nks-auth-heading">
        <h1>Approve desktop login</h1>
        <p>Confirm this desktop login from your phone</p>
    </header>

    @auth
        <div class="nks-auth-hint">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <span>
                You are signed in as <strong>{{ auth()->user()->email }}</strong>.
                Approve the desktop to complete the login.
            </span>
        </div>

        <button type="button" class="nks-auth-submit" onclick="approveDesktop()">
            <span id="qr-approve-label">Approve desktop</span>
            <i class="fa-solid fa-check" aria-hidden="true"></i>
        </button>

        <div id="qr-approve-result" style="margin-top: 16px;"></div>

        <script>
            async function approveDesktop() {
                const btn = document.querySelector('.nks-auth-submit');
                const label = document.getElementById('qr-approve-label');
                const out = document.getElementById('qr-approve-result');

                btn.disabled = true;
                label.textContent = 'Approving...';

                try {
                    const res = await fetch('/api/v1/auth/qr/approve', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'Authorization': 'Bearer ' + @json(\App\Support\Api::token()),
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ challenge: @json($challenge) }),
                    });

                    const data = await res.json();

                    if (data.status === 'success') {
                        out.innerHTML = '<div class="nks-auth-status"><i class="fa-solid fa-circle-check"></i><span>Login approved. You can close this page.</span></div>';
                        btn.style.display = 'none';
                        return;
                    }

                    out.innerHTML = '<div class="nks-auth-status nks-auth-status--error"><i class="fa-solid fa-triangle-exclamation"></i><span>' + (data.message || 'Approval failed.') + '</span></div>';
                } catch (e) {
                    out.innerHTML = '<div class="nks-auth-status nks-auth-status--error"><i class="fa-solid fa-triangle-exclamation"></i><span>Network error. Try again.</span></div>';
                }

                btn.disabled = false;
                label.textContent = 'Approve desktop';
            }
        </script>
    @else
        <div class="nks-auth-hint">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>You must be signed in on this device before you can approve a desktop login.</span>
        </div>

        <a href="{{ route('login') }}" class="nks-auth-submit" style="display: inline-flex; justify-content: center;">
            Sign in on this device
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
    @endauth
</x-guest-layout>