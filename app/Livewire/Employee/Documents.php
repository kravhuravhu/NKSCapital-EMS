<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Livewire\Component;
use Livewire\WithFileUploads;

class Documents extends Component
{
    use WithFileUploads;

    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public string $tab = 'shared';
    public array $shared = [];
    public array $mine = [];
    public array $summary = [];

    public bool $showUpload = false;
    public string $uploadTitle = '';
    public string $uploadCategory = 'HR';
    public bool $uploadShared = false;
    public $uploadFile = null;
    public string $uploadDescription = '';
    public string $aiSummary = '';
    public bool $summarizing = false;

    public function mount(): void
    {
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $response = Api::get('/documents/list');

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load documents.';
            $this->loading = false;
            return;
        }

        $this->shared = $response['data']['shared'] ?? [];
        $this->mine = $response['data']['mine'] ?? [];
        $this->summary = $response['data']['summary'] ?? [];

        $this->loading = false;
    }

    public function selectTab(string $tab): void
    {
        if (in_array($tab, ['shared', 'mine'], true)) {
            $this->tab = $tab;
        }
    }

    public function openUpload(): void
    {
        $this->uploadTitle = '';
        $this->uploadCategory = 'HR';
        $this->uploadShared = false;
        $this->uploadFile = null;
        $this->uploadDescription = '';
        $this->aiSummary = '';
        $this->showUpload = true;
    }

    public function closeUpload(): void
    {
        $this->showUpload = false;
        $this->uploadFile = null;
    }

    public function summarizeDraft(): void
    {
        if (! $this->uploadFile) {
            $this->error = 'Select a file first.';
            return;
        }

        $this->summarizing = true;
        $this->error = null;

        $response = Api::upload('/documents/summarize', [
            'title' => $this->uploadTitle ?: 'Draft',
            'category' => $this->uploadCategory,
        ], ['file' => $this->uploadFile]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'AI could not read this file.';
            $this->summarizing = false;
            return;
        }

        $this->aiSummary = $response['data']['summary'] ?? '';
        $this->summarizing = false;
    }

    public function submitUpload(): void
    {
        $this->error = null;

        if (! $this->uploadTitle || ! $this->uploadFile) {
            $this->error = 'Title and file are required.';
            return;
        }

        $response = Api::upload('/documents/upload', [
            'title' => $this->uploadTitle,
            'category' => $this->uploadCategory,
            'is_shared' => $this->uploadShared ? '1' : '0',
            'description' => $this->aiSummary ?: $this->uploadDescription,
        ], ['file' => $this->uploadFile]);

        if (! $response['ok']) {
            $errs = $response['errors'] ?? [];
            $this->error = collect($errs)->flatten()->first()
                ?: ($response['message'] ?? 'Upload failed.');
            return;
        }

        $this->flash = 'Document uploaded.';
        $this->closeUpload();
        $this->load();
    }

    public function getCurrentListProperty(): array
    {
        return $this->tab === 'mine' ? $this->mine : $this->shared;
    }

    public static function iconFor(string $title): string
    {
        $t = strtolower($title);

        if (str_contains($t, 'payslip')) return 'fa-solid fa-money-check-dollar';
        if (str_contains($t, 'contract')) return 'fa-solid fa-file-contract';
        if (str_contains($t, 'handbook')) return 'fa-solid fa-book';
        if (str_contains($t, 'policy')) return 'fa-solid fa-scroll';
        if (str_contains($t, 'cert')) return 'fa-solid fa-certificate';

        return 'fa-solid fa-file-lines';
    }

    public static function toneFor(string $category): string
    {
        return match ($category) {
            'Compliance' => 'success',
            'Policy' => 'gold',
            'Finance' => 'info',
            'Personal' => 'purple',
            default => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.employee.documents')
            ->layout('layouts.portal', ['title' => 'Documents', 'breadcrumb' => 'Documents']);
    }
}