<?php

namespace App\Jobs;

use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DispatchWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60];

    public int $timeout = 30;

    /**
     * Id of the log row created on the most recent attempt. Persisted on the
     * job object so the failed() callback knows which row to mark as
     * dead_letter after the queue exhausts retries.
     */
    public ?int $lastLogId = null;

    public function __construct(
        public int $webhookId,
        public string $event,
        public array $payload,
    ) {}

    public function handle(WebhookService $service): void
    {
        $webhook = Webhook::find($this->webhookId);

        if (!$webhook || !$webhook->is_active) {
            return;
        }

        $log = $service->deliverOnce($webhook, $this->event, $this->payload, $this->attempts());
        $this->lastLogId = $log->id;

        if ($log->status !== 'success') {
            throw new \RuntimeException(
                "Webhook delivery failed for {$webhook->name}: " . ($log->error_message ?? 'Non-2xx response')
            );
        }
    }

    public function failed(\Throwable $e): void
    {
        if ($this->lastLogId) {
            WebhookLog::where('id', $this->lastLogId)->update(['status' => 'dead_letter']);
        }
    }
}