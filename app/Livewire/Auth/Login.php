<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\Api;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class Login extends Component
{
    public string $activeTab = 'password';

    // Password flow
    public string $step = 'password';
    public string $email = '';
    public string $password = '';
    public string $code = '';
    public string $challengeToken = '';

    // QR flow
    public bool $qrLoading = false;
    public ?array $qrData = null;
    public string $qrChallenge = '';
    public string $qrStatus = 'pending';
    public bool $qrExpired = false;

    public ?string $error = null;
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

        if ($this->activeTab === 'qr' && $this->qrChallenge) {
            Api::post('/auth/qr/cancel/' . $this->qrChallenge);
            $this->qrData = null;
            $this->qrChallenge = '';
            $this->qrStatus = 'pending';
            $this->qrExpired = false;
        }

        $this->activeTab = $tab;
        $this->error = null;

        if ($tab === 'qr' && ! $this->qrData) {
            $this->initializeQr();
        }
    }

    public function initializeQr(): void
    {
        $this->qrLoading = true;
        $this->qrData = null;
        $this->qrStatus = 'pending';
        $this->qrExpired = false;
        $this->error = null;

        $response = Api::post('/auth/qr/anonymous');

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to start QR sign in.';
            $this->qrLoading = false;
            return;
        }

        $this->qrData = $response['data'];
        $this->qrChallenge = $response['data']['challenge'];
        $this->qrLoading = false;
    }

    public function refreshQr(): void
    {
        $this->initializeQr();
    }

    public function pollQrStatus(): void
    {
        if (! $this->qrChallenge || in_array($this->qrStatus, ['approved', 'consumed', 'expired'], true)) {
            return;
        }

        $response = Api::get('/auth/qr/status/' . $this->qrChallenge);

        if (! $response['ok']) {
            return;
        }

        $status = $response['data']['status'] ?? 'pending';

        if ($status === 'expired') {
            $this->qrStatus = 'expired';
            $this->qrExpired = true;
            return;
        }

        if ($status === 'approved') {
            $this->qrStatus = 'approved';
            $this->completeLogin($response['data']);
            return;
        }

        $this->qrStatus = $status;
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