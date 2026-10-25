<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Livewire\Component;

class Security extends Component
{
    public bool    $loading = true;
    public ?string $error   = null;
    public ?string $flash   = null;

    public array $passwordInfo = [];
    public array $sessions     = [];
    public array $activity     = [];

    // change-password modal
    public bool   $showPassword = false;
    public string $currentPassword = '';
    public string $newPassword = '';
    public string $newPasswordConfirm = '';

    // 2fa modal
    public bool   $show2fa = false;
    public array  $twoFactorSetup = [];
    public string $disablePassword = '';

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error   = null;

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

    // ============ change password ============

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

        if (!$this->currentPassword || strlen($this->newPassword) < 8) {
            $this->error = 'Password must be at least 8 characters.';
            return;
        }

        if ($this->newPassword !== $this->newPasswordConfirm) {
            $this->error = 'Passwords do not match.';
            return;
        }

        $response = Api::post('/security/password/change', [
            'current_password'      => $this->currentPassword,
            'new_password'          => $this->newPassword,
            'new_password_confirmation' => $this->newPasswordConfirm,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Password change failed.');
            return;
        }

        $this->flash = $response['message'] ?? 'Password changed.';
        $this->closePassword();
        $this->load();
    }

    // ============ 2FA ============

    public function open2fa(): void
    {
        $this->twoFactorSetup = [];
        $this->disablePassword = '';
        $this->show2fa = true;

        if (!($this->passwordInfo['two_factor_enabled'] ?? false)) {
            $response = Api::post('/security/2fa/enable');
            if ($response['ok']) {
                $this->twoFactorSetup = $response['data'] ?? [];
            }
        }
    }

    public function close2fa(): void
    {
        $this->show2fa = false;
        $this->twoFactorSetup = [];
    }

    public function disable2fa(): void
    {
        $this->error = null;

        if (!$this->disablePassword) {
            $this->error = 'Enter your password to disable 2FA.';
            return;
        }

        $response = Api::post('/security/2fa/disable', [
            'password' => $this->disablePassword,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Disable failed.';
            return;
        }

        $this->flash = 'Two-factor disabled.';
        $this->close2fa();
        $this->load();
    }

    public function revokeSession(int $id): void
    {
        // not a real endpoint yet — marked as TODO
        $this->flash = 'Session revoked (frontend only — endpoint pending).';
    }

    public static function strengthBars(int $strength): int
    {
        return max(0, min(5, $strength));
    }

    public function render()
    {
        return view('livewire.employee.security')
            ->layout('layouts.portal', ['title' => 'Security', 'breadcrumb' => 'Security']);
    }
}