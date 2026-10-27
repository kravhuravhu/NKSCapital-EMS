<?php

namespace App\Livewire\Director;

use App\Support\Api;
use Livewire\Component;

class PayrollExport extends Component
{
    public bool $loading = true;
    public bool $syncing = false;
    public bool $downloading = false;
    public ?string $error = null;
    public ?string $flash = null;

    public string $month = '';

    public array $preview = [];
    public array $summary = [];
    public ?array $lastDownload = null;
    public ?array $syncResult = null;

    public bool $showSync = false;

    public function mount(): void
    {
        $this->month = now()->format('Y-m');
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;
        $this->lastDownload = null;

        $response = Api::get('/timesheet/pr/export/payroll', [
            'month_year' => $this->month . '-01',
            'format' => 'json',
        ]);

        // 404 with "No approved timesheets" is expected — treat as empty state
        if (!$response['ok'] && $response['status'] === 404) {
            $this->preview = [];
            $this->summary = [];
            $this->loading = false;
            return;
        }

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load payroll preview.';
            $this->preview = [];
            $this->summary = [];
            $this->loading = false;
            return;
        }

        $data = $response['data'] ?? [];

        $this->preview = $data['timesheets'] ?? [];
        $this->summary = $data['summary'] ?? [];

        $this->loading = false;
    }

    public function updatedMonth(): void
    {
        $this->load();
    }

    public function download(string $format): void
    {
        if (!in_array($format, ['csv', 'pdf'], true)) {
            return;
        }

        $this->downloading = true;
        $this->error = null;
        $this->lastDownload = null;

        $response = Api::get('/timesheet/pr/export/payroll', [
            'month_year' => $this->month . '-01',
            'format' => $format,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to prepare download.';
            $this->downloading = false;
            return;
        }

        $this->lastDownload = $response['data'] ?? null;
        $this->downloading = false;
    }

    public function openSync(): void
    {
        $this->error = null;

        if (empty($this->preview)) {
            $this->error = 'No timesheets are ready to export for this month.';
            return;
        }

        $this->showSync = true;
    }

    public function closeSync(): void
    {
        $this->showSync = false;
    }

    public function submitSync(): void
    {
        $this->error = null;
        $this->flash = null;
        $this->syncResult = null;

        // payroll export is a GET endpoint per IntegrationController::payrollExport
        $response = Api::get('/integrations/payroll/export', [
            'month_year' => $this->month . '-01',
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to sync payroll.');
            $this->closeSync();
            return;
        }

        $this->syncResult = $response['data'] ?? null;
        $this->flash = 'Payroll synced. Timesheets have been marked as exported.';

        $this->closeSync();

        // reload — the preview should now be empty because timesheets are marked exported
        $this->load();
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'completed' => 'success',
            'pending'   => 'blue',
            'processing'=> 'gold',
            'failed'    => 'red',
            default     => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.director.payroll-export')
            ->layout('layouts.portal', ['title' => 'Payroll Export', 'breadcrumb' => 'Payroll Export']);
    }
}