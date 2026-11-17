<x-guest-layout>
    @auth
        <header class="nks-auth-heading">
            <h1>Approve desktop sign in</h1>
            <p>You are signed in as <strong>{{ auth()->user()->email }}</strong>. Approve the sign-in request from your desktop.</p>
        </header>

        <div class="nks-auth-hint">
            <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
            <span>Only approve if you started this sign-in on a device you control.</span>
        </div>

        <div style="display: flex; gap: 10px;">
            <button type="button" id="qr-approve-btn" class="nks-auth-submit" onclick="approveDesktop()">
                <span id="qr-approve-label">Approve desktop</span>
                <i class="fa-solid fa-check" aria-hidden="true"></i>
            </button>
        </div>

        <div class="nks-auth-links">
            <a href="#" onclick="cancelChallenge(event)">Cancel this request</a>
        </div>

        <div id="qr-approve-result" style="margin-top: 16px;"></div>

        <script>
            const token = @json(\App\Support\Api::token());
            const challenge = @json($challenge);

            async function approveDesktop() {
                const btn = document.getElementById('qr-approve-btn');
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
                            'Authorization': 'Bearer ' + token,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({ challenge: challenge }),
                    });

                    const data = await res.json();

                    if (data.status === 'success') {
                        out.innerHTML = '<div class="nks-auth-status"><i class="fa-solid fa-circle-check"></i><span>Sign in approved. You can close this page.</span></div>';
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

            async function cancelChallenge(event) {
                event.preventDefault();

                try {
                    await fetch('/api/v1/auth/qr/cancel/' + challenge, {
                        method: 'POST',
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                        credentials: 'same-origin',
                    });
                } catch (e) {
                    /* silently ignore */
                }

                document.getElementById('qr-approve-result').innerHTML =
                    '<div class="nks-auth-status"><i class="fa-solid fa-circle-info"></i><span>Request cancelled.</span></div>';
                document.getElementById('qr-approve-btn').style.display = 'none';
            }
        </script>
    @else
        <header class="nks-auth-heading">
            <h1>Sign in to approve</h1>
            <p>You need to be signed in on this device before you can approve a desktop sign in.</p>
        </header>

        <div class="nks-auth-hint">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
            <span>Sign in with your password and verification code, then you will be returned to this page.</span>
        </div>

        <a href="{{ route('login', ['redirect' => request()->url()]) }}" class="nks-auth-submit" style="display: inline-flex; justify-content: center; text-decoration: none;">
            Sign in on this device
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </a>
    @endauth
</x-guest-layout>