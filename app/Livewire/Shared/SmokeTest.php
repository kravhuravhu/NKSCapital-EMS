<?php

namespace App\Livewire\Shared;

use App\Support\Api;
use Livewire\Component;

class SmokeTest extends Component
{
    public array $permissionsData = [];
    public array $pendingCounts   = [];
    public bool  $apiOk = false;
    public ?string $apiError = null;

    public function mount(): void
    {
        $perm = Api::get('/auth/permissions');

        if ($perm['ok']) {
            $this->permissionsData = $perm['data'] ?? [];
            $this->apiOk = true;
        } else {
            $this->apiError = $perm['message'] ?? 'Unknown error';
        }

        $counts = Api::get('/dashboard/pending-counts');

        if ($counts['ok']) {
            $this->pendingCounts = $counts['data'] ?? [];
        }
    }

    public function render()
    {
        return view('livewire.shared.smoke-test')
            ->layout('layouts.portal', ['title' => 'Foundation Smoke Test', 'breadcrumb' => 'Smoke Test']);
    }
}