<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;
use App\Services\EmployeeCsvImportService;
use App\Livewire\Concerns\HasFlashMessages;

class ImportEmployees extends Component
{
    use WithFileUploads;
    use HasFlashMessages;

    public bool $loading = false;
    public ?string $error = null;
    public ?string $flash = null;

    public $csvFile = null;
    public array $preview = [];
    public int $previewCreated = 0;
    public int $previewSkipped = 0;
    public bool $hasPreviewed = false;

    public bool $showConfirm = false;
    public string $confirmPassword = '';

    public array $importResult = [];
    public bool $imported = false;

    public function mount(): void
    {
        // Nothing to preload.
    }

    public function downloadTemplate()
    {
        return response()->streamDownload(function () {
            echo EmployeeCsvImportService::templateCsv();
        }, 'employee-import-template.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    public function previewImport(): void
    {
        $this->error = null;
        $this->preview = [];

        if (! $this->csvFile) {
            $this->error = 'Select a CSV file first.';
            return;
        }

        $this->loading = true;

        $response = Api::upload(
            '/admin/employee-import/preview',
            [],
            ['file' => $this->csvFile]
        );

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];

            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Preview failed.');

            $this->loading = false;
            return;
        }

        $rows = $response['data']['rows'] ?? [];

        $this->preview = $rows;

        $this->previewCreated = count(array_filter(
            $rows,
            fn ($r) => ($r['status'] ?? '') === 'ready'
        ));

        $this->previewSkipped = count(array_filter(
            $rows,
            fn ($r) => ($r['status'] ?? '') === 'error'
        ));

        $this->hasPreviewed = true;
        $this->loading = false;
    }

    public function openConfirm(): void
    {
        $this->showConfirm = true;
        $this->confirmPassword = '';
        $this->error = null;
    }

    public function closeConfirm(): void
    {
        $this->showConfirm = false;
        $this->confirmPassword = '';
    }

    public function executeImport(): void
    {
        $this->error = null;

        if (empty($this->confirmPassword)) {
            $this->error = 'Enter your admin password.';
            return;
        }

        if (! Auth::validate([
            'email' => auth()->user()->email,
            'password' => $this->confirmPassword,
        ])) {
            $this->error = 'Password is incorrect.';
            return;
        }

        if (! $this->csvFile) {
            $this->error = 'CSV file no longer available. Re-upload.';
            return;
        }

        $this->loading = true;

        $response = Api::upload(
            '/admin/employee-import/import',
            ['password' => $this->confirmPassword],
            ['file' => $this->csvFile]
        );

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];

            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Import failed.');

            $this->loading = false;
            return;
        }

        $this->importResult = $response['data']['rows'] ?? [];

        $this->flash = ($response['data']['created'] ?? 0)
            . ' employees created, '
            . ($response['data']['skipped'] ?? 0)
            . ' skipped.';

        $this->imported = true;

        $this->closeConfirm();
        $this->loading = false;
    }

    /**
     * Reset the employee CSV import form.
     */
    public function resetImport(): void
    {
        $this->csvFile = null;
        $this->preview = [];
        $this->previewCreated = 0;
        $this->previewSkipped = 0;
        $this->hasPreviewed = false;

        $this->imported = false;
        $this->importResult = [];

        $this->flash = null;
        $this->error = null;

        $this->confirmPassword = '';
        $this->showConfirm = false;
        $this->loading = false;
    }

    public function render()
    {
        return view('livewire.admin.import-employees')
            ->layout('layouts.portal', [
                'title' => 'Import Employees',
                'breadcrumb' => 'Import Employees',
            ]);
    }
}
