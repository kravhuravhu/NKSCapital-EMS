<?php

namespace App\Services;

use App\Models\PRTimesheet;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class PayrollExportService
{
    /**
     * Generate payroll export data for approved timesheets
     */
    public function generatePayrollData(?string $monthYear = null, ?int $userId = null): array
    {
        $query = PRTimesheet::with([
            'user:id,employee_number,first_name,last_name,email,department,position',
            'user.contracts' => function ($q) {
                $q->where('status', 'active')->latest('effective_date');
            },
            'level1Approver:id,first_name,last_name',
            'level2Approver:id,first_name,last_name',
        ])
            ->where('status', 'approved')
            ->where('exported_to_payroll', false);

        if ($monthYear) {
            $query->where('month_year', $monthYear);
        }

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $timesheets = $query->get();

        $payrollData = [];
        $totalAmount = 0;

        foreach ($timesheets as $timesheet) {
            // Get hourly rate from active contract or default
            $hourlyRate = $this->getHourlyRate($timesheet->user);

            $regularAmount = $timesheet->total_regular_hours * $hourlyRate;
            $overtimeRate = $hourlyRate * 1.5;
            $overtimeAmount = $timesheet->total_overtime_hours * $overtimeRate;

            $totalAmount += $regularAmount + $overtimeAmount;

            $payrollData[] = [
                'employee_number' => $timesheet->user->employee_number,
                'employee_name' => $timesheet->user->full_name,
                'department' => $timesheet->user->department,
                'position' => $timesheet->user->position,
                'month' => $timesheet->month_year->format('F Y'),
                'regular_hours' => (float) $timesheet->total_regular_hours,
                'overtime_hours' => (float) $timesheet->total_overtime_hours,
                'hourly_rate' => $hourlyRate,
                'regular_amount' => round($regularAmount, 2),
                'overtime_amount' => round($overtimeAmount, 2),
                'total_amount' => round($regularAmount + $overtimeAmount, 2),
                'l1_approver' => $timesheet->level1Approver?->full_name,
                'l2_approver' => $timesheet->level2Approver?->full_name,
                'approved_date' => $timesheet->level2_approved_at?->format('Y-m-d H:i'),
                'pdf_hash' => $timesheet->pdf_hash,
            ];
        }

        return [
            'timesheets' => $payrollData,
            'summary' => [
                'total_employees' => count($payrollData),
                'total_regular_hours' => array_sum(array_column($payrollData, 'regular_hours')),
                'total_overtime_hours' => array_sum(array_column($payrollData, 'overtime_hours')),
                'total_amount' => round($totalAmount, 2),
                'generated_at' => now()->format('Y-m-d H:i:s'),
                'generated_by' => auth()->user()?->full_name ?? 'System',
            ],
        ];
    }

    /**
     * Get hourly rate for a user
     */
    private function getHourlyRate(User $user): float
    {
        $contract = $user->contracts()->where('status', 'active')->latest('effective_date')->first();

        if ($contract && $contract->salary_annual) {
            // Assuming 2080 working hours per year (40 hrs/week * 52 weeks)
            return round($contract->salary_annual / 2080, 2);
        }

        // Default rate if no contract
        return 500.00;
    }

    /**
     * Mark timesheets as exported
     */
    public function markAsExported(?string $monthYear = null): int
    {
        $query = PRTimesheet::where('status', 'approved')
            ->where('exported_to_payroll', false);

        if ($monthYear) {
            $query->where('month_year', $monthYear);
        }

        return $query->update([
            'exported_to_payroll' => true,
            'exported_at' => now(),
        ]);
    }

    /**
     * Export payroll data to CSV
     */
    public function exportToCsv(array $payrollData, ?string $filename = null): string
    {
        $filename = $filename ?? 'payroll-export-' . now()->format('Y-m-d-H-i-s') . '.csv';
        $path = 'payroll-exports/' . $filename;

        $handle = fopen('php://temp', 'r+');

        // Headers
        fputcsv($handle, [
            'Employee Number',
            'Employee Name',
            'Department',
            'Position',
            'Month',
            'Regular Hours',
            'Overtime Hours',
            'Hourly Rate (R)',
            'Regular Amount (R)',
            'Overtime Amount (R)',
            'Total Amount (R)',
            'L1 Approver',
            'L2 Approver',
            'Approved Date',
            'PDF Hash',
        ]);

        // Data
        foreach ($payrollData['timesheets'] as $row) {
            fputcsv($handle, [
                $row['employee_number'],
                $row['employee_name'],
                $row['department'],
                $row['position'],
                $row['month'],
                $row['regular_hours'],
                $row['overtime_hours'],
                $row['hourly_rate'],
                $row['regular_amount'],
                $row['overtime_amount'],
                $row['total_amount'],
                $row['l1_approver'],
                $row['l2_approver'],
                $row['approved_date'],
                $row['pdf_hash'],
            ]);
        }

        // Summary row
        fputcsv($handle, []);
        fputcsv($handle, ['SUMMARY', '', '', '', '', '', '', '', '', '', '', '', '', '', '']);
        fputcsv($handle, [
            'Total Employees',
            $payrollData['summary']['total_employees'],
        ]);
        fputcsv($handle, [
            'Total Regular Hours',
            $payrollData['summary']['total_regular_hours'],
        ]);
        fputcsv($handle, [
            'Total Overtime Hours',
            $payrollData['summary']['total_overtime_hours'],
        ]);
        fputcsv($handle, [
            'Total Amount (R)',
            $payrollData['summary']['total_amount'],
        ]);

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        Storage::disk('public')->put($path, $content);

        return $path;
    }
}