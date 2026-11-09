<?php

namespace App\Livewire\Manager;

use App\Support\Api;
use Livewire\Component;

class HalfDayNotices extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public array $notices = [];

    public bool $showRespond = false;
    public ?int $respondId = null;
    public string $respondStatus = 'acknowledged';
    public string $respondReason = '';

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $response = Api::get('/half-day/pending');

        if ($response['ok']) {
            $this->notices = $response['data'] ?? [];
        } else {
            $this->error = $response['message'] ?? 'Unable to load notices.';
        }

        $this->loading = false;
    }

    public function openRespond(int $id, string $status): void
    {
        $this->respondId = $id;
        $this->respondStatus = $status;
        $this->respondReason = '';
        $this->showRespond = true;
    }

    public function closeRespond(): void
    {
        $this->showRespond = false;
        $this->respondId = null;
        $this->respondReason = '';
    }

    public function submitRespond(): void
    {
        $this->error = null;

        $response = Api::post('/half-day/' . $this->respondId . '/respond', [
            'status' => $this->respondStatus,
            'response' => $this->respondReason ?: null,
        ]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed.';
            return;
        }

        $this->flash = 'Notice ' . $this->respondStatus . '.';
        $this->closeRespond();
        $this->load();
    }

    public function render()
    {
        return view('livewire.manager.half-day-notices')
            ->layout('layouts.portal', ['title' => 'Half-day Notices', 'breadcrumb' => 'Half-day Notices']);
    }
}