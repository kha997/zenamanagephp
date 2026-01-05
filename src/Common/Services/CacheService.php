<?php declare(strict_types=1);

namespace Src\Common\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Cache\TaggableStore;

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

    private const TAGGED_KEY_PREFIX = 'tagged:';
    private const TAG_INDEX_PREFIX = 'tag:index:';
    private const TAG_META_PREFIX = 'tag:meta:';
    private const GLOBAL_INDEX_KEY = 'index:keys';

    private ?bool $taggableStore = null;

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
        $normalizedTags = $this->normalizeTags($tags);
        $ttl = $ttl ?? self::DEFAULT_TTL;

        if (empty($normalizedTags)) {
            return $this->put($key, $value, $ttl);
        }

        if ($this->supportsTags()) {
            try {
                $fullKey = $this->buildKey($key);
                return Cache::tags($normalizedTags)->put($fullKey, $value, $ttl);
            } catch (\Throwable $e) {
                Log::warning('Cache putWithTags fallback triggered', [
                    'tags' => $normalizedTags,
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->storeWithTagFallback($normalizedTags, $key, $value, $ttl);
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
        $normalizedTags = $this->normalizeTags($tags);

        if (empty($normalizedTags)) {
            return $this->get($key, $default);
        }

        if ($this->supportsTags()) {
            try {
                $fullKey = $this->buildKey($key);
                return Cache::tags($normalizedTags)->get($fullKey, $default);
            } catch (\Throwable $e) {
                Log::warning('Cache getWithTags fallback triggered', [
                    'tags' => $normalizedTags,
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->getWithTagsFallback($normalizedTags, $key, $default);
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
        $normalizedTags = $this->normalizeTags($tags);
        $ttl = $ttl ?? self::DEFAULT_TTL;

        if (empty($normalizedTags)) {
            return $this->remember($key, $callback, $ttl);
        }

        if ($this->supportsTags()) {
            try {
                $fullKey = $this->buildKey($key);
                return Cache::tags($normalizedTags)->remember($fullKey, $ttl, $callback);
            } catch (\Throwable $e) {
                Log::warning('Cache rememberWithTags fallback triggered', [
                    'tags' => $normalizedTags,
                    'key' => $key,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->rememberWithTagsFallback($normalizedTags, $key, $callback, $ttl);
    }

    /**
     * Flush cache by tags
     * 
     * @param array $tags Cache tags
     * @return bool
     */
    public function flushByTags(array $tags): bool
    {
        $normalizedTags = $this->normalizeTags($tags);

        if (empty($normalizedTags)) {
            return $this->flush();
        }

        if ($this->supportsTags()) {
            try {
                return Cache::tags($normalizedTags)->flush();
            } catch (\Throwable $e) {
                Log::warning('Cache flushByTags fallback triggered', [
                    'tags' => $normalizedTags,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $this->flushTagsFallback($normalizedTags);
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

    private function supportsTags(): bool
    {
        if ($this->taggableStore !== null) {
            return $this->taggableStore;
        }

        try {
            $store = Cache::getStore();
            $this->taggableStore = $store instanceof TaggableStore;
        } catch (\Throwable $e) {
            Log::warning('Cache tag support check failed', [
                'error' => $e->getMessage()
            ]);
            $this->taggableStore = false;
        }

        return $this->taggableStore;
    }

    private function normalizeTags(array $tags): array
    {
        $normalized = array_filter(array_map(fn ($tag) => strtolower(trim((string) $tag)), $tags), 'strlen');
        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    private function buildTagSetHash(array $tags): string
    {
        return sha1(implode('|', $tags));
    }

    private function buildSingleTagHash(string $tag): string
    {
        return sha1($tag);
    }

    private function buildTaggedKey(string $tagHash, string $key): string
    {
        return self::KEY_PREFIX . self::TAGGED_KEY_PREFIX . $tagHash . ':' . $key;
    }

    private function buildTagIndexKey(string $tagHash): string
    {
        return self::KEY_PREFIX . self::TAG_INDEX_PREFIX . $tagHash;
    }

    private function buildTagMetaKey(string $namespacedKey): string
    {
        return self::KEY_PREFIX . self::TAG_META_PREFIX . sha1($namespacedKey);
    }

    private function getGlobalIndexKey(): string
    {
        return self::KEY_PREFIX . self::GLOBAL_INDEX_KEY;
    }

    private function storeWithTagFallback(array $tags, string $key, $value, int $ttl): bool
    {
        $tagHash = $this->buildTagSetHash($tags);
        $taggedKey = $this->buildTaggedKey($tagHash, $key);

        $stored = Cache::put($taggedKey, $value, $ttl);

        if ($stored) {
            $this->registerTaggedEntry($taggedKey, $tags);
        }

        return $stored;
    }

    private function rememberWithTagsFallback(array $tags, string $key, callable $callback, int $ttl)
    {
        $tagHash = $this->buildTagSetHash($tags);
        $taggedKey = $this->buildTaggedKey($tagHash, $key);

        $value = Cache::remember($taggedKey, $ttl, $callback);

        $this->registerTaggedEntry($taggedKey, $tags);

        return $value;
    }

    private function getWithTagsFallback(array $tags, string $key, $default = null)
    {
        $tagHash = $this->buildTagSetHash($tags);
        $taggedKey = $this->buildTaggedKey($tagHash, $key);

        return Cache::get($taggedKey, $default);
    }

    private function flushTagsFallback(array $tags): bool
    {
        $success = true;

        foreach ($tags as $tag) {
            $tagHash = $this->buildSingleTagHash($tag);
            $indexKey = $this->buildTagIndexKey($tagHash);
            $keys = Cache::get($indexKey, []);
            if (!is_array($keys)) {
                $keys = [];
            }
            Cache::forget($indexKey);

            foreach ($keys as $taggedKey) {
                $result = Cache::forget($taggedKey);
                $success = $success && $result;
                $this->removeKeyFromGlobalIndex($taggedKey);

                $taggedMeta = $this->getTaggedKeyMetadata($taggedKey);
                if (!empty($taggedMeta)) {
                    $this->removeKeyFromIndexes($taggedKey, $taggedMeta, $tag);
                }

                $this->removeTaggedKeyMetadata($taggedKey);
            }
        }

        return $success;
    }

    private function registerTaggedEntry(string $namespacedKey, array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        foreach ($tags as $tag) {
            $this->addKeyToTagIndex($tag, $namespacedKey);
        }

        $this->storeTaggedKeyMetadata($namespacedKey, $tags);
        $this->addKeyToGlobalIndex($namespacedKey);
    }

    private function addKeyToTagIndex(string $tag, string $key): void
    {
        $tagHash = $this->buildSingleTagHash($tag);
        $indexKey = $this->buildTagIndexKey($tagHash);
        $index = Cache::get($indexKey, []);

        if (!is_array($index)) {
            $index = [];
        }

        if (!in_array($key, $index, true)) {
            $index[] = $key;
            Cache::forever($indexKey, $index);
        }
    }

    private function removeKeyFromTagIndex(string $tag, string $key): void
    {
        $tagHash = $this->buildSingleTagHash($tag);
        $indexKey = $this->buildTagIndexKey($tagHash);
        $index = Cache::get($indexKey, []);

        if (!is_array($index) || empty($index)) {
            return;
        }

        $filtered = array_values(array_diff($index, [$key]));

        if (empty($filtered)) {
            Cache::forget($indexKey);
            return;
        }

        Cache::forever($indexKey, $filtered);
    }

    private function removeKeyFromIndexes(string $key, array $tags, string $currentTag): void
    {
        foreach ($tags as $tag) {
            if ($tag === $currentTag) {
                continue;
            }

            $this->removeKeyFromTagIndex($tag, $key);
        }
    }

    private function storeTaggedKeyMetadata(string $key, array $tags): void
    {
        Cache::forever($this->buildTagMetaKey($key), $tags);
    }

    private function getTaggedKeyMetadata(string $key): array
    {
        $meta = Cache::get($this->buildTagMetaKey($key), []);
        return is_array($meta) ? $meta : [];
    }

    private function removeTaggedKeyMetadata(string $key): void
    {
        Cache::forget($this->buildTagMetaKey($key));
    }

    private function addKeyToGlobalIndex(string $key): void
    {
        $indexKey = $this->getGlobalIndexKey();
        $index = Cache::get($indexKey, []);

        if (!in_array($key, $index, true)) {
            $index[] = $key;
            Cache::forever($indexKey, $index);
        }
    }

    private function removeKeyFromGlobalIndex(string $key): void
    {
        $indexKey = $this->getGlobalIndexKey();
        $index = Cache::get($indexKey, []);
        $filtered = array_values(array_diff($index, [$key]));
        Cache::forever($indexKey, $filtered);
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
