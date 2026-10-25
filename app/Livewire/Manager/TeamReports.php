<?php

namespace App\Livewire\Manager;

use App\Support\Api;
use Livewire\Component;

class TeamReports extends Component
{
    public bool $loading = true;
    public bool $exporting = false;
    public ?string $error = null;

    public string $from = '';
    public string $to = '';

    public array $report = [];
    public ?array $lastExport = null;

    public function mount(): void
    {
        // default to the current calendar month
        $this->from = now()->startOfMonth()->format('Y-m-d');
        $this->to = now()->endOfMonth()->format('Y-m-d');
        $this->load();
    }

    public function load(): void
    {
        $this->loading = true;
        $this->error = null;
        $this->lastExport = null;

        $response = Api::get('/reports/manager/team-performance', [
            'from' => $this->from,
            'to' => $this->to,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Unable to load team report.';
            $this->loading = false;
            return;
        }

        $this->report = $response['data'] ?? [];
        $this->loading = false;
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
        $this->exporting = true;
        $this->error = null;
        $this->lastExport = null;

        $endpoint = $format === 'pdf'
            ? '/reports/export/pdf'
            : '/reports/export/excel';

        $response = Api::get($endpoint, [
            'report_type' => 'team_performance',
            'from' => $this->from,
            'to' => $this->to,
        ]);

        if (!$response['ok']) {
            $this->error = $response['message'] ?? 'Failed to export report.';
            $this->exporting = false;
            return;
        }

        $data = $response['data'] ?? [];

        // Storage::url() bakes APP_URL into the link, which strips the port on local — rebuild against the live host
        if (!empty($data['download_url'])) {
            $data['download_url'] = $this->normalizeExportUrl($data['download_url']);
        }

        $this->lastExport = $data;
        $this->exporting = false;
    }

    protected function normalizeExportUrl(string $url): string
    {
        // absolute URL — keep only the path, rebuild via url() so it respects the current request host and port
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            $path = parse_url($url, PHP_URL_PATH) ?? '/';
            return url($path);
        }

        return url('/' . ltrim($url, '/'));
    }

    // integer when whole, one decimal otherwise
    public static function formatDays(float $days): string
    {
        if ($days == floor($days)) {
            return (string) (int) $days;
        }

        return number_format($days, 1);
    }

    public function render()
    {
        return view('livewire.manager.team-reports')
            ->layout('layouts.portal', ['title' => 'Team Reports', 'breadcrumb' => 'Team Reports']);
    }
}