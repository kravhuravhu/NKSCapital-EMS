<?php

namespace App\Services;

use App\Models\Webhook;
use App\Models\WebhookLog;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WebhookService
{
    protected int $maxAttempts = 3;
    protected int $backoffBaseMs = 500;

    /**
     * Register a new webhook.
     */
    public function register(array $data, int $actorId): Webhook
    {
        $webhook = Webhook::create([
            'name' => $data['name'],
            'target_url' => $data['target_url'],
            'secret' => $data['secret'] ?? Str::random(40),
            'events' => $data['events'],
            'is_active' => $data['is_active'] ?? true,
            'created_by' => $actorId,
        ]);

        AuditService::log(
            action: 'WEBHOOK_REGISTERED',
            tableName: 'webhooks',
            recordId: $webhook->id,
            newValues: [
                'name' => $webhook->name,
                'target_url' => $webhook->target_url,
                'events' => $webhook->events,
            ],
            logType: 'success'
        );

        return $webhook;
    }

    /**
     * Dispatch an event to all subscribed webhooks.
     */
    public function dispatch(string $event, array $payload): int
    {
        $webhooks = Webhook::active()->get()
            ->filter(fn ($w) => $w->subscribesTo($event));

        $delivered = 0;
        foreach ($webhooks as $webhook) {
            $this->deliver($webhook, $event, $payload, 1);
            $delivered++;
        }
        return $delivered;
    }

    /**
     * Deliver to a single webhook with retry logic.
     */
    public function deliver(Webhook $webhook, string $event, array $payload, int $attempt = 1): WebhookLog
    {
        $start = microtime(true);

        $body = json_encode([
            'event' => $event,
            'payload' => $payload,
            'timestamp' => now()->toIso8601String(),
            'webhook_id' => $webhook->id,
        ]);

        $signature = 'sha256=' . hash_hmac('sha256', $body, $webhook->secret);

        $log = WebhookLog::create([
            'webhook_id' => $webhook->id,
            'event' => $event,
            'payload' => $payload,
            'attempt' => $attempt,
            'status' => 'retrying',
            'signature' => $signature,
        ]);

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-NKS-Signature' => $signature,
                'X-NKS-Event' => $event,
                'X-NKS-Delivery' => (string) $log->id,
            ])
                ->timeout(15)
                ->withBody($body, 'application/json')
                ->post($webhook->target_url);

            $duration = (int) round((microtime(true) - $start) * 1000);

            $success = $response->successful();

            $log->update([
                'response_status' => $response->status(),
                'response_body' => Str::limit($response->body(), 5000),
                'duration_ms' => $duration,
                'status' => $success ? 'success' : 'failed',
                'error_message' => $success ? null : 'Non-2xx response',
            ]);

            $webhook->update([
                'last_triggered_at' => now(),
                'last_status' => $response->status(),
                'success_count' => $webhook->success_count + ($success ? 1 : 0),
                'failure_count' => $webhook->failure_count + ($success ? 0 : 1),
            ]);

            if (!$success && $attempt < $this->maxAttempts) {
                usleep($this->backoffBaseMs * 1000 * (2 ** ($attempt - 1)));
                return $this->deliver($webhook, $event, $payload, $attempt + 1);
            }

            if (!$success) {
                $log->update(['status' => 'dead_letter']);
            }

            return $log;
        } catch (\Throwable $e) {
            $duration = (int) round((microtime(true) - $start) * 1000);
            $log->update([
                'duration_ms' => $duration,
                'status' => $attempt < $this->maxAttempts ? 'retrying' : 'dead_letter',
                'error_message' => $e->getMessage(),
            ]);

            $webhook->update([
                'last_triggered_at' => now(),
                'failure_count' => $webhook->failure_count + 1,
            ]);

            if ($attempt < $this->maxAttempts) {
                usleep($this->backoffBaseMs * 1000 * (2 ** ($attempt - 1)));
                return $this->deliver($webhook, $event, $payload, $attempt + 1);
            }

            return $log;
        }
    }

    /**
     * Retry a failed delivery.
     */
    public function retry(WebhookLog $log): WebhookLog
    {
        if ($log->status === 'success') {
            throw new \RuntimeException('Cannot retry a successful delivery.');
        }

        $webhook = $log->webhook;

        AuditService::log(
            action: 'WEBHOOK_RETRY_REQUESTED',
            tableName: 'webhook_logs',
            recordId: $log->id,
            newValues: ['original_status' => $log->status],
            logType: 'warning'
        );

        return $this->deliver($webhook, $log->event, $log->payload ?? [], 1);
    }

    /**
     * Test a webhook by sending a ping.
     */
    public function test(Webhook $webhook): WebhookLog
    {
        return $this->deliver($webhook, 'webhook.test', [
            'message' => 'This is a test delivery from NKS Capital EMS.',
            'sent_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Deactivate and remove webhook.
     */
    public function delete(Webhook $webhook, int $actorId): void
    {
        AuditService::log(
            action: 'WEBHOOK_DELETED',
            tableName: 'webhooks',
            recordId: $webhook->id,
            oldValues: $webhook->toArray(),
            logType: 'warning'
        );

        $webhook->delete();
    }

    // ============================================================
    // BUILT-IN EVENT DISPATCHERS
    // ============================================================

    public function dispatchTimesheetApproved($timesheet): void
    {
        $this->dispatch('timesheet.approved', [
            'timesheet_id' => $timesheet->id,
            'employee_id' => $timesheet->user_id,
            'employee_name' => $timesheet->user?->full_name,
            'month_year' => $timesheet->month_year?->format('Y-m-d'),
            'total_hours' => (float) $timesheet->total_regular_hours + (float) $timesheet->total_overtime_hours,
            'approved_at' => $timesheet->level2_approved_at?->toIso8601String(),
        ]);
    }

    public function dispatchLeaveApproved($leave): void
    {
        $this->dispatch('leave.approved', [
            'leave_id' => $leave->id,
            'employee_id' => $leave->user_id,
            'leave_type' => $leave->leave_type,
            'start_date' => $leave->start_date?->toDateString(),
            'end_date' => $leave->end_date?->toDateString(),
            'days_taken' => (float) $leave->days_taken,
        ]);
    }

    public function dispatchEmployeeCreated($user): void
    {
        $this->dispatch('employee.created', [
            'employee_id' => $user->id,
            'employee_number' => $user->employee_number,
            'name' => $user->full_name,
            'email' => $user->email,
            'role' => $user->role,
            'employee_type' => $user->employee_type,
        ]);
    }

    public function dispatchAssetLoaned($asset): void
    {
        $this->dispatch('asset.loaned', [
            'asset_id' => $asset->id,
            'asset_tag' => $asset->asset_tag,
            'assigned_to' => $asset->current_assignee_id,
            'status' => $asset->status,
        ]);
    }

    public function dispatchContractExpiring($contract): void
    {
        $this->dispatch('contract.expiring', [
            'contract_id' => $contract->id,
            'employee_id' => $contract->user_id,
            'expiry_date' => $contract->expiry_date?->toDateString(),
            'days_until_expiry' => $contract->daysUntilExpiry(),
        ]);
    }

    public function dispatchOfferAccepted($offer): void
    {
        $this->dispatch('offer.accepted', [
            'offer_id' => $offer->id,
            'candidate_id' => $offer->candidate_id,
            'position' => $offer->position,
            'salary_offered' => (float) $offer->salary_offered,
            'start_date' => $offer->start_date?->toDateString(),
        ]);
    }
}