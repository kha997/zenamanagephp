<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

/**
 * Observability Service
 * 
 * Centralized service for metrics collection and observability.
 * Records HTTP requests, errors, and performance metrics.
 * 
 * Part of PR: Observability 3-in-1 (Logs, Metrics, Traces)
 */
class ObservabilityService
{
    /**
     * Record HTTP request metrics
     * 
     * @param string $method HTTP method
     * @param string $path Request path
     * @param int $statusCode Response status code
     * @param float $latencyMs Latency in milliseconds
     * @param string|null $tenantId Tenant ID
     * @param string|null $userId User ID
     * @param string|null $requestId Request ID for correlation
     */
    public function recordHttpRequest(
        string $method,
        string $path,
        int $statusCode,
        float $latencyMs,
        ?string $tenantId = null,
        ?string $userId = null,
        ?string $requestId = null
    ): void {
        // Record in cache for metrics aggregation
        $key = "metrics:http:{$method}:{$path}";
        $this->incrementCounter($key, [
            'status_code' => $statusCode,
            'tenant_id' => $tenantId,
        ]);

        // Record latency
        $latencyKey = "metrics:http:latency:{$method}:{$path}";
        $this->recordLatency($latencyKey, $latencyMs, [
            'tenant_id' => $tenantId,
        ]);

        // Log slow requests
        if ($latencyMs > 1000) { // > 1 second
            Log::warning('Slow HTTP request detected', [
                'method' => $method,
                'path' => $path,
                'status_code' => $statusCode,
                'latency_ms' => $latencyMs,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'request_id' => $requestId,
            ]);
        }

        // Record error if status >= 400
        if ($statusCode >= 400) {
            $this->recordError('http_error', [
                'method' => $method,
                'path' => $path,
                'status_code' => $statusCode,
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'request_id' => $requestId,
            ]);
        }
    }

    /**
     * Record error
     */
    public function recordError(string $errorType, array $context = []): void
    {
        $key = "metrics:errors:{$errorType}";
        $this->incrementCounter($key, $context);

        Log::error("Error recorded: {$errorType}", $context);
    }

    /**
     * Record latency metric
     */
    protected function recordLatency(string $key, float $latencyMs, array $tags = []): void
    {
        // Store latency values for percentile calculation
        $cacheKey = "{$key}:values";
        $values = Cache::get($cacheKey, []);
        $values[] = $latencyMs;

        // Keep only last 1000 values
        if (count($values) > 1000) {
            $values = array_slice($values, -1000);
        }

        Cache::put($cacheKey, $values, 3600); // 1 hour TTL
    }

    /**
     * Increment counter
     */
    protected function incrementCounter(string $key, array $tags = []): void
    {
        $fullKey = $key . ':' . md5(json_encode($tags));
        $current = Cache::get($fullKey, 0);
        Cache::put($fullKey, $current + 1, 3600); // 1 hour TTL
    }

    /**
     * Get metrics for a key
     */
    public function getMetrics(string $key): array
    {
        $values = Cache::get("{$key}:values", []);
        
        if (empty($values)) {
            return [
                'count' => 0,
                'p50' => 0,
                'p95' => 0,
                'p99' => 0,
                'avg' => 0,
                'min' => 0,
                'max' => 0,
            ];
        }

        sort($values);
        $count = count($values);

        return [
            'count' => $count,
            'p50' => $values[(int)($count * 0.5)] ?? 0,
            'p95' => $values[(int)($count * 0.95)] ?? 0,
            'p99' => $values[(int)($count * 0.99)] ?? 0,
            'avg' => array_sum($values) / $count,
            'min' => $values[0] ?? 0,
            'max' => $values[$count - 1] ?? 0,
        ];
    }
}
