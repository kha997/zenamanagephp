<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

/**
 * PerformanceMonitoringService - Service cho performance monitoring
 */
class PerformanceMonitoringService
{
    private array $monitoringConfig;

    public function __construct()
    {
        $this->monitoringConfig = [
            'enabled' => config('monitoring.enabled', true),
            'slow_query_threshold' => config('monitoring.slow_query_threshold', 1000),
            'memory_threshold' => config('monitoring.memory_threshold', 128 * 1024 * 1024), // 128MB
            'response_time_threshold' => config('monitoring.response_time_threshold', 2000), // 2 seconds
            'cache_hit_rate_threshold' => config('monitoring.cache_hit_rate_threshold', 80), // 80%
            'enable_metrics_collection' => config('monitoring.enable_metrics_collection', true),
            'metrics_retention_days' => config('monitoring.metrics_retention_days', 30)
        ];
    }

    /**
     * Monitor request performance
     */
    public function monitorRequest(Request $request, callable $callback): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        $startPeakMemory = memory_get_peak_usage(true);
        
        $metrics = [
            'request_id' => uniqid('req_'),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $request->user()?->id,
            'start_time' => $startTime,
            'start_memory' => $startMemory,
            'start_peak_memory' => $startPeakMemory
        ];
        
        try {
            $result = $callback();
            $success = true;
            $error = null;
        } catch (\Exception $e) {
            $result = null;
            $success = false;
            $error = $e->getMessage();
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $endPeakMemory = memory_get_peak_usage(true);
        
        $metrics = array_merge($metrics, [
            'success' => $success,
            'execution_time' => ($endTime - $startTime) * 1000,
            'memory_used' => $endMemory - $startMemory,
            'peak_memory' => $endPeakMemory,
            'end_time' => $endTime,
            'error' => $error,
            'response_size' => $result ? strlen(json_encode($result)) : 0
        ]);
        
        // Store metrics
        if ($this->monitoringConfig['enable_metrics_collection']) {
            $this->storeMetrics($metrics);
        }
        
        // Check for performance issues
        $this->checkPerformanceIssues($metrics);
        
        return $metrics;
    }

    /**
     * Monitor database queries
     */
    public function monitorQueries(callable $callback): array
    {
        $startTime = microtime(true);
        $queryCount = 0;
        $slowQueries = [];
        
        // Enable query logging
        DB::enableQueryLog();
        
        try {
            $result = $callback();
            $success = true;
            $error = null;
        } catch (\Exception $e) {
            $result = null;
            $success = false;
            $error = $e->getMessage();
        }
        
        $endTime = microtime(true);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        
        $totalExecutionTime = 0;
        foreach ($queries as $query) {
            $queryCount++;
            $executionTime = $query['time'];
            $totalExecutionTime += $executionTime;
            
            if ($executionTime > $this->monitoringConfig['slow_query_threshold']) {
                $slowQueries[] = [
                    'query' => $query['query'],
                    'bindings' => $query['bindings'],
                    'time' => $executionTime
                ];
            }
        }
        
        $metrics = [
            'success' => $success,
            'query_count' => $queryCount,
            'total_execution_time' => $totalExecutionTime,
            'average_execution_time' => $queryCount > 0 ? $totalExecutionTime / $queryCount : 0,
            'slow_queries' => $slowQueries,
            'slow_query_count' => count($slowQueries),
            'error' => $error
        ];
        
        // Store query metrics
        if ($this->monitoringConfig['enable_metrics_collection']) {
            $this->storeQueryMetrics($metrics);
        }
        
        return $metrics;
    }

