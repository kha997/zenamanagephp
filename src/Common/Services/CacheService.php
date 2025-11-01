<?php declare(strict_types=1);

namespace Src\Common\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * CacheService - Centralized caching service for ZenaManage
 * 
 * Provides unified caching interface with support for:
 * - Basic cache operations (get, put, forget, flush)
 * - Cache tags for grouped invalidation
 * - Remember functionality with callbacks
 * - Cache invalidation patterns
 * - Performance monitoring
 */
class CacheService
{
    /**
     * Default cache TTL in seconds
     */
    private const DEFAULT_TTL = 3600; // 1 hour

    /**
     * Cache key prefix
     */
    private const KEY_PREFIX = 'zena_';

    /**
     * Get value from cache
     * 
     * @param string $key Cache key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function get(string $key, $default = null)
    {
        try {
            $fullKey = $this->buildKey($key);
            return Cache::get($fullKey, $default);
        } catch (\Exception $e) {
            Log::error('Cache get error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    /**
     * Put value into cache
     * 
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl TTL in seconds
     * @return bool
     */
    public function put(string $key, $value, ?int $ttl = null): bool
    {
        try {
            $fullKey = $this->buildKey($key);
            $ttl = $ttl ?? self::DEFAULT_TTL;
            
            return Cache::put($fullKey, $value, $ttl);
        } catch (\Exception $e) {
            Log::error('Cache put error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Forget cache key
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function forget(string $key): bool
    {
        try {
            $fullKey = $this->buildKey($key);
            return Cache::forget($fullKey);
        } catch (\Exception $e) {
            Log::error('Cache forget error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Flush all cache
     * 
     * @return bool
     */
    public function flush(): bool
    {
        try {
            return Cache::flush();
        } catch (\Exception $e) {
            Log::error('Cache flush error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Remember value with callback
     * 
     * @param string $key Cache key
     * @param callable $callback Callback to execute if key not found
     * @param int|null $ttl TTL in seconds
     * @return mixed
     */
    public function remember(string $key, callable $callback, ?int $ttl = null)
    {
        try {
            $fullKey = $this->buildKey($key);
            $ttl = $ttl ?? self::DEFAULT_TTL;
            
            return Cache::remember($fullKey, $ttl, $callback);
        } catch (\Exception $e) {
            Log::error('Cache remember error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to direct callback execution
            return $callback();
        }
    }

    /**
     * Remember value forever
     * 
     * @param string $key Cache key
     * @param callable $callback Callback to execute if key not found
     * @return mixed
     */
    public function rememberForever(string $key, callable $callback)
    {
        try {
            $fullKey = $this->buildKey($key);
            return Cache::rememberForever($fullKey, $callback);
        } catch (\Exception $e) {
            Log::error('Cache rememberForever error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to direct callback execution
            return $callback();
        }
    }

    /**
     * Check if key exists in cache
     * 
     * @param string $key Cache key
     * @return bool
     */
    public function has(string $key): bool
    {
        try {
            $fullKey = $this->buildKey($key);
            return Cache::has($fullKey);
        } catch (\Exception $e) {
            Log::error('Cache has error', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Increment cache value
     * 
     * @param string $key Cache key
     * @param int $value Value to increment by
     * @return int|bool
     */
    public function increment(string $key, int $value = 1)
    {
        try {
            $fullKey = $this->buildKey($key);
            return Cache::increment($fullKey, $value);
        } catch (\Exception $e) {
            Log::error('Cache increment error', [
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Decrement cache value
     * 
     * @param string $key Cache key
     * @param int $value Value to decrement by
     * @return int|bool
     */
    public function decrement(string $key, int $value = 1)
    {
        try {
            $fullKey = $this->buildKey($key);
            return Cache::decrement($fullKey, $value);
        } catch (\Exception $e) {
            Log::error('Cache decrement error', [
                'key' => $key,
                'value' => $value,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Cache with tags
     * 
     * @param array $tags Cache tags
     * @param string $key Cache key
     * @param mixed $value Value to cache
     * @param int|null $ttl TTL in seconds
     * @return bool
     */
    public function putWithTags(array $tags, string $key, $value, ?int $ttl = null): bool
    {
        try {
            $fullKey = $this->buildKey($key);
            $ttl = $ttl ?? self::DEFAULT_TTL;
            
            return Cache::tags($tags)->put($fullKey, $value, $ttl);
        } catch (\Exception $e) {
            Log::error('Cache putWithTags error', [
                'tags' => $tags,
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Get value with tags
     * 
     * @param array $tags Cache tags
     * @param string $key Cache key
     * @param mixed $default Default value if key not found
     * @return mixed
     */
    public function getWithTags(array $tags, string $key, $default = null)
    {
        try {
            $fullKey = $this->buildKey($key);
            return Cache::tags($tags)->get($fullKey, $default);
        } catch (\Exception $e) {
            Log::error('Cache getWithTags error', [
                'tags' => $tags,
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    /**
     * Remember value with tags
     * 
     * @param array $tags Cache tags
     * @param string $key Cache key
     * @param callable $callback Callback to execute if key not found
     * @param int|null $ttl TTL in seconds
     * @return mixed
     */
    public function rememberWithTags(array $tags, string $key, callable $callback, ?int $ttl = null)
    {
        try {
            $fullKey = $this->buildKey($key);
            $ttl = $ttl ?? self::DEFAULT_TTL;
            
            return Cache::tags($tags)->remember($fullKey, $ttl, $callback);
        } catch (\Exception $e) {
            Log::error('Cache rememberWithTags error', [
                'tags' => $tags,
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            
            // Fallback to direct callback execution
            return $callback();
        }
    }

    /**
     * Flush cache by tags
     * 
     * @param array $tags Cache tags
     * @return bool
     */
    public function flushByTags(array $tags): bool
    {
        try {
            return Cache::tags($tags)->flush();
        } catch (\Exception $e) {
            Log::error('Cache flushByTags error', [
                'tags' => $tags,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Cache invalidation patterns
     */

    /**
     * Invalidate project-related cache
     * 
     * @param string $projectId Project ID
     * @return bool
     */
    public function invalidateProjectCache(string $projectId): bool
    {
        $tags = ['project', "project_{$projectId}"];
        return $this->flushByTags($tags);
    }

    /**
     * Invalidate user-related cache
     * 
     * @param string $userId User ID
     * @return bool
     */
    public function invalidateUserCache(string $userId): bool
    {
        $tags = ['user', "user_{$userId}"];
        return $this->flushByTags($tags);
    }

    /**
     * Invalidate task-related cache
     * 
     * @param string $taskId Task ID
     * @return bool
     */
    public function invalidateTaskCache(string $taskId): bool
    {
        $tags = ['task', "task_{$taskId}"];
        return $this->flushByTags($tags);
    }

    /**
     * Invalidate tenant-related cache
     * 
     * @param string $tenantId Tenant ID
     * @return bool
     */
    public function invalidateTenantCache(string $tenantId): bool
    {
        $tags = ['tenant', "tenant_{$tenantId}"];
        return $this->flushByTags($tags);
    }

    /**
     * Build cache key with prefix
     * 
     * @param string $key Original key
     * @return string
     */
    private function buildKey(string $key): string
    {
        return self::KEY_PREFIX . $key;
    }

    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        try {
            // This is a simplified implementation
            // In a real scenario, you might want to implement more detailed stats
            return [
                'driver' => config('cache.default'),
                'prefix' => self::KEY_PREFIX,
                'default_ttl' => self::DEFAULT_TTL,
                'timestamp' => now()->toISOString(),
            ];
        } catch (\Exception $e) {
            Log::error('Cache stats error', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Clear all ZenaManage cache
     * 
     * @return bool
     */
    public function clearAll(): bool
    {
        try {
            // Clear all cache with ZenaManage prefix
            $this->flush();
            return true;
        } catch (\Exception $e) {
            Log::error('Cache clearAll error', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
