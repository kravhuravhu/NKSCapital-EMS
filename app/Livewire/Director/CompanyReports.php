<?php

namespace App\Livewire\Director;

use App\Support\Api;
use Livewire\Component;

class CompanyReports extends Component
{
    public bool $loading = true;
    public bool $exporting = false;
    public ?string $error = null;
    public ?string $flash = null;

    public string $tab = 'kpi';
    public string $from = '';
    public string $to = '';

    public array $kpi = [];
    public array $financial = [];
    public ?array $lastExport = null;

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->format('Y-m-d');
        $this->to = now()->endOfMonth()->format('Y-m-d');
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;
        $this->lastExport = null;

        $endpoint = $this->tab === 'financial'
            ? '/reports/director/financial'
            : '/reports/director/company-kpi';

        $response = Api::get($endpoint, [
            'from' => $this->from,
            'to' => $this->to,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load report.';
            $this->loading = false;
            return;
        }

        if ($this->tab === 'financial') {
            $this->financial = $response['data'] ?? [];
        } else {
            $this->kpi = $response['data'] ?? [];
        }

        $this->loading = false;
    }

    public function selectTab(string $tab): void
    {
        if (!in_array($tab, ['kpi', 'financial'], true)) {
            return;
        }

        $this->tab = $tab;
        $this->load();
    }

    public function applyPeriod(): void
    {
        $this->load();
    }

    public function presetPeriod(string $preset): void
    {
        $now = now();

        switch ($preset) {
            case 'this_month':
                $this->from = $now->copy()->startOfMonth()->format('Y-m-d');
                $this->to = $now->copy()->endOfMonth()->format('Y-m-d');
                break;
            case 'last_month':
                $this->from = $now->copy()->subMonthNoOverflow()->startOfMonth()->format('Y-m-d');
                $this->to = $now->copy()->subMonthNoOverflow()->endOfMonth()->format('Y-m-d');
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

        $this->load();
    }

    public function export(string $format): void
    {
        if (!in_array($format, ['excel', 'pdf'], true)) {
            return;
        }

        $this->exporting = true;
        $this->error = null;
        $this->lastExport = null;

        $endpoint = '/reports/export/' . $format;

        $response = Api::get($endpoint, [
            'report_type' => $this->tab === 'financial' ? 'financial' : 'company_kpi',
            'from' => $this->from,
            'to' => $this->to,
        ]);

        if (!$response['ok']) {
            $errs = $response['errors'] ?? [];
            $first = collect($errs)->flatten()->first();
            $this->error = $first ?: ($response['message'] ?? 'Failed to export report.');
            $this->exporting = false;
            return;
        }

        $this->lastExport = $response['data'] ?? null;
        $this->exporting = false;
    }

    public static function money(float $value): string
    {
        return 'R ' . number_format($value, 2);
    }

    public function render()
    {
        return view('livewire.director.company-reports')
            ->layout('layouts.portal', ['title' => 'Company Reports', 'breadcrumb' => 'Company Reports']);
    }
}