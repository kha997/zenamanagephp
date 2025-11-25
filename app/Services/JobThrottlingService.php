<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Job Throttling Service
 * 
 * PR: Job idempotency
 * 
 * Throttles jobs per tenant to prevent queue overload.
 */
class JobThrottlingService
{
    /**
     * Default throttling limits
     */
    private const DEFAULT_MAX_JOBS_PER_TENANT = 100; // per minute
    private const DEFAULT_MAX_JOBS_PER_QUEUE = 1000; // per minute
    private const THROTTLE_WINDOW = 60; // 1 minute

    /**
     * Check if tenant can dispatch more jobs
     * 
     * @param string|null $tenantId Tenant ID
     * @param string $queue Queue name
     * @return bool True if can dispatch
     */
    public function canDispatch(?string $tenantId, string $queue = 'default'): bool
    {
        // Check tenant limit
        if ($tenantId) {
            $tenantCount = $this->getTenantJobCount($tenantId);
            $tenantLimit = config('queue.throttling.max_jobs_per_tenant', self::DEFAULT_MAX_JOBS_PER_TENANT);

            if ($tenantCount >= $tenantLimit) {
                Log::warning('Job throttling: tenant limit reached', [
                    'tenant_id' => $tenantId,
                    'count' => $tenantCount,
                    'limit' => $tenantLimit,
                ]);

                return false;
            }
        }

        // Check queue limit
        $queueCount = $this->getQueueJobCount($queue);
        $queueLimit = config('queue.throttling.max_jobs_per_queue', self::DEFAULT_MAX_JOBS_PER_QUEUE);

        if ($queueCount >= $queueLimit) {
            Log::warning('Job throttling: queue limit reached', [
                'queue' => $queue,
                'count' => $queueCount,
                'limit' => $queueLimit,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Record job dispatch
     */
    public function recordDispatch(?string $tenantId, string $queue = 'default'): void
    {
        $now = now()->timestamp;
        $windowStart = $now - ($now % self::THROTTLE_WINDOW);

        // Increment tenant counter
        if ($tenantId) {
            $tenantKey = "job_throttle:tenant:{$tenantId}:{$windowStart}";
            Cache::increment($tenantKey, 1);
            Cache::put($tenantKey, Cache::get($tenantKey, 0), self::THROTTLE_WINDOW);
        }

        // Increment queue counter
        $queueKey = "job_throttle:queue:{$queue}:{$windowStart}";
        Cache::increment($queueKey, 1);
        Cache::put($queueKey, Cache::get($queueKey, 0), self::THROTTLE_WINDOW);
    }

    /**
     * Get tenant job count for current window
     */
    protected function getTenantJobCount(string $tenantId): int
    {
        $now = now()->timestamp;
        $windowStart = $now - ($now % self::THROTTLE_WINDOW);
        $key = "job_throttle:tenant:{$tenantId}:{$windowStart}";

        return (int) Cache::get($key, 0);
    }

    /**
     * Get queue job count for current window
     */
    protected function getQueueJobCount(string $queue): int
    {
        $now = now()->timestamp;
        $windowStart = $now - ($now % self::THROTTLE_WINDOW);
        $key = "job_throttle:queue:{$queue}:{$windowStart}";

        return (int) Cache::get($key, 0);
    }

    /**
     * Get throttling statistics
     */
    public function getStats(?string $tenantId = null): array
    {
        $stats = [];

        if ($tenantId) {
            $stats['tenant'] = [
                'current_count' => $this->getTenantJobCount($tenantId),
                'limit' => config('queue.throttling.max_jobs_per_tenant', self::DEFAULT_MAX_JOBS_PER_TENANT),
            ];
        }

        $stats['queues'] = [];
        $queues = config('queue.connections', []);
        
        foreach (array_keys($queues) as $queue) {
            $stats['queues'][$queue] = [
                'current_count' => $this->getQueueJobCount($queue),
                'limit' => config('queue.throttling.max_jobs_per_queue', self::DEFAULT_MAX_JOBS_PER_QUEUE),
            ];
        }

        return $stats;
    }
}

