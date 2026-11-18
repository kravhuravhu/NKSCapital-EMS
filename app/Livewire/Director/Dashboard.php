<?php

namespace App\Livewire\Director;

use App\Support\Api;
use Livewire\Component;
use App\Livewire\Concerns\HasFlashMessages;

class Dashboard extends Component
{
    public bool  $loading = true;
    public ?string $error = null;

    public array $data   = [];
    public array $counts = [];
    public array $kpi    = [];

    public function mount(): void
    {
        // load() fires from wire:init.
    }

    public function load(bool $silent = false): void
    {
        if (! $silent) {
            $this->loading = true;
        }
        $this->error = null;

        $dashboard = Api::get('/dashboard/director');

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

        $kpi = Api::get('/reports/director/company-kpi');
        if ($kpi['ok']) {
            $this->kpi = $kpi['data'] ?? [];
        }

        $this->loading = false;
    }

    public function poll(): void
    {
        $this->load(silent: true);
    }

    public function render()
    {
        return view('livewire.director.dashboard')
            ->layout('layouts.portal', ['title' => 'Executive Dashboard', 'breadcrumb' => 'Dashboard']);
    }
}