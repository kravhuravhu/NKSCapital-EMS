<?php

namespace App\Livewire\Manager;

use App\Support\Api;
use Livewire\Component;

class TeamTimesheets extends Component
{
    public bool $loading = true;
    public ?string $error = null;

    public string $status = 'all';
    public string $month = '';
    public int $page = 1;

    public array $timesheets = [];
    public array $pagination = [];

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

        $query = ['page' => $this->page, 'per_page' => 15];

        if ($this->status !== 'all') {
            $query['status'] = $this->status;
        }

        if ($this->month) {
            $query['month_year'] = $this->month . '-01';
        }

        // managers get all timesheets via history endpoint
        $response = Api::get('/timesheet/pr/history', $query);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load team timesheets.';
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];

        $this->timesheets = $paginator['data'] ?? [];
        $this->pagination = [
            'current_page' => $paginator['current_page'] ?? 1,
            'last_page'    => $paginator['last_page'] ?? 1,
            'total'        => $paginator['total'] ?? 0,
        ];

        $this->loading = false;
    }

    public function selectStatus(string $status): void
    {
        if (!in_array($status, ['all', 'draft', 'pending_l1', 'pending_l2', 'approved', 'rejected'], true)) {
            return;
        }

        $this->status = $status;
        $this->page = 1;
        $this->load();
    }

    public function updatedMonth(): void
    {
        $this->page = 1;
        $this->load();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    public function view(int $id): void
    {
        $this->error = null;

        $response = Api::get('/timesheet/pr/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load timesheet.';
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

    public function render()
    {
        return view('livewire.manager.team-timesheets')
            ->layout('layouts.portal', ['title' => 'Team Timesheets', 'breadcrumb' => 'Team Timesheets']);
    }
}