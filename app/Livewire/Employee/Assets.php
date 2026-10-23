<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Livewire\Component;
use Livewire\WithFileUploads;

class Assets extends Component
{
    use WithFileUploads;

    public bool    $loading = true;
    public ?string $error   = null;
    public ?string $flash   = null;

    public string $tab = 'current';

    public array  $assets  = [];
    public ?array $details = null;

    public bool   $showRepair = false;
    public ?int   $repairAssetId = null;
    public string $repairIssue = '';
    public string $repairUrgency = 'medium';
    public $repairPhoto = null;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error   = null;

        $response = Api::get('/asset/track');

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load assets.';
            $this->loading = false;
            return;
        }

        $this->assets = $response['data']['loaned_assets'] ?? [];
        $this->loading = false;
    }

    public function selectTab(string $tab): void
    {
        if (in_array($tab, ['current', 'history'], true)) {
            $this->tab = $tab;
            $this->details = null;
        }
    }

    public function viewDetails(int $index): void
    {
        $this->details = $this->assets[$index] ?? null;
    }

    public function closeDetails(): void
    {
        $this->details = null;
    }

    public function openRepair(?int $assetId = null): void
    {
        $this->repairAssetId = $assetId ?? ($this->assets[0]['id'] ?? null);
        $this->repairIssue   = '';
        $this->repairUrgency = 'medium';
        $this->repairPhoto   = null;
        $this->showRepair    = true;
    }

    public function closeRepair(): void
    {
        $this->showRepair    = false;
        $this->repairAssetId = null;
        $this->repairIssue   = '';
        $this->repairPhoto   = null;
    }

    public function submitRepair(): void
    {
        $this->error = null;

        if (!$this->repairAssetId || !$this->repairIssue) {
            $this->error = 'Please select an asset and describe the issue.';
            return;
        }

        $payload = [
            'asset_id'          => $this->repairAssetId,
            'issue_description' => $this->repairIssue,
            'urgency'           => $this->repairUrgency,
        ];

        $response = $this->repairPhoto
            ? Api::upload('/asset/repair/request', $payload, ['damage_photo' => $this->repairPhoto])
            : Api::post('/asset/repair/request', $payload);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to submit repair request.';
            return;
        }

        $this->flash = 'Repair request submitted. Your manager has been notified.';
        $this->closeRepair();
        $this->load();
    }

    // ============ computed ============

    public function getFilteredAssetsProperty(): array
    {
        if ($this->tab === 'current') {
            return array_values(array_filter($this->assets, fn ($a) => ($a['status'] ?? '') === 'loaned'));
        }

        return array_values(array_filter($this->assets, fn ($a) => ($a['status'] ?? '') !== 'loaned'));
    }

    public function getSummaryProperty(): array
    {
        $all = $this->assets;

        $active       = collect($all)->where('status', 'loaned')->count();
        $returned     = collect($all)->where('status', 'available')->count();
        $maintenance  = collect($all)->whereIn('status', ['maintenance', 'repair_requested'])->count();
        $overdue      = collect($all)->filter(fn ($a) => ($a['days_overdue'] ?? 0) > 0)->count();

        return [
            'total'       => count($all),
            'active'      => $active,
            'returned'    => $returned,
            'maintenance' => $maintenance,
            'overdue'     => $overdue,
        ];
    }

    public static function iconFor(array $asset): string
    {
        $model = strtolower($asset['model'] ?? '');

        if (str_contains($model, 'monitor') || str_contains($model, 'display') || str_contains($model, 'screen')) {
            return 'fa-solid fa-desktop';
        }
        if (str_contains($model, 'mouse')) {
            return 'fa-solid fa-computer-mouse';
        }
        if (str_contains($model, 'keyboard')) {
            return 'fa-solid fa-keyboard';
        }
        if (str_contains($model, 'phone')) {
            return 'fa-solid fa-mobile-screen';
        }
        if (str_contains($model, 'tablet')) {
            return 'fa-solid fa-tablet-screen-button';
        }
        if (str_contains($model, 'headset') || str_contains($model, 'headphone')) {
            return 'fa-solid fa-headphones';
        }

        return 'fa-solid fa-laptop';
    }

    public function render()
    {
        return view('livewire.employee.assets')
            ->layout('layouts.portal', ['title' => 'My Assets', 'breadcrumb' => 'Assets']);
    }
}