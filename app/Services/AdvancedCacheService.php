<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;

/**
 * Advanced Caching Service
 * 
 * Provides sophisticated caching capabilities:
 * - Multi-tier caching (Redis, Memory, Database)
 * - Cache invalidation strategies
 * - Cache warming and preloading
 * - Performance monitoring and analytics
 * - Tenant-aware caching
 */
class AdvancedCacheService
{
    private array $cacheConfig = [
        'default_ttl' => 3600, // 1 hour
        'short_ttl' => 300,   // 5 minutes
        'long_ttl' => 86400,  // 24 hours
        'very_long_ttl' => 604800, // 7 days
    ];

    private array $cacheStrategies = [
        'user_data' => ['ttl' => 1800, 'tags' => ['user'], 'strategy' => 'write_through'],
        'dashboard_data' => ['ttl' => 300, 'tags' => ['dashboard'], 'strategy' => 'write_behind'],
        'project_data' => ['ttl' => 3600, 'tags' => ['project'], 'strategy' => 'write_through'],
        'task_data' => ['ttl' => 1800, 'tags' => ['task'], 'strategy' => 'write_behind'],
        'analytics_data' => ['ttl' => 600, 'tags' => ['analytics'], 'strategy' => 'write_through'],
        'permissions' => ['ttl' => 3600, 'tags' => ['permissions'], 'strategy' => 'write_through'],
        'tenant_data' => ['ttl' => 7200, 'tags' => ['tenant'], 'strategy' => 'write_through'],
    ];

