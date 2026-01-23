<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\JsonContainsCompat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Carbon\Carbon;

/**
 * Advanced Caching Service
 * 
 * Provides intelligent caching strategies including:
 * - Multi-level caching (L1: Memory, L2: Redis, L3: Database)
 * - Cache warming and invalidation
 * - Cache compression and serialization
 * - Cache analytics and monitoring
 * - Smart cache key generation
 */
class AdvancedCachingService
{
    private const CACHE_LEVELS = [
        'memory' => 'L1',
        'redis' => 'L2', 
        'database' => 'L3'
    ];

    private const DEFAULT_TTL = 3600; // 1 hour
    private const COMPRESSION_THRESHOLD = 1024; // 1KB

    private array $cacheStats = [];
    private bool $compressionEnabled = true;
    private bool $analyticsEnabled = true;

    public function __construct()
    {
        $this->compressionEnabled = config('cache.compression.enabled', true);
        $this->analyticsEnabled = config('cache.analytics.enabled', true);
    }

    /**
     * Get data with multi-level caching
     */
    public function remember(string $key, int $ttl, callable $callback, array $tags = []): mixed
    {
        $startTime = microtime(true);
        
        // Try L1 cache (memory)
        $data = $this->getFromMemory($key);
        if ($data !== null) {
            $this->recordCacheHit('memory', microtime(true) - $startTime);
            return $data;
        }

        // Try L2 cache (Redis)
        $data = $this->getFromRedis($key);
        if ($data !== null) {
            $this->storeInMemory($key, $data, $ttl);
            $this->recordCacheHit('redis', microtime(true) - $startTime);
            return $data;
        }

        // Try L3 cache (database)
        $data = $this->getFromDatabase($key);
        if ($data !== null) {
            $this->storeInRedis($key, $data, $ttl);
            $this->storeInMemory($key, $data, $ttl);
            $this->recordCacheHit('database', microtime(true) - $startTime);
            return $data;
        }

        // Execute callback and store in all levels
        $data = $callback();
        $this->storeInAllLevels($key, $data, $ttl, $tags);
        $this->recordCacheMiss(microtime(true) - $startTime);

        return $data;
    }

    /**
     * Store data in all cache levels
     */
    public function put(string $key, mixed $value, int $ttl = self::DEFAULT_TTL, array $tags = []): bool
    {
        return $this->storeInAllLevels($key, $value, $ttl, $tags);
    }

    /**
     * Get data from specific cache level
     */
    public function get(string $key, string $level = 'redis'): mixed
    {
        return match ($level) {
            'memory' => $this->getFromMemory($key),
            'redis' => $this->getFromRedis($key),
            'database' => $this->getFromDatabase($key),
            default => $this->getFromRedis($key)
        };
    }

    /**
     * Invalidate cache by key or tags
     */
    public function forget(string $key, array $tags = []): bool
    {
        $success = true;

        // Remove from all levels
        $success &= $this->forgetFromMemory($key);
        $success &= $this->forgetFromRedis($key);
        $success &= $this->forgetFromDatabase($key);

        // Remove by tags if provided
        if (!empty($tags)) {
            $success &= $this->forgetByTags($tags);
        }

        return $success;
    }

    /**
     * Warm up cache with frequently accessed data
     */
    public function warmUp(array $warmUpData): void
    {
        foreach ($warmUpData as $key => $callback) {
            if (!$this->has($key)) {
                $this->put($key, $callback());
                Log::info("Cache warmed up for key: {$key}");
            }
        }
    }

    /**
     * Check if key exists in any cache level
     */
    public function has(string $key): bool
    {
        return $this->getFromMemory($key) !== null ||
               $this->getFromRedis($key) !== null ||
               $this->getFromDatabase($key) !== null;
    }

    /**
     * Get cache statistics
     */
    public function getStats(): array
    {
        return [
            'levels' => $this->cacheStats,
            'memory_usage' => $this->getMemoryUsage(),
            'redis_info' => $this->getRedisInfo(),
            'compression_enabled' => $this->compressionEnabled,
            'analytics_enabled' => $this->analyticsEnabled,
        ];
    }

    /**
     * Clear all cache levels
     */
    public function flush(): bool
    {
        $success = true;
        
        $success &= $this->flushMemory();
        $success &= $this->flushRedis();
        $success &= $this->flushDatabase();

        return $success;
    }

    /**
     * Generate smart cache key
     */
    public function generateKey(string $prefix, array $params = []): string
    {
        $key = $prefix;
        
        if (!empty($params)) {
            ksort($params);
            $key .= ':' . md5(serialize($params));
        }

        return $key;
    }

    /**
     * Get from memory cache (L1)
     */
    private function getFromMemory(string $key): mixed
    {
        $data = Cache::store('array')->get($key);
        return $data ? $this->decompress($data) : null;
    }

    /**
     * Store in memory cache (L1)
     */
    private function storeInMemory(string $key, mixed $value, int $ttl): bool
    {
        $compressed = $this->compress($value);
        return Cache::store('array')->put($key, $compressed, $ttl);
    }

