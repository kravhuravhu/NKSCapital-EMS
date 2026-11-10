<?php

namespace App\Livewire\Employee;

use App\Support\Api;
use Carbon\Carbon;
use Livewire\Component;

class TimesheetHistory extends Component
{
    public bool $loading = true;
    public ?string $error = null;

    public string $workflow = 'pr';
    public array $items = [];
    public array $pagination = [];
    public string $statusFilter = '';
    public int $page = 1;

    public array $stats = [
        'total' => 0,
        'approved' => 0,
        'pending' => 0,
        'rejected' => 0,
        'total_hours' => 0.0,
    ];

    public bool $showDetail = false;
    public ?array $detailData = null;
    public ?array $detailMeta = null;

    public function mount(): void
    {
        $user = auth()->user();
        $this->workflow = $user->employee_type === 'ps' ? 'ps' : 'pr';
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        if ($this->workflow === 'pr') {
            $params = ['page' => $this->page, 'per_page' => 15];

            if ($this->statusFilter !== '') {
                $params['status'] = $this->statusFilter;
            }

            $response = Api::get('/timesheet/pr/list', $params);

            if (! $response['ok']) {
                $this->error = $response['message'] ?? 'Unable to load timesheet history.';
                $this->loading = false;
                return;
            }

            $paginator = $response['data'] ?? [];
            $this->items = $paginator['data'] ?? [];
            $this->pagination = [
                'current_page' => $paginator['current_page'] ?? 1,
                'last_page' => $paginator['last_page'] ?? 1,
                'total' => $paginator['total'] ?? 0,
            ];
        } else {
            $response = Api::get('/timesheet/ps/history');

            if (! $response['ok']) {
                $this->error = $response['message'] ?? 'Unable to load timesheet history.';
                $this->loading = false;
                return;
            }

            $rows = $response['data'] ?? [];

            if ($this->statusFilter !== '') {
                $rows = array_values(array_filter(
                    $rows,
                    fn ($r) => ($r['status'] ?? '') === $this->statusFilter
                ));
            }

            $this->items = $rows;
            $this->pagination = [
                'current_page' => 1,
                'last_page' => 1,
                'total' => count($rows),
            ];
        }

        $this->computeStats();
        $this->loading = false;
    }

    protected function computeStats(): void
    {
        $stats = [
            'total' => 0,
            'approved' => 0,
            'pending' => 0,
            'rejected' => 0,
            'total_hours' => 0.0,
        ];

        foreach ($this->items as $row) {
            $stats['total']++;

            $status = $row['status'] ?? 'draft';
            $regular = (float) ($row['total_regular_hours'] ?? $row['total_hours'] ?? 0);
            $overtime = (float) ($row['total_overtime_hours'] ?? 0);

            $stats['total_hours'] += $regular + $overtime;

            if (in_array($status, ['approved', 'submitted_to_client'], true)) {
                $stats['approved']++;
            } elseif (in_array($status, ['pending_l1', 'pending_l2', 'template_generated', 'external_pending', 'external_signed'], true)) {
                $stats['pending']++;
            } elseif ($status === 'rejected') {
                $stats['rejected']++;
            }
        }

        $this->stats = $stats;
    }

    public function selectStatus(string $status): void
    {
        if (! in_array($status, ['', 'draft', 'pending_l1', 'pending_l2', 'approved', 'rejected', 'submitted_to_client', 'template_generated', 'external_signed'], true)) {
            return;
        }

        $this->statusFilter = $status;
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

        $path = $this->workflow === 'ps'
            ? '/timesheet/ps/' . $id
            : '/timesheet/pr/' . $id;

        $response = Api::get($path);

        if (! $response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load timesheet.';
            return;
        }

        $this->detailData = $response['data'] ?? null;
        $this->showDetail = true;
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->detailData = null;
    }

    public function statusTone(string $status): string
    {
        return match ($status) {
            'approved', 'submitted_to_client' => 'success',
            'pending_l1', 'pending_l2', 'template_generated', 'external_pending', 'external_signed' => 'warning',
            'rejected' => 'danger',
            'draft' => 'muted',
            default => 'muted',
        };
    }

    public function statusLabel(string $status): string
    {
        return match ($status) {
            'submitted_to_client' => 'Submitted to client',
            'external_signed' => 'Signed by client',
            'external_pending' => 'Awaiting client',
            'template_generated' => 'Template generated',
            'pending_l1' => 'Pending L1',
            'pending_l2' => 'Pending L2',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public function totalHours(array $row): float
    {
        return (float) ($row['total_regular_hours'] ?? $row['total_hours'] ?? 0)
             + (float) ($row['total_overtime_hours'] ?? 0);
    }

    public function render()
    {
        return view('livewire.employee.timesheet-history')
            ->layout('layouts.portal', ['title' => 'Timesheet History', 'breadcrumb' => 'Timesheet History']);
    }
}