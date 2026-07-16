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

        // SSRF guard: never deliver to loopback/private/link-local targets
        // (checked at delivery time to also cover DNS rebinding after creation)
        if (self::resolvesToBlockedAddress($endpoint->url)) {
            Log::warning('Webhook delivery blocked: target resolves to a private address', [
                'endpoint_id' => (string) $endpoint->id,
                'url' => $endpoint->url,
            ]);
            $endpoint->increment('failure_count');

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

            if ($this->attempts() < $this->tries) {
                $this->release($this->backoff[min($this->attempts() - 1, count($this->backoff) - 1)]);
            }
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

    public static function resolvesToBlockedAddress(string $url): bool
    {
        $host = (string) parse_url($url, PHP_URL_HOST);

        if ($host === '') {
            return true;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : (array) (gethostbynamel($host) ?: []);

        // Unresolvable hostnames are allowed through: DNS may legitimately be
        // unavailable here and the actual connection will simply fail.
        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return true;
            }
        }

        return false;
    }
}
