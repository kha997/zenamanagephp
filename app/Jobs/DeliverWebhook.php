<?php declare(strict_types=1);

namespace App\Jobs;

use App\Models\WebhookEndpoint;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 60, 300];

    public function __construct(
        public readonly string $endpointId,
        /** @var array<string, mixed> */
        public readonly array $payload,
    ) {
    }

    public function handle(): void
    {
        $endpoint = WebhookEndpoint::query()->find($this->endpointId);

        if (!$endpoint instanceof WebhookEndpoint || !$endpoint->is_active) {
            return;
        }

        $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $signature = hash_hmac('sha256', (string) $body, (string) $endpoint->secret);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Zena-Event' => (string) ($this->payload['event_key'] ?? ''),
                    'X-Zena-Signature' => 'sha256=' . $signature,
                ])
                ->withBody((string) $body, 'application/json')
                ->post($endpoint->url);

            if ($response->successful()) {
                $endpoint->forceFill([
                    'last_delivered_at' => now(),
                    'failure_count' => 0,
                ])->save();

                return;
            }

            $endpoint->increment('failure_count');
            $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);
        } catch (\Throwable $exception) {
            $endpoint->increment('failure_count');

            Log::warning('Webhook delivery failed', [
                'endpoint_id' => (string) $endpoint->id,
                'url' => $endpoint->url,
                'error' => $exception->getMessage(),
            ]);

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);
            }
        }
    }
}
