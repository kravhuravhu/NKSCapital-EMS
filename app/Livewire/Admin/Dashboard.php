<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Livewire\Component;

class Dashboard extends Component
{
    public bool  $loading = true;
    public ?string $error = null;

    public array $data   = [];
    public array $counts = [];

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error   = null;

        $dashboard = Api::get('/dashboard/admin');

        if (!$dashboard['ok']) {
            $this->error   = $dashboard['message'] ?? 'Unable to load dashboard.';
            $this->loading = false;
            return;
        }

        $this->data = $dashboard['data']['widgets'] ?? [];

        $counts = Api::get('/dashboard/pending-counts');
        if ($counts['ok']) {
            $this->counts = $counts['data'] ?? [];
        }

        $this->loading = false;
    }

    public function render()
    {
        return view('livewire.admin.dashboard')
            ->layout('layouts.portal', ['title' => 'Admin Dashboard', 'breadcrumb' => 'Dashboard']);
    }
}