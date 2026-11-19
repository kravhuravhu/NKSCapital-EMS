<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\HasFlashMessages;
use App\Models\User;
use App\Support\Api;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class Login extends Component
{
    use HasFlashMessages;

    public string $activeTab = 'password';

    // Password flow
    public string $step = 'password';
    public string $email = '';
    public string $password = '';
    public string $code = '';
    public string $challengeToken = '';

    // QR flow: identify, pending, approved, expired, error
    public string $qrState = 'identify';
    public string $qrIdentifier = '';
    public ?array $qrData = null;
    public string $qrChallenge = '';

    public ?string $error = null;
    public ?string $flash = null;
    public ?string $intendedRedirect = null;

    public function mount(): void
    {
        if ($redirect = request('redirect')) {
            $this->intendedRedirect = $redirect;
            Session::put('intended_url', $redirect);
        }
    }

    public function selectTab(string $tab): void
    {
        if (! in_array($tab, ['password', 'qr'], true) || $tab === $this->activeTab) {
            return;
        }

        if ($this->activeTab === 'qr') {
            $this->cancelQr();
        }

        $this->activeTab = $tab;
        $this->error = null;
        $this->flash = null;
    }

    public function startQr(): void
    {
        $this->error = null;
        $this->flash = null;
        $this->qrIdentifier = trim($this->qrIdentifier);

        $this->validate(
            ['qrIdentifier' => 'required|string|max:255'],
            [],
            ['qrIdentifier' => 'email or employee number']
        );

        $this->qrData = null;
        $this->qrChallenge = '';

        $response = Api::post('/auth/qr/start', ['identifier' => $this->qrIdentifier]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to start QR sign in.';
            $this->qrState = in_array((int) $response['status'], [404, 422], true) ? 'identify' : 'error';
            return;
        }

        $data = $response['data'] ?? [];

        if (empty($data['challenge'])) {
            $this->error = 'Unable to start QR sign in.';
            $this->qrState = 'error';
            return;
        }

        $this->qrData = $data;
        $this->qrChallenge = $data['challenge'];
        $this->qrState = 'pending';
    }

    public function regenerateQr(): void
    {
        $this->startQr();
    }

    public function retryQr(): void
    {
        if ($this->qrIdentifier === '') {
            $this->resetQr();
            return;
        }

        $this->startQr();
    }

    public function cancelQr(): void
    {
        if ($this->qrChallenge !== '' && $this->qrState === 'pending') {
            Api::post('/auth/qr/cancel/' . $this->qrChallenge);
        }

        $this->resetQr();
    }

    protected function resetQr(): void
    {
        $this->qrState = 'identify';
        $this->qrIdentifier = '';
        $this->qrData = null;
        $this->qrChallenge = '';
        $this->error = null;
    }

    public function pollQrStatus(): void
    {
        if ($this->qrState !== 'pending' || $this->qrChallenge === '') {
            return;
        }

        $response = Api::get('/auth/qr/status/' . $this->qrChallenge);

        if (! $response['ok']) {
            if (in_array((int) $response['status'], [404, 422], true)) {
                $this->error = $response['message'] ?? 'This QR code is no longer valid.';
                $this->qrState = 'error';
            }

            return;
        }

        $status = $response['data']['status'] ?? 'pending';

        if (in_array($status, ['expired', 'consumed'], true)) {
            $this->qrState = 'expired';
            return;
        }

        if ($status === 'approved') {
            $this->qrState = 'approved';
            $this->completeLogin($response['data']);

            if ($this->error) {
                $this->qrState = 'error';
            }
        }
    }

    public function submitPassword(): void
    {
        $this->error = null;
        $this->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $response = Api::post('/auth/login', [
            'email' => $this->email,
            'password' => $this->password,
        ]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Login failed.';
            return;
        }

        $this->challengeToken = $response['data']['challenge_token'];
        $this->step = 'otp';
        $this->password = '';
    }

    public function submitOtp(): void
    {
        $this->error = null;
        $this->validate(['code' => 'required|string|size:6']);

        $response = Api::post('/auth/verify-otp', [
            'challenge_token' => $this->challengeToken,
            'code' => $this->code,
        ]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Verification failed.';
            return;
        }

        $data = $response['data'] ?? [];

        if (!empty($data['requires_enrollment'])) {
            $user = User::find($data['user_id'] ?? null);

            if (!$user) {
                $this->error = 'Enrollment flow could not identify your account.';
                return;
            }

            Auth::login($user);
            $this->redirect($data['enrollment_url'] ?? route('portal.security'));
            return;
        }

        $this->completeLogin($data);
    }

    public function resendOtp(): void
    {
        $this->error = null;

        $response = Api::post('/auth/resend-otp', ['challenge_token' => $this->challengeToken]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Could not resend code.';
            return;
        }

        $this->challengeToken = $response['data']['challenge_token'];
        $this->code = '';
        $this->dispatch('toast', type: 'success', title: 'New code sent', message: 'Check your email.');
    }

    public function backToPassword(): void
    {
        $this->step = 'password';
        $this->code = '';
        $this->challengeToken = '';
        $this->error = null;
    }

    protected function completeLogin(array $data): void
    {
        $token = $data['token'] ?? null;
        $userId = $data['user']['id'] ?? null;

        if (! $token || ! $userId) {
            $this->error = 'Login response was incomplete.';
            return;
        }

        $user = User::find($userId);

        if (! $user) {
            $this->error = 'User not found.';
            return;
        }

        Session::put('api_token', $token);
        Auth::login($user);

        $redirect = Session::pull('intended_url') ?? route('portal.dashboard');
        $this->redirect($redirect);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}