    /**
     * Get cached data with fallback strategies
     */
    public function get(string $key, callable $fallback = null, array $options = []): mixed
    {
        $startTime = microtime(true);
        $tenantId = $this->getTenantId();
        $fullKey = $this->buildKey($key, $tenantId);
        
        try {
            // Try Redis first
            $data = $this->getFromRedis($fullKey);
            if ($data !== null) {
                $this->logCacheHit($key, 'redis', microtime(true) - $startTime);
                return $data;
            }

            // Try Laravel cache
            $data = Cache::get($fullKey);
            if ($data !== null) {
                $this->logCacheHit($key, 'laravel', microtime(true) - $startTime);
                // Store in Redis for faster access
                $this->storeInRedis($fullKey, $data, $options);
                return $data;
            }

            // Execute fallback if provided
            if ($fallback !== null) {
                $data = $fallback();
                $this->set($key, $data, $options);
                $this->logCacheMiss($key, 'fallback', microtime(true) - $startTime);
                return $data;
            }

            $this->logCacheMiss($key, 'none', microtime(true) - $startTime);
            return null;

        } catch (\Exception $e) {
            Log::error('Cache get error', [
                'key' => $key,
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            
            // Fallback to direct execution
            if ($fallback !== null) {
                return $fallback();
            }
            
            return null;
        }
    }

    /**
     * Store data in cache with multiple strategies
     */
    public function set(string $key, mixed $data, array $options = []): bool
    {
        $tenantId = $this->getTenantId();
        $fullKey = $this->buildKey($key, $tenantId);
        
        $strategy = $options['strategy'] ?? $this->getStrategy($key);
        $ttl = $options['ttl'] ?? $this->getTtl($key);
        $tags = $options['tags'] ?? $this->getTags($key);

        try {
            switch ($strategy) {
                case 'write_through':
                    return $this->writeThrough($fullKey, $data, $ttl, $tags);
                case 'write_behind':
                    return $this->writeBehind($fullKey, $data, $ttl, $tags);
                case 'write_around':
                    return $this->writeAround($fullKey, $data, $ttl, $tags);
                default:
                    return $this->writeThrough($fullKey, $data, $ttl, $tags);
            }
        } catch (\Exception $e) {
            Log::error('Cache set error', [
                'key' => $key,
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            return false;
        }
    }

    /**
     * Invalidate cache by key, tags, or pattern
     */
    public function invalidate(string $key = null, array $tags = null, string $pattern = null): bool
    {
        $tenantId = $this->getTenantId();
        
        try {
            if ($key !== null) {
                $fullKey = $this->buildKey($key, $tenantId);
                $this->deleteFromRedis($fullKey);
                Cache::forget($fullKey);
            }

            if ($tags !== null) {
                $this->invalidateByTags($tags, $tenantId);
            }

            if ($pattern !== null) {
                $this->invalidateByPattern($pattern, $tenantId);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Cache invalidation error', [
                'key' => $key,
                'tags' => $tags,
                'pattern' => $pattern,
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            return false;
        }
    }

    /**
     * Warm up cache with frequently accessed data
     */
    public function warmUp(array $keys, callable $dataProvider): bool
    {
        $tenantId = $this->getTenantId();
        
        try {
            foreach ($keys as $key) {
                $fullKey = $this->buildKey($key, $tenantId);
                
                // Check if already cached
                if ($this->getFromRedis($fullKey) !== null) {
                    continue;
                }

                // Get data and cache it
                $data = $dataProvider($key);
                if ($data !== null) {
                    $this->set($key, $data);
                }
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Cache warm up error', [
                'keys' => $keys,
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
            ]);
            return false;
        }
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        $defaultStats = [
            'redis' => [
                'memory_used' => '0 B',
                'connected_clients' => 0,
                'total_commands_processed' => 0,
                'keyspace_hits' => 0,
                'keyspace_misses' => 0,
            ],
            'hit_rate' => 0.0,
            'miss_rate' => 0.0,
            'total_keys' => 0,
            'memory_usage' => '0 B',
            'uptime' => 1,
            'connected_clients' => 0,
            'used_memory_human' => '0 B',
            'redis_version' => 'unknown',
            'cache_size' => 0,
        ];

        try {
            $redisInfo = Redis::info();
            $totalKeys = $this->getCacheSize();
            $memoryUsage = $redisInfo['used_memory_human'] ?? '0 B';
            $uptime = (int) ($redisInfo['uptime_in_seconds'] ?? 0);
            if ($uptime <= 0) {
                $uptime = 1;
            }

            return [
                'redis' => [
                    'memory_used' => $memoryUsage,
                    'connected_clients' => $redisInfo['connected_clients'] ?? 0,
                    'total_commands_processed' => $redisInfo['total_commands_processed'] ?? 0,
                    'keyspace_hits' => $redisInfo['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $redisInfo['keyspace_misses'] ?? 0,
                ],
                'hit_rate' => $this->calculateHitRate($redisInfo),
                'miss_rate' => $this->calculateMissRate($redisInfo),
                'total_keys' => $totalKeys,
                'memory_usage' => $memoryUsage,
                'uptime' => $uptime,
                'connected_clients' => $redisInfo['connected_clients'] ?? 0,
                'used_memory_human' => $memoryUsage,
                'redis_version' => $redisInfo['redis_version'] ?? 'unknown',
                'cache_size' => $totalKeys,
            ];
        } catch (\Exception $e) {
            Log::error('Cache stats error', ['error' => $e->getMessage()]);
            return $defaultStats;
        }
    }

    /**
     * Write-through cache strategy
     */
    private function writeThrough(string $key, mixed $data, int $ttl, array $tags): bool
    {
        $success = true;
        
        // Store in Redis
        if (!$this->storeInRedis($key, $data, $ttl)) {
            $success = false;
        }
        
        // Store in Laravel cache
        if (!Cache::put($key, $data, $ttl)) {
            $success = false;
        }
        
        // Store tags for invalidation
        $this->storeTags($key, $tags);
        
        return $success;
    }

    /**
     * Write-behind cache strategy
     */
    private function writeBehind(string $key, mixed $data, int $ttl, array $tags): bool
    {
        // Store in Redis immediately
        $this->storeInRedis($key, $data, $ttl);
        
        // Queue Laravel cache update
        // In a real implementation, you might use a queue
        Cache::put($key, $data, $ttl);
        
        $this->storeTags($key, $tags);
        
        return true;
    }

    /**
     * Write-around cache strategy
     */
    private function writeAround(string $key, mixed $data, int $ttl, array $tags): bool
    {
        // Only store in Redis, not in Laravel cache
        $this->storeInRedis($key, $data, $ttl);
        $this->storeTags($key, $tags);
        
        return true;
    }

    /**
     * Get data from Redis
     */
    private function getFromRedis(string $key): mixed
    {
        try {
            $data = Redis::get($key);
            return $data ? json_decode($data, true) : null;
        } catch (\Exception $e) {
            Log::warning('Redis get error', ['key' => $key, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Store data in Redis
     */
    private function storeInRedis(string $key, mixed $data, int $ttl = null): bool
    {
        try {
            $ttl = $ttl ?? $this->cacheConfig['default_ttl'];
            $serialized = json_encode($data);
            
            Redis::setex($key, $ttl, $serialized);
            return true;
        } catch (\Exception $e) {
            Log::warning('Redis set error', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Delete data from Redis
     */
    private function deleteFromRedis(string $key): bool
    {
        try {
            Redis::del($key);
            return true;
        } catch (\Exception $e) {
            Log::warning('Redis delete error', ['key' => $key, 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Store cache tags for invalidation
     */
    private function storeTags(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            $tagKey = "tag:{$tag}:" . $this->getTenantId();
            Redis::sadd($tagKey, $key);
            Redis::expire($tagKey, $this->cacheConfig['very_long_ttl']);
        }
    }

    /**
     * Invalidate cache by tags
     */
    private function invalidateByTags(array $tags, string $tenantId): void
    {
        foreach ($tags as $tag) {
            $tagKey = "tag:{$tag}:{$tenantId}";
            try {
                $keys = Redis::smembers($tagKey);
            } catch (\Exception $e) {
                Log::warning('Redis smembers error', [
                    'tag_key' => $tagKey,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }

            if (!empty($keys)) {
                try {
                    Redis::del($keys);
                    Redis::del($tagKey);
                } catch (\Exception $e) {
                    Log::warning('Redis delete error', [
                        'keys' => $keys,
                        'tag_key' => $tagKey,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Invalidate cache by pattern
     */
    private function invalidateByPattern(string $pattern, string $tenantId): void
    {
        $fullPattern = $this->buildKey($pattern, $tenantId);
        $keys = [];

        try {
            $keys = Redis::keys($fullPattern);
        } catch (\Exception $e) {
            Log::warning('Redis keys error', [
                'pattern' => $fullPattern,
                'error' => $e->getMessage(),
            ]);
        }

        if (!empty($keys)) {
            try {
                Redis::del($keys);
            } catch (\Exception $e) {
                Log::warning('Redis delete error', [
                    'keys' => $keys,
                    'pattern' => $fullPattern,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->invalidatePatternFromArrayStore($fullPattern);
    }

    private function invalidatePatternFromArrayStore(string $pattern): void
    {
        $store = Cache::getStore();

        if (!$store instanceof \Illuminate\Cache\ArrayStore) {
            return;
        }

        try {
            $storageProp = new \ReflectionProperty($store, 'storage');
            $storageProp->setAccessible(true);
            $storage = $storageProp->getValue($store);
            $cachePrefix = config('cache.prefix', '');
            $matchPattern = $pattern;

            $prefixSegment = $cachePrefix !== '' ? $cachePrefix . ':' : '';

            foreach (array_keys($storage) as $key) {
                if (fnmatch($matchPattern, $key)) {
                    $keyWithoutPrefix = $prefixSegment !== '' && str_starts_with($key, $prefixSegment)
                        ? substr($key, strlen($prefixSegment))
                        : $key;
                    Cache::forget($keyWithoutPrefix);
                }
            }
        } catch (\Exception $e) {
            Log::warning('Array cache pattern invalidation error', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Build cache key with tenant isolation
     */
    private function buildKey(string $key, string $tenantId): string
    {
        return "tenant:{$tenantId}:{$key}";
    }

    /**
     * Get tenant ID from current context
     */
    private function getTenantId(): string
    {
        // In a real implementation, you might get this from:
        // - Request context
        // - Auth user
        // - Session
        return request()->header('X-Tenant-ID', 'default');
    }

    /**
     * Get cache strategy for key
     */
    private function getStrategy(string $key): string
    {
        foreach ($this->cacheStrategies as $pattern => $config) {
            if (str_contains($key, $pattern)) {
                return $config['strategy'];
            }
        }
        
        return 'write_through';
    }

    /**
     * Get TTL for key
     */
    private function getTtl(string $key): int
    {
        foreach ($this->cacheStrategies as $pattern => $config) {
            if (str_contains($key, $pattern)) {
                return $config['ttl'];
            }
        }
        
        return $this->cacheConfig['default_ttl'];
    }

    /**
     * Get tags for key
     */
    private function getTags(string $key): array
    {
        foreach ($this->cacheStrategies as $pattern => $config) {
            if (str_contains($key, $pattern)) {
                return $config['tags'];
            }
        }
        
        return [];
    }

    /**
     * Log cache hit
     */
    private function logCacheHit(string $key, string $source, float $duration): void
    {
        Log::info('Cache hit', [
            'key' => $key,
            'source' => $source,
            'duration_ms' => round($duration * 1000, 2),
        ]);
    }

    /**
     * Log cache miss
     */
    private function logCacheMiss(string $key, string $source, float $duration): void
    {
        Log::info('Cache miss', [
            'key' => $key,
            'source' => $source,
            'duration_ms' => round($duration * 1000, 2),
        ]);
    }

    /**
     * Calculate cache hit rate
     */
    private function calculateHitRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;

        if ($hits + $misses === 0) {
            return 0.0;
        }

        return round($hits / ($hits + $misses), 4);
    }

    private function calculateMissRate(array $info): float
    {
        $hits = $info['keyspace_hits'] ?? 0;
        $misses = $info['keyspace_misses'] ?? 0;

        if ($hits + $misses === 0) {
            return 0.0;
        }

        return round($misses / ($hits + $misses), 4);
    }

    /**
     * Get cache size
     */
    private function getCacheSize(): int
    {
        try {
            return Redis::dbsize();
        } catch (\Exception $e) {
            return 0;
        }
    }
}
