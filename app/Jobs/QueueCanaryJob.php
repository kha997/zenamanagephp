<?php declare(strict_types=1);

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * GAP-049 queue-worker liveness canary. Proves a real worker process
 * executed a real queued job — not claimed by the HTTP readiness endpoint.
 */
class QueueCanaryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly string $probeId)
    {
    }

    public function handle(): void
    {
        Cache::put($this->markerKey(), true, 60);
    }

    public function markerKey(): string
    {
        return "gap049-queue-canary-{$this->probeId}";
    }
}