    /**
     * Monitor cache performance
     */
    public function monitorCache(callable $callback): array
    {
        $startTime = microtime(true);
        $cacheHits = 0;
        $cacheMisses = 0;
        
        try {
            $result = $callback();
            $success = true;
            $error = null;
        } catch (\Exception $e) {
            $result = null;
            $success = false;
            $error = $e->getMessage();
        }
        
        $endTime = microtime(true);
        
        // Get cache statistics
        $cacheStats = $this->getCacheStatistics();
        
        $metrics = [
            'success' => $success,
            'execution_time' => ($endTime - $startTime) * 1000,
            'cache_hits' => $cacheHits,
            'cache_misses' => $cacheMisses,
            'cache_hit_rate' => $cacheStats['hit_rate'] ?? 0,
            'cache_size' => $cacheStats['cache_size'] ?? 0,
            'error' => $error
        ];
        
        // Store cache metrics
        if ($this->monitoringConfig['enable_metrics_collection']) {
            $this->storeCacheMetrics($metrics);
        }
        
        return $metrics;
    }

    /**
     * Get system performance metrics
     */
    public function getSystemMetrics(): array
    {
        $metrics = [];
        
        try {
            // Memory usage
            $metrics['memory'] = [
                'current_usage' => memory_get_usage(true),
                'current_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_usage' => memory_get_peak_usage(true),
                'peak_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                'limit' => ini_get('memory_limit')
            ];
            
            // CPU usage (if available)
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $metrics['cpu'] = [
                    'load_1min' => $load[0],
                    'load_5min' => $load[1],
                    'load_15min' => $load[2]
                ];
            }
            
            // Database connections
            $dbStats = $this->getDatabaseStatistics();
            $metrics['database'] = $dbStats;
            
            // Cache statistics
            $cacheStats = $this->getCacheStatistics();
            $metrics['cache'] = $cacheStats;
            
            // Disk usage
            $diskStats = $this->getDiskStatistics();
            $metrics['disk'] = $diskStats;
            
        } catch (\Exception $e) {
            Log::error('Failed to get system metrics', [
                'error' => $e->getMessage()
            ]);
            $metrics['error'] = $e->getMessage();
        }
        
