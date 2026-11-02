<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Livewire\Component;

class BackupRestore extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public array $backups = [];
    public array $health = [];
    public bool $isSuperAdmin = false;

    public bool $showCreate = false;
    public bool $creating = false;

    public bool $showDelete = false;
    public ?string $deleteFilename = null;
    public string $deletePassword = '';

    public bool $showRestore = false;
    public ?string $restoreFilename = null;
    public string $restorePassword = '';
    public string $restoreConfirm = '';
    public bool $restoring = false;

    public function mount(): void
    {
        $this->isSuperAdmin = auth()->user()->role === 'super_admin';
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $list = Api::get('/admin/backup/list');

        if (!$list['ok']) {
            $this->error = $list['message'] ?? 'Unable to load backups.';
            $this->loading = false;
            return;
        }

        $this->backups = $list['data']['backups'] ?? [];

        $health = Api::get('/admin/health');

        if ($health['ok']) {
            $this->health = $health['raw'] ?? [];
        }

        $this->loading = false;
    }

    // ---- create ----

    public function openCreate(): void
    {
        $this->error = null;
        $this->showCreate = true;
    }

    public function closeCreate(): void
    {
        $this->showCreate = false;
    }

    public function submitCreate(): void
    {
        $this->error = null;
        $this->flash = null;
        $this->creating = true;

        $response = Api::post('/admin/backup/trigger');

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to create backup.';
            $this->creating = false;
            return;
        }

        $data = $response['data'] ?? [];
        $this->flash = 'Backup created: ' . ($data['filename'] ?? 'unknown') .
            ' (' . ($data['size_human'] ?? '—') . ')';
        $this->creating = false;
        $this->closeCreate();
        $this->load();
    }

    // ---- delete ----

    public function openDelete(string $filename): void
    {
        $this->deleteFilename = $filename;
        $this->deletePassword = '';
        $this->error = null;
        $this->showDelete = true;
    }

    public function closeDelete(): void
    {
        $this->showDelete = false;
        $this->deleteFilename = null;
        $this->deletePassword = '';
    }

    public function submitDelete(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->deletePassword)) {
            $this->error = 'Password is required.';
            return;
        }

        $response = Api::delete('/admin/backup/delete/' . $this->deleteFilename, [
            'password' => $this->deletePassword,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to delete backup.';
            return;
        }

        $this->flash = 'Backup deleted.';
        $this->closeDelete();
        $this->load();
    }

    // ---- restore ----

    public function openRestore(string $filename): void
    {
        if (!$this->isSuperAdmin) {
            $this->error = 'Only Super Admins can restore backups.';
            return;
        }

        $this->restoreFilename = $filename;
        $this->restorePassword = '';
        $this->restoreConfirm = '';
        $this->error = null;
        $this->showRestore = true;
    }

    public function closeRestore(): void
    {
        $this->showRestore = false;
        $this->restoreFilename = null;
        $this->restorePassword = '';
        $this->restoreConfirm = '';
    }

    public function submitRestore(): void
    {
        $this->error = null;
        $this->flash = null;

        if (!$this->isSuperAdmin) {
            $this->error = 'Only Super Admins can restore backups.';
            return;
        }

        if (empty($this->restorePassword)) {
            $this->error = 'Password is required.';
            return;
        }

        if ($this->restoreConfirm !== $this->restoreFilename) {
            $this->error = 'The filename you typed does not match.';
            return;
        }

        $this->restoring = true;

        $response = Api::post('/admin/backup/restore/' . $this->restoreFilename, [
            'password' => $this->restorePassword,
            'confirm_filename' => $this->restoreConfirm,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Restore failed.';
            $this->restoring = false;
            return;
        }

        $data = $response['data'] ?? [];
        $safety = $data['safety_backup'] ?? null;

        $this->flash = "Database restored from {$this->restoreFilename}." .
            ($safety ? " Safety backup created: {$safety}" : '');
        $this->restoring = false;
        $this->closeRestore();
        $this->load();
    }

    // ---- helpers ----

    public static function healthTone(string $status): string
    {
        return match ($status) {
            'ok', 'healthy'     => 'success',
            'degraded'          => 'gold',
            'fail', 'unhealthy' => 'red',
            default             => 'muted',
        };
    }

    public static function formatTone(string $format): string
    {
        return match ($format) {
            'sql'     => 'success',
            'sqlite'  => 'success',
            'phpdump' => 'gold',
            default   => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.admin.backup-restore')
            ->layout('layouts.portal', ['title' => 'Backup & Restore', 'breadcrumb' => 'Backup & Restore']);
    }
}