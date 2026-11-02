<?php

namespace App\Livewire\Admin;

use App\Support\Api;
use Livewire\Component;

class QueueMonitor extends Component
{
    public bool $loading = true;
    public ?string $error = null;
    public ?string $flash = null;

    public array $stats = [];
    public string $tab = 'pending';
    public bool $autoRefresh = false;

    // pending list
    public array $pendingJobs = [];
    public array $pendingPagination = [];
    public int $pendingPage = 1;

    // failed list
    public array $failedJobs = [];
    public array $failedPagination = [];
    public int $failedPage = 1;
    public ?int $expandedFailedId = null;

    // destructive action modals
    public bool $showClearPending = false;
    public bool $showRetryAll = false;
    public bool $showFlushFailed = false;
    public string $actionPassword = '';
    public bool $actionSubmitting = false;

    public function mount(): void
    {
        $this->refreshAll();
    }

    public function refreshAll(): void
    {
        $this->loadStats();
        $this->loadPending();
        $this->loadFailed();
        $this->loading = false;
    }

    public function loadStats(): void
    {
        $response = Api::get('/admin/queue/stats');

        if ($response['ok']) {
            $this->stats = $response['data'] ?? [];
        }
    }

    public function loadPending(): void
    {
        $response = Api::get('/admin/queue/pending', [
            'page' => $this->pendingPage,
            'per_page' => 25,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load pending jobs.';
            $this->pendingJobs = [];
            $this->pendingPagination = [];
            return;
        }

        $data = $response['data'] ?? [];
        $this->pendingJobs = $data['data'] ?? [];
        $this->pendingPagination = $data;
    }

    public function loadFailed(): void
    {
        $response = Api::get('/admin/queue/failed', [
            'page' => $this->failedPage,
            'per_page' => 25,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load failed jobs.';
            $this->failedJobs = [];
            $this->failedPagination = [];
            return;
        }

        $data = $response['data'] ?? [];
        $this->failedJobs = $data['data'] ?? [];
        $this->failedPagination = $data;
    }

    public function selectTab(string $tab): void
    {
        if (in_array($tab, ['pending', 'failed'], true)) {
            $this->tab = $tab;
            $this->error = null;
        }
    }

    public function toggleAutoRefresh(): void
    {
        $this->autoRefresh = !$this->autoRefresh;
    }

    public function gotoPendingPage(int $page): void
    {
        $this->pendingPage = max(1, $page);
        $this->loadPending();
    }

    public function gotoFailedPage(int $page): void
    {
        $this->failedPage = max(1, $page);
        $this->loadFailed();
    }

    public function toggleFailed(int $id): void
    {
        $this->expandedFailedId = $this->expandedFailedId === $id ? null : $id;
    }

    public function retryJob(int $id): void
    {
        $this->error = null;
        $this->flash = null;

        $response = Api::post('/admin/queue/failed/retry/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to retry job.';
            return;
        }

        $this->flash = 'Job pushed back onto the queue.';
        $this->expandedFailedId = null;
        $this->loadStats();
        $this->loadFailed();
        $this->loadPending();
    }

    public function forgetJob(int $id): void
    {
        $this->error = null;
        $this->flash = null;

        $response = Api::post('/admin/queue/failed/forget/' . $id);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to remove job.';
            return;
        }

        $this->flash = 'Failed job removed.';
        $this->expandedFailedId = null;
        $this->loadStats();
        $this->loadFailed();
    }

    // ----- destructive bulk actions -----

    public function openRetryAll(): void
    {
        if (($this->failedPagination['total'] ?? 0) === 0) {
            $this->error = 'No failed jobs to retry.';
            return;
        }
        $this->actionPassword = '';
        $this->showRetryAll = true;
    }

    public function openFlushFailed(): void
    {
        if (($this->failedPagination['total'] ?? 0) === 0) {
            $this->error = 'No failed jobs to flush.';
            return;
        }
        $this->actionPassword = '';
        $this->showFlushFailed = true;
    }

    public function openClearPending(): void
    {
        if (($this->pendingPagination['total'] ?? 0) === 0) {
            $this->error = 'No pending jobs to clear.';
            return;
        }
        $this->actionPassword = '';
        $this->showClearPending = true;
    }

    public function closeAction(): void
    {
        $this->showRetryAll = false;
        $this->showFlushFailed = false;
        $this->showClearPending = false;
        $this->actionPassword = '';
    }

    public function submitAction(): void
    {
        $this->error = null;
        $this->flash = null;

        if (empty($this->actionPassword)) {
            $this->error = 'Admin password is required.';
            return;
        }

        $this->actionSubmitting = true;

        if ($this->showRetryAll) {
            $response = Api::post('/admin/queue/failed/retry-all', [
                'password' => $this->actionPassword,
            ]);
        } elseif ($this->showFlushFailed) {
            $response = Api::post('/admin/queue/failed/flush', [
                'password' => $this->actionPassword,
            ]);
        } elseif ($this->showClearPending) {
            $response = Api::post('/admin/queue/pending/flush', [
                'password' => $this->actionPassword,
            ]);
        } else {
            $this->actionSubmitting = false;
            return;
        }

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Action failed.';
            $this->actionSubmitting = false;
            return;
        }

        $this->flash = $response['message'] ?? 'Action completed.';
        $this->actionSubmitting = false;
        $this->closeAction();
        $this->refreshAll();
    }

    public static function statsTone(string $key): string
    {
        return match ($key) {
            'pending' => 'blue',
            'failed'  => 'red',
            'batches' => 'purple',
            default   => 'muted',
        };
    }

    public function render()
    {
        return view('livewire.admin.queue-monitor')
            ->layout('layouts.portal', ['title' => 'Queue Monitor', 'breadcrumb' => 'Queue Monitor']);
    }
}