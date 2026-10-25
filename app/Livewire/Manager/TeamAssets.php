<?php

namespace App\Livewire\Manager;

use App\Support\Api;
use Livewire\Component;

class TeamAssets extends Component
{
    public bool $loading = true;
    public ?string $error = null;

    public string $search = '';
    public string $filter = 'all';

    public array $assets = [];
    public array $stats = [];

    public bool $showView = false;
    public ?array $viewData = null;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = [];

        if ($this->search) {
            $params['search'] = $this->search;
        }

        $response = Api::get('/asset/track', $params);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load team assets.';
            $this->loading = false;
            return;
        }

        $all = $response['data']['loaned_assets'] ?? [];

        if ($this->filter === 'overdue') {
            $all = array_values(array_filter($all, fn ($a) => !empty($a['is_overdue'])));
        } elseif ($this->filter === 'my_department') {
            $myDept = auth()->user()->department;

            if ($myDept) {
                $all = array_values(array_filter(
                    $all,
                    fn ($a) => ($a['current_assignee']['department'] ?? '') === $myDept
                ));
            }
        }

        $this->assets = $all;

        $report = Api::get('/asset/report');

        if ($report['ok']) {
            $this->stats = $report['data']['stats'] ?? [];
        }

        $this->loading = false;
    }

    public function updatedSearch(): void
    {
        $this->load();
    }

    public function selectFilter(string $filter): void
    {
        if (!in_array($filter, ['all', 'overdue', 'my_department'], true)) {
            return;
        }

        $this->filter = $filter;
        $this->load();
    }

    public function view(int $id): void
    {
        $this->error = null;

        $response = Api::get('/asset/history/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load asset history.';
            return;
        }

        $this->viewData = $response['data'] ?? null;
        $this->showView = true;
    }

    public function closeView(): void
    {
        $this->showView = false;
        $this->viewData = null;
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'loaned'           => 'success',
            'available'        => 'muted',
            'repair_requested',
            'maintenance'      => 'gold',
            'lost', 'damaged'  => 'red',
            default            => 'muted',
        };
    }

    public static function conditionTone(string $condition): string
    {
        return match ($condition) {
            'new', 'good', 'refurbished' => 'success',
            'fair'                       => 'blue',
            'poor', 'for_repair'         => 'gold',
            'damaged', 'obsolete'        => 'red',
            default                      => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.manager.team-assets')
            ->layout('layouts.portal', ['title' => 'Team Assets', 'breadcrumb' => 'Team Assets']);
    }
}