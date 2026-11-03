<?php

namespace App\Livewire\Recruiter;

use App\Support\Api;
use Livewire\Component;

class Reports extends Component
{
    public bool $loading = true;
    public bool $exporting = false;
    public ?string $error = null;
    public ?string $flash = null;

    public string $tab = 'metrics';
    public string $from = '';
    public string $to = '';
    public string $department = '';

    public array $metrics = [];
    public array $report = [];

    public ?array $lastExport = null;

    public function mount(): void
    {
        $this->from = now()->subDays(90)->format('Y-m-d');
        $this->to = now()->format('Y-m-d');
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;

        $params = ['from' => $this->from, 'to' => $this->to];

        $metrics = Api::get('/recruitment/metrics', $params);

        if (!$metrics['ok']) {
            $this->error = $metrics['message'] ?? 'Unable to load metrics.';
            $this->loading = false;
            return;
        }

        $this->metrics = $metrics['data'] ?? [];

        // report needs department
        $rParams = $params;
        if ($this->department !== '') {
            $rParams['department'] = $this->department;
        }

        $report = Api::get('/recruitment/report', $rParams);

        if ($report['ok']) {
            $this->report = $report['data'] ?? [];
        } else {
            $this->report = [];
        }

        $this->loading = false;
    }

    public function selectTab(string $tab): void
    {
        if (in_array($tab, ['metrics', 'report'], true)) {
            $this->tab = $tab;
        }
    }

    public function applyPeriod(): void
    {
        $this->lastExport = null;
        $this->load();
    }

    public function presetPeriod(string $preset): void
    {
        $now = now();

        switch ($preset) {
            case 'last_30':
                $this->from = $now->copy()->subDays(30)->format('Y-m-d');
                $this->to = $now->format('Y-m-d');
                break;
            case 'last_90':
                $this->from = $now->copy()->subDays(90)->format('Y-m-d');
                $this->to = $now->format('Y-m-d');
                break;
            case 'this_quarter':
                $this->from = $now->copy()->startOfQuarter()->format('Y-m-d');
                $this->to = $now->copy()->endOfQuarter()->format('Y-m-d');
                break;
            case 'this_year':
                $this->from = $now->copy()->startOfYear()->format('Y-m-d');
                $this->to = $now->copy()->endOfYear()->format('Y-m-d');
                break;
            default:
                return;
        }

        $this->applyPeriod();
    }

    public function exportCsv(): void
    {
        $this->exporting = true;
        $this->error = null;
        $this->lastExport = null;

        $params = [
            'from' => $this->from,
            'to' => $this->to,
            'format' => 'csv',
        ];

        if ($this->department !== '') {
            $params['department'] = $this->department;
        }

        $response = Api::get('/recruitment/report', $params);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to export report.');
            $this->exporting = false;
            return;
        }

        $this->lastExport = $response['data'] ?? null;
        $this->flash = 'Recruitment report exported.';
        $this->exporting = false;
    }

    // ============ helpers ============

    /**
     * Width percentage of a funnel stage relative to the "applied" count.
     */
    public static function funnelWidth(int $count, int $max): int
    {
        if ($max <= 0) return 0;

        return max(0, min(100, (int) round(($count / $max) * 100)));
    }

    public static function formatPercent(float $value): string
    {
        return number_format($value, 1) . '%';
    }

    public function render()
    {
        return view('livewire.recruiter.reports')
            ->layout('layouts.portal', ['title' => 'Reports', 'breadcrumb' => 'Reports']);
    }
}