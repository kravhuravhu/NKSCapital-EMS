<?php

namespace App\Livewire\Auth;

use App\Livewire\Concerns\HasFlashMessages;
use App\Support\Api;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Component;

class QrApprove extends Component
{
    use HasFlashMessages;

    #[Locked]
    public string $challenge = '';

    #[Locked]
    public string $targetName = '';

    #[Locked]
    public string $state = 'ready';

    public string $password = '';

    public ?string $error = null;
    public ?string $flash = null;

    public function mount(string $challenge, string $state = 'ready', string $targetName = ''): void
    {
        $this->challenge = $challenge;
        $this->state = $state;
        $this->targetName = $targetName;
    }

    public function approve(): void
    {
        if ($this->state !== 'ready') {
            return;
        }

        $this->error = null;
        $this->flash = null;

        $this->validate(['password' => 'required|string']);

        $user = Auth::user();

        if (!$user || !Auth::validate(['email' => $user->email, 'password' => $this->password])) {
            $this->error = 'Password is incorrect.';
            $this->password = '';
            return;
        }

        $response = Api::post('/auth/qr/approve', [
            'challenge' => $this->challenge,
            'password' => $this->password,
        ]);

        $this->password = '';

        if (!$response['ok']) {
            $status = (int) ($response['status'] ?? 0);

            if ($status === 403) {
                $this->state = 'mismatch';
                return;
            }

            $this->error = $response['message'] ?? 'Approval failed.';

            if (in_array($status, [404, 422], true)) {
                $this->state = 'invalid';
            }

            return;
        }

        $this->state = 'approved';
        $this->flash = 'Sign in approved. You can close this page.';
    }

    public function cancel(): void
    {
        if ($this->state !== 'ready') {
            return;
        }

        $this->error = null;
        $this->flash = null;
        $this->password = '';

        Api::post('/auth/qr/cancel/' . $this->challenge);

        $this->state = 'cancelled';
    }

    public function render()
    {
        return view('livewire.auth.qr-approve');
    }
}