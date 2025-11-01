<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;

class CacheOptimizationService
{
    /**
     * Get cache performance metrics
     */
    public function getCacheMetrics(): array
    {
        $metrics = [];

        try {
            // Get cache driver info
            $driver = config('cache.default');
            $metrics['driver'] = $driver;

            // Get Redis metrics if using Redis
            if ($driver === 'redis') {
                $redis = Redis::connection();
                $info = $redis->info();
                
                $metrics['redis'] = [
                    'used_memory' => $info['used_memory_human'] ?? 'N/A',
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                    'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                    'hit_rate' => $this->calculateHitRate($info),
                ];
            }

            // Get cache statistics
            $metrics['stats'] = $this->getCacheStats();

        } catch (\Exception $e) {
            Log::error('Failed to get cache metrics', ['error' => $e->getMessage()]);
            $metrics['error'] = $e->getMessage();
        }

        return $metrics;
    }

    /**
     * Calculate cache hit rate
     */
    private function calculateHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
    }

    /**
     * Get cache statistics
     */
    private function getCacheStats(): array
    {
        $stats = [
            'total_keys' => 0,
            'memory_usage' => 0,
            'hit_rate' => 0,
        ];

        try {
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                $keys = $redis->keys('*');
                $stats['total_keys'] = count($keys);
                
                $info = $redis->info();
                $stats['memory_usage'] = $info['used_memory'] ?? 0;
                $stats['hit_rate'] = $this->calculateHitRate($info);
            }
        } catch (\Exception $e) {
            Log::error('Failed to get cache stats', ['error' => $e->getMessage()]);
        }

        return $stats;
    }

    /**
     * Optimize cache configuration for production
     */
    public function getProductionCacheConfig(): array
    {
        return [
            'default' => 'redis',
            'stores' => [
                'redis' => [
                    'driver' => 'redis',
                    'connection' => 'cache',
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'password' => env('REDIS_PASSWORD'),
                    'port' => env('REDIS_PORT', 6379),
                    'database' => env('REDIS_CACHE_DB', 1),
                    'options' => [
                        'prefix' => env('CACHE_PREFIX', 'zenamanage_cache'),
                        'serializer' => 'json',
                        'compression' => 'gzip',
                    ],
                ],
                'memcached' => [
                    'driver' => 'memcached',
                    'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
                    'sasl' => [
                        env('MEMCACHED_USERNAME'),
                        env('MEMCACHED_PASSWORD'),
                    ],
                    'options' => [
                        // Memcached::OPT_DISTRIBUTION => Memcached::DISTRIBUTION_CONSISTENT,
                        // Memcached::OPT_LIBKETAMA_COMPATIBLE => true,
                        // Memcached::OPT_SERVER_FAILURE_LIMIT => 2,
                        // Memcached::OPT_REMOVE_FAILED_SERVERS => true,
                        // Memcached::OPT_RETRY_TIMEOUT => 1,
                    ],
                    'servers' => [
                        [
                            'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                            'port' => env('MEMCACHED_PORT', 11211),
                            'weight' => 100,
                        ],
                    ],
                ],
            ],
            'optimizations' => [
                'compression' => true,
                'serialization' => 'json',
                'prefixing' => true,
                'tagging' => true,
            ],
        ];
    }

    /**
     * Warm up cache with frequently accessed data
     */
    public function warmUpCache(): array
    {
        $warmed = [];

        try {
            // Warm up application cache
            $warmed['app_config'] = $this->warmUpAppConfig();
            $warmed['permissions'] = $this->warmUpPermissions();
            $warmed['routes'] = $this->warmUpRoutes();
            $warmed['translations'] = $this->warmUpTranslations();

            Log::info('Cache warm-up completed', $warmed);

        } catch (\Exception $e) {
            Log::error('Cache warm-up failed', ['error' => $e->getMessage()]);
            $warmed['error'] = $e->getMessage();
        }

        return $warmed;
    }

    /**
     * Warm up application configuration cache
     */
    private function warmUpAppConfig(): bool
    {
        try {
            // Cache frequently accessed config
            Cache::remember('app.config', 3600, function () {
                return [
                    'app_name' => config('app.name'),
                    'app_env' => config('app.env'),
                    'app_debug' => config('app.debug'),
                    'app_url' => config('app.url'),
                ];
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Warm up permissions cache
     */
    private function warmUpPermissions(): bool
    {
        try {
            Cache::remember('permissions.config', 3600, function () {
                return config('permissions');
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Warm up routes cache
     */
    private function warmUpRoutes(): bool
    {
        try {
            Cache::remember('routes.list', 3600, function () {
                return app('router')->getRoutes();
            });
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Warm up translations cache
     */
    private function warmUpTranslations(): bool
    {
        try {
            $locales = ['en', 'vi'];
            foreach ($locales as $locale) {
                Cache::remember("translations.{$locale}", 3600, function () use ($locale) {
                    return [
                        'app' => trans('app', [], $locale),
                        'projects' => trans('projects', [], $locale),
                        'tasks' => trans('tasks', [], $locale),
                        'dashboard' => trans('dashboard', [], $locale),
                    ];
                });
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Clear cache by tags
     */
    public function clearCacheByTags(array $tags): bool
    {
        try {
            foreach ($tags as $tag) {
                Cache::tags($tag)->flush();
            }
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear cache by tags', ['tags' => $tags, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Clear all cache
     */
    public function clearAllCache(): bool
    {
        try {
            Cache::flush();
            return true;
        } catch (\Exception $e) {
            Log::error('Failed to clear all cache', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get cache recommendations
     */
    public function getCacheRecommendations(): array
    {
        $recommendations = [];

        $metrics = $this->getCacheMetrics();

        // Check hit rate
        if (isset($metrics['redis']['hit_rate']) && $metrics['redis']['hit_rate'] < 80) {
            $recommendations[] = [
                'type' => 'performance',
                'message' => 'Cache hit rate is low. Consider increasing cache TTL or optimizing cache keys.',
                'priority' => 'high',
            ];
        }

        // Check memory usage
        if (isset($metrics['redis']['used_memory'])) {
            $recommendations[] = [
                'type' => 'memory',
                'message' => 'Monitor Redis memory usage to prevent OOM errors.',
                'priority' => 'medium',
            ];
        }

        // Check driver
        if (config('cache.default') === 'file') {
            $recommendations[] = [
                'type' => 'configuration',
                'message' => 'Consider using Redis or Memcached for better performance in production.',
                'priority' => 'high',
            ];
        }

        return $recommendations;
    }

    /**
     * Optimize cache keys
     */
    public function optimizeCacheKeys(): array
    {
        $optimizations = [];

        // Suggest shorter cache keys
        $optimizations[] = [
            'type' => 'key_optimization',
            'suggestion' => 'Use shorter cache keys to reduce memory usage',
            'example' => 'user:123:profile instead of user_profile_data_for_user_id_123',
        ];

        // Suggest cache tagging
        $optimizations[] = [
            'type' => 'tagging',
            'suggestion' => 'Use cache tags for easier cache invalidation',
            'example' => 'Cache::tags([\'user\', \'profile\'])->put($key, $value)',
        ];

        return $optimizations;
    }

    /**
     * Monitor cache performance
     */
    public function monitorCachePerformance(): array
    {
        $monitoring = [];

        try {
            $startTime = microtime(true);
            
            // Test cache operations
            $testKey = 'performance_test_' . time();
            $testValue = ['test' => 'data', 'timestamp' => time()];
            
            // Test write
            Cache::put($testKey, $testValue, 60);
            $writeTime = microtime(true) - $startTime;
            
            // Test read
            $startTime = microtime(true);
            $retrieved = Cache::get($testKey);
            $readTime = microtime(true) - $startTime;
            
            // Test delete
            $startTime = microtime(true);
            Cache::forget($testKey);
            $deleteTime = microtime(true) - $startTime;
            
            $monitoring = [
                'write_time' => round($writeTime * 1000, 2), // ms
                'read_time' => round($readTime * 1000, 2), // ms
                'delete_time' => round($deleteTime * 1000, 2), // ms
                'data_integrity' => $retrieved === $testValue,
                'status' => 'healthy',
            ];
            
        } catch (\Exception $e) {
            $monitoring = [
                'error' => $e->getMessage(),
                'status' => 'unhealthy',
            ];
        }

        return $monitoring;
    }
}
