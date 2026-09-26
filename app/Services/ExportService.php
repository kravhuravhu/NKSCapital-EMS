<?php

namespace App\Services;

use App\Models\ReportExport;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ExportService
{
    /**
     * Export any report as CSV.
     */
    public function toCsv(array $data, string $reportType, User $actor, array $filters = []): ReportExport
    {
        $filename = "{$reportType}-" . now()->format('Ymd-His') . '-' . Str::random(6) . '.csv';
        $path = 'report-exports/' . $filename;

        $csv = $this->flattenToCsv($data);
        Storage::disk('public')->put($path, $csv);

        return $this->record($actor, $reportType, 'csv', $path, strlen($csv), $filters, $this->countRecords($data));
    }

    /**
     * Export any report as PDF using a generic template.
     */
    public function toPdf(array $data, string $reportType, User $actor, array $filters = []): ReportExport
    {
        $filename = "{$reportType}-" . now()->format('Ymd-His') . '-' . Str::random(6) . '.pdf';
        $path = 'report-exports/' . $filename;

        $pdf = Pdf::loadView('pdf.generic-report', [
            'title' => $this->titleCase($reportType),
            'report_type' => $reportType,
            'data' => $data,
            'filters' => $filters,
            'generated_at' => now()->format('d M Y H:i'),
            'generated_by' => $actor->full_name,
        ]);

        $content = $pdf->output();
        Storage::disk('public')->put($path, $content);

        return $this->record($actor, $reportType, 'pdf', $path, strlen($content), $filters, $this->countRecords($data));
    }

    /**
     * Export as JSON (in-memory).
     */
    public function toJson(array $data, string $reportType, User $actor, array $filters = []): ReportExport
    {
        $filename = "{$reportType}-" . now()->format('Ymd-His') . '-' . Str::random(6) . '.json';
        $path = 'report-exports/' . $filename;

        $content = json_encode($data, JSON_PRETTY_PRINT);
        Storage::disk('public')->put($path, $content);

        return $this->record($actor, $reportType, 'json', $path, strlen($content), $filters, $this->countRecords($data));
    }

    /**
     * Excel export — uses PhpSpreadsheet if available; falls back to CSV.
     */
    public function toExcel(array $data, string $reportType, User $actor, array $filters = []): ReportExport
    {
        if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
            // graceful fallback
            return $this->toCsv($data, $reportType, $actor, $filters);
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(substr($this->titleCase($reportType), 0, 31));

        $row = 1;
        foreach ($data as $key => $value) {
            if (is_array($value) && $this->isAssoc($value)) {
                // Section heading
                $sheet->setCellValue('A' . $row, $this->titleCase($key));
                $sheet->getStyle('A' . $row)->getFont()->setBold(true);
                $row++;

                // Header row
                if (!empty($value)) {
                    $first = reset($value);
                    if (is_array($first)) {
                        $col = 'A';
                        foreach (array_keys($first) as $h) {
                            $sheet->setCellValue($col . $row, $this->titleCase($h));
                            $sheet->getStyle($col . $row)->getFont()->setBold(true);
                            $col++;
                        }
                        $row++;

                        // Data rows
                        foreach ($value as $item) {
                            $col = 'A';
                            foreach ($item as $v) {
                                $sheet->setCellValue($col . $row, is_scalar($v) ? $v : json_encode($v));
                                $col++;
                            }
                            $row++;
                        }
                    } else {
                        // Simple key-value
                        foreach ($value as $k => $v) {
                            $sheet->setCellValue('A' . $row, $this->titleCase($k));
                            $sheet->setCellValue('B' . $row, is_scalar($v) ? $v : json_encode($v));
                            $row++;
                        }
                    }
                }
            } else {
                $sheet->setCellValue('A' . $row, $this->titleCase($key));
                $sheet->setCellValue('B' . $row, is_scalar($value) ? $value : json_encode($value));
                $row++;
            }
            $row++; // blank line between sections
        }

        $filename = "{$reportType}-" . now()->format('Ymd-His') . '-' . Str::random(6) . '.xlsx';
        $path = 'report-exports/' . $filename;

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        Storage::disk('public')->put($path, $content);

        return $this->record($actor, $reportType, 'excel', $path, strlen($content), $filters, $this->countRecords($data));
    }

    // ============================================================
    // HELPERS
    // ============================================================

    protected function record(
        User $actor,
        string $reportType,
        string $format,
        string $path,
        int $size,
        array $filters,
        int $recordCount
    ): ReportExport {
        return ReportExport::create([
            'user_id' => $actor->id,
            'report_type' => $reportType,
            'format' => $format,
            'file_path' => $path,
            'file_size' => $size,
            'filters' => $filters,
            'record_count' => $recordCount,
        ]);
    }

    protected function countRecords(array $data): int
    {
        $count = 0;
        array_walk_recursive($data, function ($value) use (&$count) {
            $count++;
        });
        return $count;
    }

    protected function flattenToCsv(array $data, string $prefix = ''): string
    {
        $out = '';
        foreach ($data as $key => $value) {
            $label = $prefix ? "{$prefix}.{$key}" : $key;
            if (is_array($value)) {
                if ($this->isAssoc($value)) {
                    $out .= $this->flattenToCsv($value, $label);
                } else {
                    foreach ($value as $i => $row) {
                        if (is_array($row)) {
                            $out .= $this->flattenToCsv($row, "{$label}[{$i}]");
                        } else {
                            $out .= sprintf("%s[%d],\"%s\"\n", $label, $i, str_replace('"', '""', $row));
                        }
                    }
                }
            } else {
                $out .= sprintf("%s,\"%s\"\n", $label, str_replace('"', '""', (string) $value));
            }
        }
        return $out;
    }

    protected function isAssoc(array $arr): bool
    {
        if (empty($arr)) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }

    protected function titleCase(string $key): string
    {
        return Str::of($key)->replace(['_', '.'], ' ')->title();
    }
}