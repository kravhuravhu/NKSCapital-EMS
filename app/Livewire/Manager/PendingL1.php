<?php

namespace App\Livewire\Manager;

use App\Support\Api;
use Livewire\Component;

class PendingL1 extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public array $timesheets = [];

    public bool $showView = false;
    public ?array $viewData = null;

    public bool $showReject = false;
    public ?int $rejectId = null;
    public string $rejectReason = '';

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        // pending L1 approvals for the signed-in manager
        $response = Api::get('/timesheet/pr/pending/l1');

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load pending timesheets.';
            $this->loading = false;
            return;
        }

        $this->timesheets = $response['data'] ?? [];
        $this->loading = false;
    }

    public function view(int $id): void
    {
        $this->error = null;

        // full timesheet for the modal
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

    public function approve(int $id): void
    {
        $this->error = null;
        $this->flash = null;

        // L1 approval — forwards the timesheet to the director
        $response = Api::post('/timesheet/pr/approve/l1', [
            'timesheet_id' => $id,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to approve timesheet.';
            return;
        }

        $this->flash = 'Timesheet approved and forwarded to L2.';
        $this->closeView();
        $this->load();
    }

    public function openReject(int $id): void
    {
        $this->rejectId = $id;
        $this->rejectReason = '';
        $this->showReject = true;
    }

    public function closeReject(): void
    {
        $this->showReject = false;
        $this->rejectId = null;
        $this->rejectReason = '';
    }

    public function submitReject(): void
    {
        $this->error = null;

        if (!$this->rejectReason) {
            $this->error = 'Please provide a rejection reason.';
            return;
        }

        // send back to the employee
        $response = Api::post('/timesheet/pr/reject/l1', [
            'timesheet_id' => $this->rejectId,
            'reason' => $this->rejectReason,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to reject timesheet.';
            return;
        }

        $this->flash = 'Timesheet rejected.';
        $this->closeReject();
        $this->load();
    }

    public function render()
    {
        return view('livewire.manager.pending-l1')
            ->layout('layouts.portal', ['title' => 'Pending L1', 'breadcrumb' => 'Pending L1']);
    }
}