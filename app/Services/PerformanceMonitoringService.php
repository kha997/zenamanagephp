<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PerformanceMonitoringService
{
    protected array $performanceThresholds = [
        'page_load_time' => 500, // ms
        'api_response_time' => 300, // ms
        'memory_usage' => 80, // percentage
        'database_query_time' => 100, // ms
        'cache_hit_ratio' => 90, // percentage
    ];

    protected array $performanceMetrics = [];

    public function __construct()
    {
        $this->initializeMetrics();
    }

    /**
     * Initialize performance metrics
     */
    protected function initializeMetrics(): void
    {
        $this->performanceMetrics = [
            'page_load_times' => [],
            'api_response_times' => [],
            'memory_usage' => [],
            'database_query_times' => [],
            'cache_hit_ratios' => [],
            'error_rates' => [],
            'throughput' => [],
        ];
    }

    /**
     * Record page load time
     */
    public function recordPageLoadTime(string $route, float $loadTime): void
    {
        $this->performanceMetrics['page_load_times'][] = [
            'route' => $route,
            'load_time' => $loadTime,
            'timestamp' => now(),
        ];

        // Log if exceeds threshold
        if ($loadTime > $this->performanceThresholds['page_load_time']) {
            Log::warning('Page load time exceeded threshold', [
                'route' => $route,
                'load_time' => $loadTime,
                'threshold' => $this->performanceThresholds['page_load_time'],
            ]);
        }

        // Store in cache for real-time monitoring
        $this->storeMetric('page_load_time', $route, $loadTime);
    }

    /**
     * Record API response time
     */
    public function recordApiResponseTime(string $endpoint, float $responseTime): void
    {
        $this->performanceMetrics['api_response_times'][] = [
            'endpoint' => $endpoint,
                'response_time' => $responseTime,
            'timestamp' => now(),
        ];

        // Log if exceeds threshold
        if ($responseTime > $this->performanceThresholds['api_response_time']) {
            Log::warning('API response time exceeded threshold', [
                'endpoint' => $endpoint,
                'response_time' => $responseTime,
                'threshold' => $this->performanceThresholds['api_response_time'],
            ]);
        }

        // Store in cache for real-time monitoring
        $this->storeMetric('api_response_time', $endpoint, $responseTime);
    }

    /**
     * Record memory usage
     */
    public function recordMemoryUsage(float $memoryUsage): void
    {
        $memoryPercentage = ($memoryUsage / memory_get_peak_usage(true)) * 100;
        
        $this->performanceMetrics['memory_usage'][] = [
            'memory_usage' => $memoryUsage,
            'memory_percentage' => $memoryPercentage,
            'timestamp' => now(),
        ];

        // Log if exceeds threshold
        if ($memoryPercentage > $this->performanceThresholds['memory_usage']) {
            Log::warning('Memory usage exceeded threshold', [
                'memory_usage' => $memoryUsage,
                'memory_percentage' => $memoryPercentage,
                'threshold' => $this->performanceThresholds['memory_usage'],
            ]);
        }

        // Store in cache for real-time monitoring
        $this->storeMetric('memory_usage', 'system', $memoryPercentage);
    }

    /**
     * Record database query time
     */
    public function recordDatabaseQueryTime(string $query, float $queryTime): void
    {
        $this->performanceMetrics['database_query_times'][] = [
            'query' => $query,
                'query_time' => $queryTime,
            'timestamp' => now(),
        ];

        // Log if exceeds threshold
        if ($queryTime > $this->performanceThresholds['database_query_time']) {
            Log::warning('Database query time exceeded threshold', [
                'query' => $query,
                'query_time' => $queryTime,
                'threshold' => $this->performanceThresholds['database_query_time'],
            ]);
        }

        // Store in cache for real-time monitoring
        $this->storeMetric('database_query_time', $query, $queryTime);
    }

    /**
     * Record cache hit ratio
     */
    public function recordCacheHitRatio(string $cacheKey, bool $hit): void
    {
        $this->performanceMetrics['cache_hit_ratios'][] = [
            'cache_key' => $cacheKey,
            'hit' => $hit,
            'timestamp' => now(),
        ];

        // Store in cache for real-time monitoring
        $this->storeMetric('cache_hit_ratio', $cacheKey, $hit ? 100 : 0);
    }

    /**
     * Record error rate
     */
    public function recordError(string $error, string $context = ''): void
    {
        $this->performanceMetrics['error_rates'][] = [
            'error' => $error,
            'context' => $context,
            'timestamp' => now(),
        ];

        // Store in cache for real-time monitoring
        $this->storeMetric('error_rate', $context, 1);
    }

    /**
     * Record throughput
     */
    public function recordThroughput(string $operation, int $count): void
    {
        $this->performanceMetrics['throughput'][] = [
            'operation' => $operation,
            'count' => $count,
            'timestamp' => now(),
        ];

        // Store in cache for real-time monitoring
        $this->storeMetric('throughput', $operation, $count);
    }

    /**
     * Store metric in cache for real-time monitoring
     */
    protected function storeMetric(string $type, string $key, mixed $value): void
    {
        $cacheKey = "performance_metric_{$type}_{$key}_" . now()->format('Y-m-d-H-i');
        Cache::put($cacheKey, $value, 300); // 5 minutes
    }

    /**
     * Get performance statistics
     */
    public function getPerformanceStats(): array
    {
        $stats = [];

        // Page load times
        $pageLoadTimes = collect($this->performanceMetrics['page_load_times'])
            ->pluck('load_time')
            ->toArray();
        
        if (!empty($pageLoadTimes)) {
            $stats['page_load_time'] = [
                'avg' => round(array_sum($pageLoadTimes) / count($pageLoadTimes), 2),
                'min' => min($pageLoadTimes),
                'max' => max($pageLoadTimes),
                'p95' => $this->calculatePercentile($pageLoadTimes, 95),
                'count' => count($pageLoadTimes),
            ];
        }

        // API response times
        $apiResponseTimes = collect($this->performanceMetrics['api_response_times'])
            ->pluck('response_time')
            ->toArray();
        
        if (!empty($apiResponseTimes)) {
            $stats['api_response_time'] = [
                'avg' => round(array_sum($apiResponseTimes) / count($apiResponseTimes), 2),
                'min' => min($apiResponseTimes),
                'max' => max($apiResponseTimes),
                'p95' => $this->calculatePercentile($apiResponseTimes, 95),
                'count' => count($apiResponseTimes),
            ];
        }

        // Memory usage
        $memoryUsage = collect($this->performanceMetrics['memory_usage'])
            ->pluck('memory_percentage')
            ->toArray();
        
        if (!empty($memoryUsage)) {
            $stats['memory_usage'] = [
                'avg' => round(array_sum($memoryUsage) / count($memoryUsage), 2),
                'min' => min($memoryUsage),
                'max' => max($memoryUsage),
                'current' => memory_get_usage(true),
                'peak' => memory_get_peak_usage(true),
            ];
        }

        // Database query times
        $queryTimes = collect($this->performanceMetrics['database_query_times'])
            ->pluck('query_time')
            ->toArray();
        
        if (!empty($queryTimes)) {
            $stats['database_query_time'] = [
                'avg' => round(array_sum($queryTimes) / count($queryTimes), 2),
                'min' => min($queryTimes),
                'max' => max($queryTimes),
                'p95' => $this->calculatePercentile($queryTimes, 95),
                'count' => count($queryTimes),
            ];
        }

        // Cache hit ratios
        $cacheHitRatios = collect($this->performanceMetrics['cache_hit_ratios'])
            ->pluck('hit')
            ->toArray();
        
        if (!empty($cacheHitRatios)) {
            $hitCount = array_sum($cacheHitRatios);
            $totalCount = count($cacheHitRatios);
            $stats['cache_hit_ratio'] = [
                'hit_ratio' => round(($hitCount / $totalCount) * 100, 2),
                'hit_count' => $hitCount,
                'miss_count' => $totalCount - $hitCount,
                'total_count' => $totalCount,
            ];
        }

        // Error rates
        $errorCount = count($this->performanceMetrics['error_rates']);
        $totalRequests = $errorCount + count($this->performanceMetrics['page_load_times']) + count($this->performanceMetrics['api_response_times']);
        
        if ($totalRequests > 0) {
            $stats['error_rate'] = [
                'error_count' => $errorCount,
                'total_requests' => $totalRequests,
                'error_percentage' => round(($errorCount / $totalRequests) * 100, 2),
            ];
        }

        // Throughput
        $throughput = collect($this->performanceMetrics['throughput'])
            ->groupBy('operation')
            ->map(function ($items) {
                return $items->sum('count');
            })
            ->toArray();
        
        if (!empty($throughput)) {
            $stats['throughput'] = $throughput;
        }

        return $stats;
    }

    /**
     * Get performance recommendations
     */
    public function getPerformanceRecommendations(): array
    {
        $recommendations = [];
        $stats = $this->getPerformanceStats();

        // Page load time recommendations
        if (isset($stats['page_load_time']) && $stats['page_load_time']['avg'] > $this->performanceThresholds['page_load_time']) {
            $recommendations[] = [
                'type' => 'page_load_time',
                'priority' => 'high',
                'message' => 'Page load time is above threshold. Consider optimizing assets, enabling caching, or reducing database queries.',
                'current_value' => $stats['page_load_time']['avg'],
                'threshold' => $this->performanceThresholds['page_load_time'],
            ];
        }

        // API response time recommendations
        if (isset($stats['api_response_time']) && $stats['api_response_time']['avg'] > $this->performanceThresholds['api_response_time']) {
            $recommendations[] = [
                'type' => 'api_response_time',
                'priority' => 'high',
                'message' => 'API response time is above threshold. Consider optimizing queries, adding indexes, or implementing caching.',
                'current_value' => $stats['api_response_time']['avg'],
                'threshold' => $this->performanceThresholds['api_response_time'],
            ];
        }

        // Memory usage recommendations
        if (isset($stats['memory_usage']) && $stats['memory_usage']['avg'] > $this->performanceThresholds['memory_usage']) {
            $recommendations[] = [
                'type' => 'memory_usage',
                'priority' => 'medium',
                'message' => 'Memory usage is above threshold. Consider optimizing memory usage, reducing object creation, or implementing garbage collection.',
                'current_value' => $stats['memory_usage']['avg'],
                'threshold' => $this->performanceThresholds['memory_usage'],
            ];
        }

        // Database query time recommendations
        if (isset($stats['database_query_time']) && $stats['database_query_time']['avg'] > $this->performanceThresholds['database_query_time']) {
            $recommendations[] = [
                'type' => 'database_query_time',
                'priority' => 'high',
                'message' => 'Database query time is above threshold. Consider adding indexes, optimizing queries, or implementing query caching.',
                'current_value' => $stats['database_query_time']['avg'],
                'threshold' => $this->performanceThresholds['database_query_time'],
            ];
        }

        // Cache hit ratio recommendations
        if (isset($stats['cache_hit_ratio']) && $stats['cache_hit_ratio']['hit_ratio'] < $this->performanceThresholds['cache_hit_ratio']) {
            $recommendations[] = [
                'type' => 'cache_hit_ratio',
                'priority' => 'medium',
                'message' => 'Cache hit ratio is below threshold. Consider implementing more caching strategies or optimizing cache keys.',
                'current_value' => $stats['cache_hit_ratio']['hit_ratio'],
                'threshold' => $this->performanceThresholds['cache_hit_ratio'],
            ];
        }

        return $recommendations;
    }

    /**
     * Get performance thresholds
     */
    public function getPerformanceThresholds(): array
    {
        return $this->performanceThresholds;
    }

    /**
     * Set performance thresholds
     */
    public function setPerformanceThresholds(array $thresholds): void
    {
        $this->performanceThresholds = array_merge($this->performanceThresholds, $thresholds);
    }

    /**
     * Calculate percentile
     */
    protected function calculatePercentile(array $values, int $percentile): float
    {
        sort($values);
        $index = ($percentile / 100) * (count($values) - 1);
        $lower = floor($index);
        $upper = ceil($index);
        
        if ($lower === $upper) {
            return $values[$lower];
        }
        
        $weight = $index - $lower;
        return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
    }

    /**
     * Get real-time performance metrics
     */
    public function getRealTimeMetrics(): array
    {
        $metrics = [];
        
        // Get cached metrics from last 5 minutes
        $cacheKeys = Cache::get('performance_metrics_keys', []);
        
        foreach ($cacheKeys as $key) {
            $value = Cache::get($key);
            if ($value !== null) {
                $metrics[] = [
                    'key' => $key,
                    'value' => $value,
                    'timestamp' => now(),
                ];
            }
        }
        
        return $metrics;
    }

    /**
     * Clear performance metrics
     */
    public function clearMetrics(): void
    {
        $this->initializeMetrics();
        Cache::forget('performance_metrics_keys');
    }

    /**
     * Export performance data
     */
    public function exportPerformanceData(): array
    {
        return [
            'timestamp' => now(),
            'metrics' => $this->performanceMetrics,
            'stats' => $this->getPerformanceStats(),
            'recommendations' => $this->getPerformanceRecommendations(),
            'thresholds' => $this->getPerformanceThresholds(),
        ];
    }
}
