<?php

namespace App\Livewire\Shared;

use App\Support\Api;
use Livewire\Component;

class NotificationBadge extends Component
{
    public int $unread = 0;

    public function mount(): void
    {
        $this->refreshCount();
    }

    public function refreshCount(): void
    {
        $response = Api::get('/notifications/unread/count');

        if ($response['ok']) {
            $this->unread = (int) ($response['data']['total'] ?? 0);
        }
    }

    public function render()
    {
        return view('livewire.shared.notification-badge');
    }
}