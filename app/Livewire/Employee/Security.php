<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Livewire\Component;

class Security extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public array $passwordInfo = [];
    public array $sessions = [];
    public array $activity = [];

    public bool $showPassword = false;
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirm = '';

    public bool $show2fa = false;
    public array $twoFactorSetup = [];
    public string $twoFactorCode = '';
    public string $disablePassword = '';
    public bool $enabling2fa = false;
    public bool $verifying2fa = false;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $info = Api::get('/security/password-info');
        if ($info['ok']) {
            $this->passwordInfo = $info['data'] ?? [];
        }

        $sessions = Api::get('/security/sessions');
        if ($sessions['ok']) {
            $this->sessions = $sessions['data']['sessions'] ?? [];
        }

        $activity = Api::get('/security/activity');
        if ($activity['ok']) {
            $this->activity = $activity['data']['activity'] ?? [];
        }

        $this->loading = false;
    }

    public function openPassword(): void
    {
        $this->currentPassword = '';
        $this->newPassword = '';
        $this->newPasswordConfirm = '';
        $this->showPassword = true;
    }

    public function closePassword(): void
    {
        $this->showPassword = false;
    }

    public function submitPassword(): void
    {
        $this->error = null;

        if (strlen($this->newPassword) < 8) {
            $this->error = 'New password must be at least 8 characters.';
            return;
        }

        if ($this->newPassword !== $this->newPasswordConfirm) {
            $this->error = 'Passwords do not match.';
            return;
        }

        $response = Api::post('/security/password/change', [
            'current_password' => $this->currentPassword,
            'new_password' => $this->newPassword,
            'new_password_confirmation' => $this->newPasswordConfirm,
        ]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Password change failed.');
            return;
        }

        $this->flash = $response['message'] ?? 'Password changed.';
        $this->closePassword();
        $this->load();
    }

    public function open2fa(): void
    {
        $this->twoFactorSetup = [];
        $this->disablePassword = '';
        $this->twoFactorCode = '';
        $this->show2fa = true;

        if (! ($this->passwordInfo['two_factor_enabled'] ?? false)) {
            $this->enabling2fa = true;

            $response = Api::post('/security/2fa/enable');

            if ($response['ok']) {
                $this->twoFactorSetup = $response['data'] ?? [];
            } else {
                $this->error = $response['message'] ?? 'Unable to start 2FA setup.';
            }

            $this->enabling2fa = false;
        }
    }

    public function close2fa(): void
    {
        $this->show2fa = false;
        $this->twoFactorSetup = [];
        $this->twoFactorCode = '';
        $this->disablePassword = '';
    }

    public function verify2fa(): void
    {
        $this->error = null;

        if (strlen($this->twoFactorCode) !== 6 || ! ctype_digit($this->twoFactorCode)) {
            $this->error = 'Enter the 6 digit code from your authenticator app.';
            return;
        }

        $this->verifying2fa = true;

        $response = Api::post('/auth/2fa/verify', [
            'code' => $this->twoFactorCode,
            'secret' => $this->twoFactorSetup['secret'] ?? '',
        ]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Code did not verify.';
            $this->verifying2fa = false;
            return;
        }

        $this->flash = 'Two factor authentication enabled.';
        $this->verifying2fa = false;
        $this->close2fa();
        $this->load();
    }

    public function disable2fa(): void
    {
        $this->error = null;

        if (! $this->disablePassword) {
            $this->error = 'Enter your password to disable 2FA.';
            return;
        }

        $response = Api::post('/security/2fa/disable', ['password' => $this->disablePassword]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Disable failed.';
            return;
        }

        $this->flash = 'Two factor authentication disabled.';
        $this->close2fa();
        $this->load();
    }

    public function strengthPercent(): int
    {
        return max(0, min(100, ((int) ($this->passwordInfo['strength'] ?? 3)) * 20));
    }

    public function render()
    {
        return view('livewire.employee.security')
            ->layout('layouts.portal', ['title' => 'Security', 'breadcrumb' => 'Security']);
    }
}