        return $metrics;
    }

    /**
     * Get performance trends
     */
    public function getPerformanceTrends(int $days = 7): array
    {
        $trends = [];
        
        try {
            // Get request metrics trends
            $trends['requests'] = $this->getRequestTrends($days);
            
            // Get query metrics trends
            $trends['queries'] = $this->getQueryTrends($days);
            
            // Get cache metrics trends
            $trends['cache'] = $this->getCacheTrends($days);
            
            // Get error trends
            $trends['errors'] = $this->getErrorTrends($days);
            
        } catch (\Exception $e) {
            Log::error('Failed to get performance trends', [
                'error' => $e->getMessage()
            ]);
            $trends['error'] = $e->getMessage();
        }
        
        return $trends;
    }

    /**
     * Get performance alerts
     */
    public function getPerformanceAlerts(): array
    {
        $alerts = [];
        
        try {
            $systemMetrics = $this->getSystemMetrics();
            
            // Memory alerts
            if ($systemMetrics['memory']['current_usage_mb'] > 100) {
                $alerts[] = [
                    'type' => 'memory',
                    'level' => 'warning',
                    'message' => 'High memory usage detected',
                    'value' => $systemMetrics['memory']['current_usage_mb'] . 'MB'
                ];
            }
            
            // Database alerts
            if ($systemMetrics['database']['slow_queries'] > 10) {
                $alerts[] = [
                    'type' => 'database',
                    'level' => 'warning',
                    'message' => 'High number of slow queries',
                    'value' => $systemMetrics['database']['slow_queries']
                ];
            }
            
            // Cache alerts
            if ($systemMetrics['cache']['hit_rate'] < $this->monitoringConfig['cache_hit_rate_threshold']) {
                $alerts[] = [
                    'type' => 'cache',
                    'level' => 'warning',
                    'message' => 'Low cache hit rate',
                    'value' => $systemMetrics['cache']['hit_rate'] . '%'
                ];
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to get performance alerts', [
                'error' => $e->getMessage()
            ]);
            $alerts[] = [
                'type' => 'system',
                'level' => 'error',
                'message' => 'Failed to get performance alerts',
                'value' => $e->getMessage()
            ];
        }
        
        return $alerts;
    }

    /**
     * Store metrics
     */
    private function storeMetrics(array $metrics): void
    {
        try {
            $key = 'metrics:request:' . date('Y-m-d-H') . ':' . $metrics['request_id'];
            Cache::put($key, $metrics, $this->monitoringConfig['metrics_retention_days'] * 24 * 3600);
        } catch (\Exception $e) {
            Log::error('Failed to store metrics', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Store query metrics
     */
    private function storeQueryMetrics(array $metrics): void
    {
        try {
            $key = 'metrics:query:' . date('Y-m-d-H') . ':' . uniqid();
            Cache::put($key, $metrics, $this->monitoringConfig['metrics_retention_days'] * 24 * 3600);
        } catch (\Exception $e) {
            Log::error('Failed to store query metrics', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Store cache metrics
     */
    private function storeCacheMetrics(array $metrics): void
    {
        try {
            $key = 'metrics:cache:' . date('Y-m-d-H') . ':' . uniqid();
            Cache::put($key, $metrics, $this->monitoringConfig['metrics_retention_days'] * 24 * 3600);
        } catch (\Exception $e) {
            Log::error('Failed to store cache metrics', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check for performance issues
     */
    private function checkPerformanceIssues(array $metrics): void
    {
        $issues = [];
        
        // Check execution time
        if ($metrics['execution_time'] > $this->monitoringConfig['response_time_threshold']) {
            $issues[] = 'Slow response time: ' . $metrics['execution_time'] . 'ms';
        }
        
        // Check memory usage
        if ($metrics['memory_used'] > $this->monitoringConfig['memory_threshold']) {
            $issues[] = 'High memory usage: ' . round($metrics['memory_used'] / 1024 / 1024, 2) . 'MB';
        }
        
        // Log issues
        if (!empty($issues)) {
            Log::warning('Performance issues detected', [
                'request_id' => $metrics['request_id'],
                'issues' => $issues,
                'metrics' => $metrics
            ]);
        }
    }

    /**
     * Get database statistics
     */
    private function getDatabaseStatistics(): array
    {
        try {
            $stats = DB::select("SHOW STATUS LIKE 'Slow_queries'");
            $slowQueries = $stats[0]->Value ?? 0;
            
            $stats = DB::select("SHOW STATUS LIKE 'Queries'");
            $totalQueries = $stats[0]->Value ?? 0;
            
            return [
                'slow_queries' => $slowQueries,
                'total_queries' => $totalQueries,
                'slow_query_rate' => $totalQueries > 0 ? ($slowQueries / $totalQueries) * 100 : 0
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get cache statistics
     */
    private function getCacheStatistics(): array
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                $info = $redis->info();
                
                $hits = $info['keyspace_hits'] ?? 0;
                $misses = $info['keyspace_misses'] ?? 0;
                $total = $hits + $misses;
                
                return [
                    'hit_rate' => $total > 0 ? ($hits / $total) * 100 : 0,
                    'cache_size' => $redis->dbsize(),
                    'used_memory' => $info['used_memory'] ?? 0
                ];
            }
            
            return ['hit_rate' => 0, 'cache_size' => 0];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get disk statistics
     */
    private function getDiskStatistics(): array
    {
        try {
            $diskTotal = disk_total_space('/');
            $diskFree = disk_free_space('/');
            $diskUsed = $diskTotal - $diskFree;
            
            return [
                'total' => $diskTotal,
                'used' => $diskUsed,
                'free' => $diskFree,
                'usage_percent' => $diskTotal > 0 ? ($diskUsed / $diskTotal) * 100 : 0
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get request trends
     */
    private function getRequestTrends(int $days): array
    {
        // Implementation for getting request trends from stored metrics
        return [];
    }

    /**
     * Get query trends
     */
    private function getQueryTrends(int $days): array
    {
        // Implementation for getting query trends from stored metrics
        return [];
    }

    /**
     * Get cache trends
     */
    private function getCacheTrends(int $days): array
    {
        // Implementation for getting cache trends from stored metrics
        return [];
    }

    /**
     * Get error trends
     */
    private function getErrorTrends(int $days): array
    {
        // Implementation for getting error trends from stored metrics
        return [];
    }
}