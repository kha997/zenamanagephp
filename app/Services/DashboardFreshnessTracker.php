<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Dashboard Freshness Tracker
 * 
 * PR: SLO/SLA nội bộ
 * 
 * Tracks cache freshness after mutations to ensure dashboard updates within SLO target (≤ 5s).
 */
class DashboardFreshnessTracker
{
    private const FRESHNESS_TARGET = 5000; // 5 seconds in milliseconds
    private const CACHE_KEY_PREFIX = 'freshness_tracking:';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Record mutation timestamp
     * 
     * @param string $mutationType Mutation type (e.g., 'task.create', 'project.update')
     * @param string|null $resourceId Resource ID (optional)
     * @param string|null $tenantId Tenant ID (optional)
     */
    public function recordMutation(
        string $mutationType,
        ?string $resourceId = null,
        ?string $tenantId = null
    ): void {
        $key = $this->getMutationKey($mutationType, $resourceId, $tenantId);
        $timestamp = now()->timestamp;

        Cache::put($key, $timestamp, self::CACHE_TTL);

        Log::debug('Dashboard mutation recorded', [
            'mutation_type' => $mutationType,
            'resource_id' => $resourceId,
            'tenant_id' => $tenantId,
            'timestamp' => $timestamp,
        ]);
    }

    /**
     * Record cache invalidation timestamp
     * 
     * @param string $mutationType Mutation type
     * @param string|null $resourceId Resource ID (optional)
     * @param string|null $tenantId Tenant ID (optional)
     */
    public function recordInvalidation(
        string $mutationType,
        ?string $resourceId = null,
        ?string $tenantId = null
    ): void {
        $key = $this->getInvalidationKey($mutationType, $resourceId, $tenantId);
        $timestamp = now()->timestamp;

        Cache::put($key, $timestamp, self::CACHE_TTL);

        Log::debug('Cache invalidation recorded', [
            'mutation_type' => $mutationType,
            'resource_id' => $resourceId,
            'tenant_id' => $tenantId,
            'timestamp' => $timestamp,
        ]);
    }

    /**
     * Record dashboard refresh timestamp
     * 
     * @param string $mutationType Mutation type
     * @param string|null $resourceId Resource ID (optional)
     * @param string|null $tenantId Tenant ID (optional)
     * @return float|null Freshness in milliseconds, or null if mutation not found
     */
    public function recordRefresh(
        string $mutationType,
        ?string $resourceId = null,
        ?string $tenantId = null
    ): ?float {
        $mutationKey = $this->getMutationKey($mutationType, $resourceId, $tenantId);
        $mutationTimestamp = Cache::get($mutationKey);

        if (!$mutationTimestamp) {
            Log::warning('Dashboard refresh recorded but mutation not found', [
                'mutation_type' => $mutationType,
                'resource_id' => $resourceId,
                'tenant_id' => $tenantId,
            ]);
            return null;
        }

        $refreshTimestamp = now()->timestamp;
        $freshness = ($refreshTimestamp - $mutationTimestamp) * 1000; // Convert to milliseconds

        // Store freshness metric
        $this->storeFreshnessMetric($mutationType, $freshness);

        // Log if exceeds target
        if ($freshness > self::FRESHNESS_TARGET) {
            Log::warning('Dashboard freshness exceeded target', [
                'mutation_type' => $mutationType,
                'resource_id' => $resourceId,
                'tenant_id' => $tenantId,
                'freshness_ms' => $freshness,
                'target_ms' => self::FRESHNESS_TARGET,
                'exceeded_by' => $freshness - self::FRESHNESS_TARGET,
            ]);
        }

        // Clean up tracking keys
        Cache::forget($mutationKey);
        Cache::forget($this->getInvalidationKey($mutationType, $resourceId, $tenantId));

        return $freshness;
    }

    /**
     * Get current freshness metrics
     * 
     * @return array Freshness metrics by mutation type
     */
    public function getFreshnessMetrics(): array
    {
        $metrics = Cache::get('freshness_metrics', []);

        // Calculate statistics
        $stats = [];
        foreach ($metrics as $mutationType => $values) {
            if (empty($values)) {
                continue;
            }

            sort($values);
            $count = count($values);
            $p50Index = (int) floor($count * 0.5);
            $p95Index = (int) floor($count * 0.95);
            $p99Index = (int) floor($count * 0.99);

            $stats[$mutationType] = [
                'count' => $count,
                'p50' => $values[$p50Index] ?? 0,
                'p95' => $values[$p95Index] ?? 0,
                'p99' => $values[$p99Index] ?? 0,
                'max' => max($values),
                'min' => min($values),
                'avg' => array_sum($values) / $count,
            ];
        }

        return $stats;
    }

    /**
     * Get freshness violations (exceeding target)
     * 
     * @return array Violations
     */
    public function getViolations(): array
    {
        $metrics = $this->getFreshnessMetrics();
        $violations = [];

        foreach ($metrics as $mutationType => $stats) {
            if ($stats['p95'] > self::FRESHNESS_TARGET) {
                $violations[] = [
                    'mutation_type' => $mutationType,
                    'p95' => $stats['p95'],
                    'target' => self::FRESHNESS_TARGET,
                    'exceeded_by' => $stats['p95'] - self::FRESHNESS_TARGET,
                    'percentage' => ($stats['p95'] / self::FRESHNESS_TARGET) * 100,
                ];
            }
        }

        return $violations;
    }

    /**
     * Store freshness metric
     */
    private function storeFreshnessMetric(string $mutationType, float $freshness): void
    {
        $key = 'freshness_metrics';
        $metrics = Cache::get($key, []);

        if (!isset($metrics[$mutationType])) {
            $metrics[$mutationType] = [];
        }

        $metrics[$mutationType][] = $freshness;

        // Keep only last 1000 values per mutation type
        if (count($metrics[$mutationType]) > 1000) {
            $metrics[$mutationType] = array_slice($metrics[$mutationType], -1000);
        }

        Cache::put($key, $metrics, self::CACHE_TTL);
    }

    /**
     * Get mutation cache key
     */
    private function getMutationKey(
        string $mutationType,
        ?string $resourceId = null,
        ?string $tenantId = null
    ): string {
        $parts = [self::CACHE_KEY_PREFIX . 'mutation', $mutationType];
        
        if ($tenantId) {
            $parts[] = "tenant:{$tenantId}";
        }
        
        if ($resourceId) {
            $parts[] = "resource:{$resourceId}";
        }

        return implode(':', $parts);
    }

    /**
     * Get invalidation cache key
     */
    private function getInvalidationKey(
        string $mutationType,
        ?string $resourceId = null,
        ?string $tenantId = null
    ): string {
        $parts = [self::CACHE_KEY_PREFIX . 'invalidation', $mutationType];
        
        if ($tenantId) {
            $parts[] = "tenant:{$tenantId}";
        }
        
        if ($resourceId) {
            $parts[] = "resource:{$resourceId}";
        }

        return implode(':', $parts);
    }
}

