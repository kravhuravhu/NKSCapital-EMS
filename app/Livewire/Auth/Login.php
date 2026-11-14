<?php

namespace App\Livewire\Auth;

use App\Models\User;
use App\Support\Api;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class Login extends Component
{
    public string $step = 'password';
    public string $email = '';
    public string $password = '';
    public string $code = '';
    public string $challengeToken = '';
    public bool $passkeyAvailable = false;
    public ?string $error = null;

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

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Login failed.';
            return;
        }

        $this->challengeToken = $response['data']['challenge_token'];
        $this->passkeyAvailable = (bool) ($response['data']['passkey_available'] ?? false);
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

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Verification failed.';
            return;
        }

        $token = $response['data']['token'] ?? null;
        $userId = $response['data']['user']['id'] ?? null;

        if (!$token || !$userId) {
            $this->error = 'Login response was incomplete.';
            return;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error = 'User not found.';
            return;
        }

        Session::put('api_token', $token);
        Auth::login($user);

        $this->redirect(route('portal.dashboard'));
    }

    public function resendOtp(): void
    {
        $this->error = null;

        $response = Api::post('/auth/resend-otp', ['challenge_token' => $this->challengeToken]);

        if (!$response['ok']) {
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
        $this->passkeyAvailable = false;
        $this->error = null;
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}