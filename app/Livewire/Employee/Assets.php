<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;
use Livewire\WithFileUploads;

class Assets extends Component
{
    use WithFileUploads;

    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $tab = 'current';
    public array $assets = [];

    public bool $showRepair = false;
    public ?int $repairAssetId = null;
    public string $repairIssue = '';
    public string $repairUrgency = 'medium';
    public $repairPhoto = null;
    public bool $describing = false;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $response = Api::get('/asset/track');

        if (! $response['ok']) {
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
        }
    }

    public function openRepair(?int $assetId = null): void
    {
        $this->repairAssetId = $assetId ?? ($this->assets[0]['id'] ?? null);
        $this->repairIssue = '';
        $this->repairUrgency = 'medium';
        $this->repairPhoto = null;
        $this->showRepair = true;
    }

    public function closeRepair(): void
    {
        $this->showRepair = false;
        $this->repairAssetId = null;
        $this->repairIssue = '';
        $this->repairPhoto = null;
    }

    public function describeWithAi(): void
    {
        if (! $this->repairAssetId) {
            $this->error = 'Select an asset first.';
            return;
        }

        $asset = collect($this->assets)->firstWhere('id', $this->repairAssetId);

        if (! $asset) {
            $this->error = 'Asset not found.';
            return;
        }

        $this->describing = true;
        $this->error = null;

        $response = Api::post('/ai/describe-issue', [
            'asset_model' => $asset['model'] ?? '',
            'rough_issue' => $this->repairIssue ?: 'Something is not working properly',
        ]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'AI is unavailable.';
            $this->describing = false;
            return;
        }

        $this->repairIssue = $response['data']['text'] ?? $this->repairIssue;
        $this->describing = false;
    }

    public function submitRepair(): void
    {
        $this->error = null;

        if (! $this->repairAssetId || ! $this->repairIssue) {
            $this->error = 'Describe the issue first.';
            return;
        }

        $payload = [
            'asset_id' => $this->repairAssetId,
            'issue_description' => $this->repairIssue,
            'urgency' => $this->repairUrgency,
        ];

        $response = $this->repairPhoto
            ? Api::upload('/asset/repair/request', $payload, ['damage_photo' => $this->repairPhoto])
            : Api::post('/asset/repair/request', $payload);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed to submit repair request.';
            return;
        }

        $this->flash = 'Repair request submitted. Your manager has been notified.';
        $this->closeRepair();
        $this->load();
    }

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

        return [
            'total' => count($all),
            'active' => collect($all)->where('status', 'loaned')->count(),
            'returned' => collect($all)->where('status', 'available')->count(),
            'maintenance' => collect($all)->whereIn('status', ['maintenance', 'repair_requested'])->count(),
            'overdue' => collect($all)->filter(fn ($a) => ($a['days_overdue'] ?? 0) > 0)->count(),
        ];
    }

    public function daysLeft(array $asset): ?int
    {
        if (empty($asset['expected_return_date'])) return null;

        return (int) now()->startOfDay()->diffInDays(Carbon::parse($asset['expected_return_date'])->startOfDay(), false);
    }

    public static function iconFor(array $asset): string
    {
        $model = strtolower($asset['model'] ?? '');

        if (str_contains($model, 'monitor') || str_contains($model, 'display')) return 'fa-solid fa-desktop';
        if (str_contains($model, 'phone')) return 'fa-solid fa-mobile-screen';
        if (str_contains($model, 'tablet')) return 'fa-solid fa-tablet-screen-button';
        if (str_contains($model, 'headset')) return 'fa-solid fa-headphones';

        return 'fa-solid fa-laptop';
    }

    public function render()
    {
        return view('livewire.employee.assets')
            ->layout('layouts.portal', ['title' => 'My Assets', 'breadcrumb' => 'Assets']);
    }
}