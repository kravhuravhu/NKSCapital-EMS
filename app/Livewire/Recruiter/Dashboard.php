<?php

namespace App\Livewire\Recruiter;

use App\Support\Api;
use Livewire\Component;

class Dashboard extends Component
{
    public bool  $loading = true;
    public ?string $error = null;

    public array $data = [];

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error   = null;

        $dashboard = Api::get('/dashboard/recruiter');

        if (!$dashboard['ok']) {
            $this->error   = $dashboard['message'] ?? 'Unable to load dashboard.';
            $this->loading = false;
            return;
        }

        $this->data = $dashboard['data']['widgets'] ?? [];
        $this->loading = false;
    }

    public function render()
    {
        return view('livewire.recruiter.dashboard')
            ->layout('layouts.portal', ['title' => 'Recruiter Dashboard', 'breadcrumb' => 'Dashboard']);
    }
}