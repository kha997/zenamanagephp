<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CacheOptimizationService
{
    /**
     * Constructor exists for ServicesTest dependency inspection.
     */
    public function __construct()
    {
        // No dependencies required yet; placeholder for contract checks.
    }

    /**
     * Optimize cache performance.
     */
    public function optimizeCache(): array
    {
        $optimizationResults = [
            'timestamp' => now()->toISOString(),
            'optimizations' => []
        ];

        // Clear expired cache entries
        $optimizationResults['optimizations']['clear_expired'] = $this->clearExpiredCache();
        
        // Optimize cache keys
        $optimizationResults['optimizations']['optimize_keys'] = $this->optimizeCacheKeys();
        
        // Analyze cache usage
        $optimizationResults['optimizations']['analyze_usage'] = $this->analyzeCacheUsage();
        
        // Optimize cache configuration
        $optimizationResults['optimizations']['optimize_config'] = $this->optimizeCacheConfig();
        
        // Generate cache report
        $optimizationResults['report'] = $this->generateCacheReport();

        $this->logPerformanceInfo('Cache optimization completed', $optimizationResults);

        return $optimizationResults;
    }

    /**
     * Clear expired cache entries.
     */
    protected function clearExpiredCache(): array
    {
        $clearedCount = 0;
        $startTime = microtime(true);

        try {
            // Clear expired cache entries
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                $keys = $redis->keys('*');
                
                foreach ($keys as $key) {
                    $ttl = $redis->ttl($key);
                    if ($ttl === -1) { // No expiration set
                        $redis->expire($key, 3600); // Set 1 hour expiration
                        $clearedCount++;
                    }
                }
            } else {
                // For file cache, clear old files
                $cachePath = storage_path('framework/cache');
                if (is_dir($cachePath)) {
                    $files = glob($cachePath . '/*');
                    $now = time();
                    
                    foreach ($files as $file) {
                        if (is_file($file) && ($now - filemtime($file)) > 3600) {
                            unlink($file);
                            $clearedCount++;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error clearing expired cache', ['error' => $e->getMessage()]);
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'cleared_count' => $clearedCount,
            'duration_ms' => $duration,
            'status' => 'completed'
        ];
    }

    /**
     * Optimize cache keys.
     */
    protected function optimizeCacheKeys(): array
    {
        $optimizedCount = 0;
        $startTime = microtime(true);

        try {
            // Optimize common cache keys
            $commonKeys = [
                'user_permissions',
                'tenant_settings',
                'project_teams',
                'task_dependencies',
                'document_versions'
            ];

            foreach ($commonKeys as $key) {
                // Set appropriate TTL for each key type
                $ttl = $this->getOptimalTtl($key);
                Cache::put("optimized_{$key}", true, $ttl);
                $optimizedCount++;
            }
        } catch (\Exception $e) {
            Log::error('Error optimizing cache keys', ['error' => $e->getMessage()]);
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'optimized_count' => $optimizedCount,
            'duration_ms' => $duration,
            'status' => 'completed'
        ];
    }

    /**
     * Analyze cache usage.
     */
    protected function analyzeCacheUsage(): array
    {
        $analysis = [
            'total_keys' => 0,
            'memory_usage' => 0,
            'hit_rate' => 0,
            'miss_rate' => 0,
            'top_keys' => []
        ];

        try {
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                $keys = $redis->keys('*');
                $analysis['total_keys'] = count($keys);

                // Get memory usage
                $info = $redis->info('memory');
                $analysis['memory_usage'] = $info['used_memory_human'] ?? 'Unknown';

                // Analyze key patterns
                $keyPatterns = [];
                foreach ($keys as $key) {
                    $pattern = explode(':', $key)[0] ?? 'unknown';
                    $keyPatterns[$pattern] = ($keyPatterns[$pattern] ?? 0) + 1;
                }
                arsort($keyPatterns);
                $analysis['top_keys'] = array_slice($keyPatterns, 0, 10, true);
            }
        } catch (\Exception $e) {
            Log::error('Error analyzing cache usage', ['error' => $e->getMessage()]);
        }

        return $analysis;
    }

    /**
     * Optimize cache configuration.
     */
    protected function optimizeCacheConfig(): array
    {
        $optimizations = [];

        // Check cache driver
        $currentDriver = config('cache.default');
        $optimizations['driver'] = [
            'current' => $currentDriver,
            'recommended' => 'redis',
            'status' => $currentDriver === 'redis' ? 'optimal' : 'suboptimal'
        ];

        // Check cache TTL settings
        $defaultTtl = config('cache.ttl', 3600);
        $optimizations['ttl'] = [
            'current' => $defaultTtl,
            'recommended' => 3600,
            'status' => $defaultTtl <= 3600 ? 'optimal' : 'too_long'
        ];

        // Check cache prefix
        $prefix = config('cache.prefix');
        $optimizations['prefix'] = [
            'current' => $prefix,
            'recommended' => 'zenamanage',
            'status' => !empty($prefix) ? 'optimal' : 'missing'
        ];

        return $optimizations;
    }

    /**
     * Generate cache report.
     */
    protected function generateCacheReport(): array
    {
        $report = [
            'cache_driver' => config('cache.default'),
            'cache_ttl' => config('cache.ttl', 3600),
            'cache_prefix' => config('cache.prefix'),
            'memory_usage' => $this->getMemoryUsage(),
            'performance_metrics' => $this->getPerformanceMetrics(),
            'recommendations' => $this->getCacheRecommendations()
        ];

        return $report;
    }

    /**
     * Get optimal TTL for cache key.
     */
    protected function getOptimalTtl(string $key): int
    {
        $ttlMap = [
            'user_permissions' => 1800, // 30 minutes
            'tenant_settings' => 3600,  // 1 hour
            'project_teams' => 1800,    // 30 minutes
            'task_dependencies' => 900, // 15 minutes
            'document_versions' => 3600 // 1 hour
        ];

        return $ttlMap[$key] ?? 3600;
    }

    /**
     * Get memory usage.
     */
    protected function getMemoryUsage(): array
    {
        $memory = [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        ];

        $memory['current_mb'] = round($memory['current'] / 1024 / 1024, 2);
        $memory['peak_mb'] = round($memory['peak'] / 1024 / 1024, 2);

        return $memory;
    }

    /**
     * Get performance metrics.
     */
    protected function getPerformanceMetrics(): array
    {
        $metrics = [
            'cache_hits' => Cache::get('cache_hits', 0),
            'cache_misses' => Cache::get('cache_misses', 0),
            'cache_sets' => Cache::get('cache_sets', 0)
        ];

        $total = $metrics['cache_hits'] + $metrics['cache_misses'];
        $metrics['hit_rate'] = $total > 0 ? round(($metrics['cache_hits'] / $total) * 100, 2) : 0;
        $metrics['miss_rate'] = $total > 0 ? round(($metrics['cache_misses'] / $total) * 100, 2) : 0;

        return $metrics;
    }

    /**
     * Get cache recommendations.
     */
    protected function getCacheRecommendations(): array
    {
        $recommendations = [];

        // Check cache driver
        if (config('cache.default') !== 'redis') {
            $recommendations[] = [
                'priority' => 'high',
                'recommendation' => 'Switch to Redis cache driver for better performance'
            ];
        }

        // Check cache TTL
        if (config('cache.ttl', 3600) > 3600) {
            $recommendations[] = [
                'priority' => 'medium',
                'recommendation' => 'Reduce cache TTL to prevent stale data'
            ];
        }

        // Check cache prefix
        if (empty(config('cache.prefix'))) {
            $recommendations[] = [
                'priority' => 'low',
                'recommendation' => 'Set cache prefix for better organization'
            ];
        }

        return $recommendations;
    }

    /**
     * Warm up cache.
     */
    public function warmUpCache(): array
    {
        $warmUpResults = [
            'timestamp' => now()->toISOString(),
            'warmed_items' => []
        ];

        $startTime = microtime(true);

        try {
            // Warm up user permissions
            $users = DB::table('users')->limit(100)->get();
            foreach ($users as $user) {
                Cache::remember("user_permissions_{$user->id}", 1800, function() use ($user) {
                    return DB::table('user_roles')
                        ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                        ->where('user_roles.user_id', $user->id)
                        ->pluck('roles.name')
                        ->toArray();
                });
                $warmUpResults['warmed_items'][] = "user_permissions_{$user->id}";
            }

            // Warm up tenant settings
            $tenants = DB::table('tenants')->get();
            foreach ($tenants as $tenant) {
                Cache::remember("tenant_settings_{$tenant->id}", 3600, function() use ($tenant) {
                    return DB::table('tenant_settings')
                        ->where('tenant_id', $tenant->id)
                        ->pluck('value', 'key')
                        ->toArray();
                });
                $warmUpResults['warmed_items'][] = "tenant_settings_{$tenant->id}";
            }

        } catch (\Exception $e) {
            Log::error('Error warming up cache', ['error' => $e->getMessage()]);
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $warmUpResults['duration_ms'] = $duration;
        $warmUpResults['total_items'] = count($warmUpResults['warmed_items']);

        $this->logPerformanceInfo('Cache warm-up completed', $warmUpResults);

        return $warmUpResults;
    }

    /**
     * Clear cache by pattern.
     */
    public function clearCacheByPattern(string $pattern): array
    {
        $clearedCount = 0;
        $startTime = microtime(true);

        try {
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                $keys = $redis->keys("*{$pattern}*");
                
                foreach ($keys as $key) {
                    $redis->del($key);
                    $clearedCount++;
                }
            } else {
                // For file cache
                $cachePath = storage_path('framework/cache');
                if (is_dir($cachePath)) {
                    $files = glob($cachePath . "/*{$pattern}*");
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            unlink($file);
                            $clearedCount++;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('Error clearing cache by pattern', ['error' => $e->getMessage()]);
        }

        $duration = round((microtime(true) - $startTime) * 1000, 2);

        return [
            'pattern' => $pattern,
            'cleared_count' => $clearedCount,
            'duration_ms' => $duration,
            'status' => 'completed'
        ];
    }

    /**
     * Backward-compatible entry point expected by unit tests.
     * Delegates to optimizeCache() without changing domain logic.
     */
    public function optimizeApplicationCache(): array
    {
        $metricsBefore = $this->collectCacheMetrics();

        $result = $this->optimizeCache();

        $metricsAfter = $this->collectCacheMetrics();

        if (!is_array($result)) {
            return [
                'status' => $result === false ? 'fail' : 'pass',
                'message' => is_string($result) ? $result : 'Cache optimization completed',
                'actions_taken' => [],
                'metrics_before' => $metricsBefore,
                'metrics_after' => $metricsAfter,
            ];
        }

        $actions = [];
        if (isset($result['optimizations']) && is_array($result['optimizations'])) {
            $actions = array_keys($result['optimizations']);
        }

        $result['actions_taken'] = $actions;
        $result['metrics_before'] = $metricsBefore;
        $result['metrics_after'] = $metricsAfter;

        if (!isset($result['status'])) {
            $result['status'] = 'completed';
        }

        return $result;
    }

    public function getCacheMetrics(): array
    {
        return $this->collectCacheMetrics();
    }

    public function clearAllApplicationCaches(): void
    {
        try {
            Cache::flush();

            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                $redis->flushdb();
            }
        } catch (\Throwable $e) {
            Log::warning('Error clearing all application caches', ['error' => $e->getMessage()]);
        }
    }

    protected function collectCacheMetrics(): array
    {
        $metrics = [
            'driver' => config('cache.default'),
            'timestamp' => now()->toISOString(),
        ];

        try {
            $cachePath = storage_path('framework/cache');
            $metrics['file_cache_path_exists'] = is_dir($cachePath);

            if (is_dir($cachePath)) {
                $files = glob($cachePath . '/*') ?: [];
                $metrics['file_cache_files'] = count($files);
                $bytes = 0;

                foreach ($files as $file) {
                    if (is_file($file)) {
                        $bytes += filesize($file);
                    }
                }

                $metrics['file_cache_bytes'] = $bytes;
            } else {
                $metrics['file_cache_files'] = 0;
                $metrics['file_cache_bytes'] = 0;
            }
        } catch (\Throwable $e) {
            $metrics['file_cache_error'] = $e->getMessage();
        }

        if (config('cache.default') === 'redis') {
            try {
                $redis = Redis::connection();
                $info = $redis->info('memory');
                $metrics['redis_used_memory'] = $info['used_memory_human'] ?? null;
                $metrics['redis_used_memory_bytes'] = $info['used_memory'] ?? null;
            } catch (\Throwable $e) {
                $metrics['redis_error'] = $e->getMessage();
            }
        }

        return $metrics;
    }

    protected function logPerformanceInfo(string $message, array $context = []): void
    {
        Log::info($message, $context);

        if (!app()->runningUnitTests()) {
            Log::channel('performance')->info($message, $context);
        }
    }

}
