<?php

namespace App\Livewire\Manager;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;

class TeamAssets extends Component
{
    public bool $loading = true;
    public ?string $error = null;

    public string $search = '';
    public string $filter = 'all';
    public string $departmentFilter = '';

    public array $assets = [];
    public array $stats = [];

    public bool $showView = false;
    public ?array $viewData = null;

    public array $departments = [];

    public function mount(): void
    {
        $this->loadDepartments();
        $this->load();
    }

    protected function loadDepartments(): void
    {
        $response = Api::get('/asset/track');

        if (! $response['ok']) return;

        $rows = $response['data']['loaned_assets'] ?? [];

        $depts = collect($rows)
            ->map(fn ($a) => $a['current_assignee']['department'] ?? null)
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->toArray();

        $this->departments = $depts;
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = [];

        if ($this->search !== '') {
            $params['search'] = $this->search;
        }

        $response = Api::get('/asset/track', $params);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load team assets.';
            $this->loading = false;
            return;
        }

        $rows = $response['data']['loaned_assets'] ?? [];

        $myDepartment = auth()->user()->department;

        $rows = array_values(array_filter($rows, function ($a) use ($myDepartment) {
            if ($this->filter === 'overdue') {
                return ! empty($a['is_overdue']);
            }

            if ($this->filter === 'maintenance') {
                return in_array($a['status'] ?? '', ['maintenance', 'repair_requested'], true);
            }

            if ($this->filter === 'my_department') {
                return $myDepartment && ($a['current_assignee']['department'] ?? '') === $myDepartment;
            }

            return true;
        }));

        if ($this->departmentFilter !== '') {
            $rows = array_values(array_filter(
                $rows,
                fn ($a) => ($a['current_assignee']['department'] ?? '') === $this->departmentFilter
            ));
        }

        $this->assets = $rows;

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
        if (! in_array($filter, ['all', 'overdue', 'maintenance', 'my_department'], true)) {
            return;
        }

        $this->filter = $filter;
        $this->load();
    }

    public function updatedDepartmentFilter(): void
    {
        $this->load();
    }

    public function view(int $id): void
    {
        $this->error = null;

        $response = Api::get('/asset/history/' . $id);

        if (! $response['ok']) {
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

    public function daysOverdue(array $asset): int
    {
        return (int) ($asset['days_overdue'] ?? 0);
    }

    public function daysUntilReturn(array $asset): ?int
    {
        if (empty($asset['expected_return_date'])) return null;

        return (int) now()->startOfDay()->diffInDays(
            Carbon::parse($asset['expected_return_date'])->startOfDay(),
            false
        );
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'loaned' => 'success',
            'available' => 'muted',
            'repair_requested', 'maintenance' => 'warning',
            'lost', 'damaged' => 'danger',
            default => 'muted',
        };
    }

    public static function conditionTone(string $condition): string
    {
        return match ($condition) {
            'new', 'good', 'refurbished' => 'success',
            'fair' => 'info',
            'poor', 'for_repair' => 'warning',
            'damaged', 'obsolete' => 'danger',
            default => 'muted',
        };
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
        return view('livewire.manager.team-assets')
            ->layout('layouts.portal', ['title' => 'Team Assets', 'breadcrumb' => 'Team Assets']);
    }
}