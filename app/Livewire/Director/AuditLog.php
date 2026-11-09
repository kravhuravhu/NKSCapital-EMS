<?php

namespace App\Livewire\Director;

use App\Support\AuditActionVerb;
use App\Support\Api;
use Livewire\Component;

class AuditLog extends Component
{
    public bool $loading = true;
    public bool $verifying = false;
    public bool $exporting = false;
    public ?string $error = null;
    public ?string $flash = null;

    public string $search = '';
    public string $logType = '';
    public string $severity = '';
    public string $from = '';
    public string $to = '';
    public bool $isSlow = false;

    public int $page = 1;

    public array $logs = [];
    public array $pagination = [];
    public array $stats = [];

    public bool $showDetail = false;
    public ?array $detail = null;

    public ?array $chainResult = null;
    public ?array $lastExport = null;

    public function mount(): void
    {
        $this->from = now()->subDays(7)->format('Y-m-d');
        $this->to = now()->format('Y-m-d');
        $this->loadStats();
        $this->load();
    }

    public function loadStats(): void
    {
        $response = Api::get('/admin/audit/report', ['from' => $this->from, 'to' => $this->to]);

        $this->stats = $response['ok'] ? ($response['data']['totals'] ?? []) : [];
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $payload = ['page' => $this->page, 'per_page' => 25];

        if ($this->search !== '')   $payload['search'] = $this->search;
        if ($this->logType !== '')  $payload['log_type'] = $this->logType;
        if ($this->severity !== '') $payload['severity'] = $this->severity;
        if ($this->from !== '')     $payload['from'] = $this->from;
        if ($this->to !== '')       $payload['to'] = $this->to;
        if ($this->isSlow)          $payload['is_slow'] = true;

        $response = Api::post('/admin/audit/search', $payload);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load audit logs.';
            $this->logs = [];
            $this->loading = false;
            return;
        }

        $paginator = $response['data'] ?? [];
        $this->logs = $paginator['data'] ?? [];
        $this->pagination = [
            'current_page' => $paginator['current_page'] ?? 1,
            'last_page' => $paginator['last_page'] ?? 1,
            'total' => $paginator['total'] ?? 0,
        ];

        $this->loading = false;
    }

    public function applyFilters(): void
    {
        $this->page = 1;
        $this->loadStats();
        $this->load();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->logType = '';
        $this->severity = '';
        $this->from = now()->subDays(7)->format('Y-m-d');
        $this->to = now()->format('Y-m-d');
        $this->isSlow = false;
        $this->page = 1;
        $this->applyFilters();
    }

    public function gotoPage(int $page): void
    {
        $this->page = max(1, $page);
        $this->load();
    }

    public function view(int $id): void
    {
        $row = collect($this->logs)->firstWhere('id', $id);

        if (! $row) {
            $this->error = 'Log entry not found.';
            return;
        }

        $this->detail = $row;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->detail = null;
    }

    public function verifyChain(): void
    {
        $this->verifying = true;
        $this->error = null;
        $this->flash = null;

        $response = Api::get('/admin/audit/verify-chain');

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to verify chain.';
            $this->verifying = false;
            return;
        }

        $this->chainResult = $response['data'] ?? null;

        if (! empty($this->chainResult['is_valid'])) {
            $this->flash = 'Chain verified. ' . ($this->chainResult['total_logs'] ?? 0) . ' entries, integrity intact.';
        } else {
            $this->error = 'Chain integrity FAILED at log #' . ($this->chainResult['broken_at'] ?? '?');
        }

        $this->verifying = false;
    }

    public function export(): void
    {
        $this->exporting = true;
        $this->error = null;

        if ($this->from === '' || $this->to === '') {
            $this->error = 'Set From and To dates before exporting.';
            $this->exporting = false;
            return;
        }

        $response = Api::get('/admin/audit/export', [
            'from' => $this->from,
            'to' => $this->to,
            'format' => 'csv',
        ]);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Failed to export.';
            $this->exporting = false;
            return;
        }

        $this->lastExport = $response['data'] ?? null;
        $this->exporting = false;
    }

    public function verb(string $action): string
    {
        return AuditActionVerb::verb($action);
    }

    public function tone(string $action): string
    {
        return AuditActionVerb::tone($action);
    }

    public function initials(?array $user): string
    {
        if (! $user) return 'SY';

        return strtoupper(
            substr($user['first_name'] ?? 'S', 0, 1) .
            substr($user['last_name'] ?? 'Y', 0, 1)
        );
    }

    public function render()
    {
        return view('livewire.director.audit-log')
            ->layout('layouts.portal', ['title' => 'Audit Log', 'breadcrumb' => 'Audit Log']);
    }
}