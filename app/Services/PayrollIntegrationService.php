<?php

namespace App\Services;

use App\Models\PRTimesheet;
use App\Models\PayrollSyncLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PayrollIntegrationService
{
    /**
     * Export approved PR timesheets to payroll (CSV + optional webhook).
     */
    public function export(User $actor, ?string $monthYear = null): PayrollSyncLog
    {
        $month = $monthYear
            ? Carbon::parse($monthYear)->startOfMonth()
            : now()->startOfMonth();

        $timesheets = PRTimesheet::with('user:id,employee_number,first_name,last_name,department,position')
            ->where('status', 'approved')
            ->where('exported_to_payroll', false)
            ->where('month_year', $month->format('Y-m-d'))
            ->get();

        if ($timesheets->isEmpty()) {
            throw new \RuntimeException('No approved timesheets available for payroll export this period.');
        }

        // Build CSV
        $csv = "Employee Number,Employee Name,Department,Position,Month,Regular Hours,Overtime Hours,Total Hours,Approved Date,PDF Hash\n";
        $totalAmount = 0;
        $defaultRate = 500.00;

        foreach ($timesheets as $t) {
            $totalHours = (float) $t->total_regular_hours + (float) $t->total_overtime_hours;
            $amount = ($t->total_regular_hours * $defaultRate)
                    + ($t->total_overtime_hours * $defaultRate * 1.5);
            $totalAmount += $amount;

            $csv .= sprintf(
                "%s,\"%s\",%s,\"%s\",%s,%.2f,%.2f,%.2f,%s,%s\n",
                $t->user->employee_number,
                $t->user->full_name,
                $t->user->department ?? '',
                $t->user->position ?? '',
                $t->month_year->format('F Y'),
                $t->total_regular_hours,
                $t->total_overtime_hours,
                $totalHours,
                $t->level2_approved_at?->toDateTimeString() ?? '',
                $t->pdf_hash ?? ''
            );
        }

        $filename = 'payroll-' . $month->format('Y-m') . '-' . time() . '.csv';
        $path = 'payroll-exports/' . $filename;
        Storage::disk('public')->put($path, $csv);

        // Create sync log
        $log = PayrollSyncLog::create([
            'month_year' => $month->format('Y-m-d'),
            'records_count' => $timesheets->count(),
            'total_amount' => round($totalAmount, 2),
            'status' => 'pending',
            'payload' => [
                'csv_path' => $path,
                'employee_ids' => $timesheets->pluck('user_id')->toArray(),
            ],
            'initiated_by' => $actor->id,
        ]);

        // Attempt webhook to external payroll system (best-effort)
        $this->pushToExternal($log);

        // Mark timesheets as exported
        PRTimesheet::whereIn('id', $timesheets->pluck('id'))
            ->update(['exported_to_payroll' => true, 'exported_at' => now()]);

        AuditService::log(
            action: 'PAYROLL_EXPORTED',
            tableName: 'payroll_sync_logs',
            recordId: $log->id,
            newValues: [
                'month_year' => $month->format('Y-m-d'),
                'records_count' => $log->records_count,
                'total_amount' => $log->total_amount,
                'csv_path' => $path,
            ],
            logType: 'success'
        );

        return $log->fresh();
    }

    /**
     * Receive payroll acknowledgment webhook.
     */
    public function receiveAcknowledgment(array $payload): PayrollSyncLog
    {
        $reference = $payload['external_reference'] ?? null;

        $log = $reference
            ? PayrollSyncLog::where('external_reference', $reference)->first()
            : null;

        if (!$log) {
            $log = PayrollSyncLog::where('status', 'pending')->latest()->first();
        }

        if (!$log) {
            throw new \RuntimeException('No matching payroll sync log found.');
        }

        $log->status = $payload['status'] ?? 'completed';
        $log->external_reference = $reference ?? $log->external_reference;
        $log->response_payload = $payload;
        $log->error_message = $payload['error'] ?? null;
        $log->completed_at = now();
        $log->save();

        AuditService::log(
            action: 'PAYROLL_WEBHOOK_RECEIVED',
            tableName: 'payroll_sync_logs',
            recordId: $log->id,
            newValues: [
                'status' => $log->status,
                'external_reference' => $log->external_reference,
            ],
            logType: $log->status === 'completed' ? 'success' : 'warning'
        );

        return $log;
    }

    /**
     * Best-effort push to external payroll system.
     */
    protected function pushToExternal(PayrollSyncLog $log): void
    {
        $endpoint = config('services.payroll.webhook_url');

        if (!$endpoint) {
            $log->status = 'completed';
            $log->completed_at = now();
            $log->external_reference = 'LOCAL-' . Str::upper(Str::random(8));
            $log->save();
            return;
        }

        try {
            $response = Http::timeout(30)->post($endpoint, [
                'event' => 'payroll.export',
                'payload' => $log->payload,
                'records_count' => $log->records_count,
                'total_amount' => $log->total_amount,
                'timestamp' => now()->toIso8601String(),
            ]);

            $log->status = $response->successful() ? 'completed' : 'failed';
            $log->response_payload = [
                'status' => $response->status(),
                'body' => Str::limit($response->body(), 2000),
            ];
            $log->external_reference = $response->json('reference') ?? $log->external_reference;
            $log->error_message = $response->successful() ? null : 'Non-2xx response';
            $log->completed_at = now();
            $log->save();
        } catch (\Throwable $e) {
            $log->status = 'failed';
            $log->error_message = $e->getMessage();
            $log->save();
        }
    }
}