    /**
     * Get from Redis cache (L2)
     */
    private function getFromRedis(string $key): mixed
    {
        try {
            $data = Cache::store('redis')->get($key);
            return $data ? $this->decompress($data) : null;
        } catch (\Exception $e) {
            Log::warning("Redis cache get failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Store in Redis cache (L2)
     */
    private function storeInRedis(string $key, mixed $value, int $ttl): bool
    {
        try {
            $compressed = $this->compress($value);
            return Cache::store('redis')->put($key, $compressed, $ttl);
        } catch (\Exception $e) {
            Log::warning("Redis cache put failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get from database cache (L3)
     */
    private function getFromDatabase(string $key): mixed
    {
        try {
            $cacheEntry = \App\Models\CacheEntry::where('key', $key)
                ->where('expires_at', '>', now())
                ->first();

            if ($cacheEntry) {
                return $this->decompress($cacheEntry->value);
            }

            return null;
        } catch (\Exception $e) {
            Log::warning("Database cache get failed: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Store in database cache (L3)
     */
    private function storeInDatabase(string $key, mixed $value, int $ttl, array $tags = []): bool
    {
        try {
            \App\Models\CacheEntry::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $this->compress($value),
                    'expires_at' => now()->addSeconds($ttl),
                    'tags' => json_encode($tags),
                    'created_at' => now(),
                ]
            );

            return true;
        } catch (\Exception $e) {
            Log::warning("Database cache put failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Store in all cache levels
     */
    private function storeInAllLevels(string $key, mixed $value, int $ttl, array $tags = []): bool
    {
        $success = true;
        
        $success &= $this->storeInMemory($key, $value, $ttl);
        $success &= $this->storeInRedis($key, $value, $ttl);
        $success &= $this->storeInDatabase($key, $value, $ttl, $tags);

        return $success;
    }

    /**
     * Compress data if needed
     */
    private function compress(mixed $data): mixed
    {
        if (!$this->compressionEnabled) {
            return $data;
        }

        $serialized = serialize($data);
        
        if (strlen($serialized) > self::COMPRESSION_THRESHOLD) {
            return base64_encode(gzcompress($serialized));
        }

        return $data;
    }

    /**
     * Decompress data if needed
     */
    private function decompress(mixed $data): mixed
    {
        if (!$this->compressionEnabled) {
            return $data;
        }

        if (is_string($data) && base64_decode($data, true) !== false) {
            $decoded = base64_decode($data);
            if ($decoded !== false) {
                $decompressed = gzuncompress($decoded);
                if ($decompressed !== false) {
                    return unserialize($decompressed);
                }
            }
        }

        return $data;
    }

    /**
     * Record cache hit statistics
     */
    private function recordCacheHit(string $level, float $executionTime): void
    {
        if (!$this->analyticsEnabled) {
            return;
        }

        if (!isset($this->cacheStats[$level])) {
            $this->cacheStats[$level] = ['hits' => 0, 'misses' => 0, 'avg_time' => 0];
        }

        $this->cacheStats[$level]['hits']++;
        $this->updateAverageTime($level, $executionTime);
    }

    /**
     * Record cache miss statistics
     */
    private function recordCacheMiss(float $executionTime): void
    {
        if (!$this->analyticsEnabled) {
            return;
        }

        foreach ($this->cacheStats as $level => &$stats) {
            $stats['misses']++;
            $this->updateAverageTime($level, $executionTime);
        }
    }

    /**
     * Update average execution time
     */
    private function updateAverageTime(string $level, float $executionTime): void
    {
        $total = $this->cacheStats[$level]['hits'] + $this->cacheStats[$level]['misses'];
        $currentAvg = $this->cacheStats[$level]['avg_time'];
        
        $this->cacheStats[$level]['avg_time'] = 
            ($currentAvg * ($total - 1) + $executionTime) / $total;
    }

    /**
     * Forget from memory cache
     */
    private function forgetFromMemory(string $key): bool
    {
        return Cache::store('array')->forget($key);
    }

    /**
     * Forget from Redis cache
     */
    private function forgetFromRedis(string $key): bool
    {
        try {
            return Cache::store('redis')->forget($key);
        } catch (\Exception $e) {
            Log::warning("Redis cache forget failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Forget from database cache
     */
    private function forgetFromDatabase(string $key): bool
    {
        try {
            return \App\Models\CacheEntry::where('key', $key)->delete() > 0;
        } catch (\Exception $e) {
            Log::warning("Database cache forget failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Forget by tags
     */
    private function forgetByTags(array $tags): bool
    {
        try {
            return JsonContainsCompat::apply(
                \App\Models\CacheEntry::query(),
                'tags',
                $tags
            )->delete() > 0;
        } catch (\Exception $e) {
            Log::warning("Database cache forget by tags failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Flush memory cache
     */
    private function flushMemory(): bool
    {
        return Cache::store('array')->flush();
    }

    /**
     * Flush Redis cache
     */
    private function flushRedis(): bool
    {
        try {
            return Cache::store('redis')->flush();
        } catch (\Exception $e) {
            Log::warning("Redis cache flush failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Flush database cache
     */
    private function flushDatabase(): bool
    {
        try {
            return \App\Models\CacheEntry::truncate();
        } catch (\Exception $e) {
            Log::warning("Database cache flush failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get memory usage statistics
     */
    private function getMemoryUsage(): array
    {
        return [
            'used' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit'),
        ];
    }

    /**
     * Get Redis information
     */
    private function getRedisInfo(): array
    {
        try {
            $redis = Redis::connection();
            return $redis->info();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
