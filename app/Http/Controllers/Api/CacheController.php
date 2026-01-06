<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Cache\ArrayStore;
use Illuminate\Contracts\Cache\TaggableStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use ReflectionClass;
use Src\Common\Services\CacheService as CommonCacheService;

class CacheController extends Controller
{
    private const DEFAULT_TTL = 3600;
    private const JSON_OPTIONS = JSON_PRESERVE_ZERO_FRACTION;

    private ?Connection $redisConnection = null;
    private CommonCacheService $commonCacheService;

    public function __construct(CommonCacheService $commonCacheService)
    {
        $this->commonCacheService = $commonCacheService;
    }

    public function getStats(): JsonResponse
    {
        try {
            $connection = $this->getRedisConnection();
            $stats = $this->buildStatsPayload($connection);

            return $this->respond([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'error' => 'Failed to get cache statistics',
                'message' => $e->getMessage(),
                'code' => 'CACHE_STATS_ERROR',
            ], 500);
        }
    }

    public function getConfig(): JsonResponse
    {
        try {
            $store = Cache::getStore();
            $prefix = method_exists($store, 'getPrefix') ? $store->getPrefix() : '';

            return $this->respond([
                'success' => true,
                'data' => [
                    'driver' => config('cache.default'),
                    'default_ttl' => self::DEFAULT_TTL,
                    'prefix' => $prefix,
                    'serializer' => 'php',
                    'compression' => 'none',
                    'tags_enabled' => $this->cacheStoreSupportsTags(),
                    'warmup_enabled' => true,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'error' => 'Failed to get cache configuration',
                'message' => $e->getMessage(),
                'code' => 'CACHE_CONFIG_ERROR',
            ], 500);
        }
    }

    public function invalidateKey(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        try {
            $key = $request->input('key');
            $deleted = Cache::forget($key) ? 1 : 0;

            return $this->respond([
                'success' => true,
                'data' => [
                    'invalidated_keys' => $deleted,
                    'message' => $deleted > 0
                        ? "Cache key '{$key}' invalidated successfully."
                        : "Cache key '{$key}' was not present.",
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'error' => 'Failed to invalidate cache key',
                'message' => $e->getMessage(),
                'code' => 'CACHE_INVALIDATION_ERROR',
            ], 500);
        }
    }

    public function invalidateTags(Request $request): JsonResponse
    {
        $request->validate([
            'tags' => 'required|array',
            'tags.*' => 'string',
        ]);

        try {
            $tags = array_values(array_unique(array_filter($request->input('tags', []), 'is_string')));

            $invalidated = 0;
            $message = 'Cache tags invalidated successfully.';

            if ($this->cacheStoreSupportsTags() && !empty($tags)) {
                $invalidated = $this->flushTags($tags);
                if ($invalidated === 0) {
                    $message = 'No cache entries matched the provided tags.';
                }
            } else {
                $message = 'Current cache store does not support tags.';
            }

            if (!empty($tags)) {
                $this->commonCacheService->flushByTags($tags);
            }

            return $this->respond([
                'success' => true,
                'data' => [
                    'invalidated_keys' => $invalidated,
                    'message' => $message,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'error' => 'Failed to invalidate cache tags',
                'message' => $e->getMessage(),
                'code' => 'CACHE_INVALIDATION_ERROR',
            ], 500);
        }
    }

    public function invalidatePattern(Request $request): JsonResponse
    {
        $request->validate([
            'pattern' => 'required|string',
        ]);

        $pattern = $request->input('pattern');

        if (!$this->isValidPattern($pattern)) {
            return $this->respond([
                'success' => false,
                'error' => [
                    'id' => 'CACHE_INVALID_PATTERN',
                    'message' => "Invalid cache pattern '{$pattern}'.",
                    'code' => 'CACHE_INVALID_PATTERN',
                ],
            ], 400);
        }

        try {
            $deleted = $this->forgetPatternKeys($pattern);

            return $this->respond([
                'success' => true,
                'data' => [
                    'invalidated_keys' => $deleted,
                    'message' => $deleted > 0
                        ? "Pattern '{$pattern}' cleared."
                        : "No cache entries matched '{$pattern}'.",
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'error' => 'Failed to invalidate cache pattern',
                'message' => $e->getMessage(),
                'code' => 'CACHE_INVALIDATION_ERROR',
            ], 500);
        }
    }

    public function warmUp(Request $request): JsonResponse
    {
        $request->validate([
            'keys' => 'required|array',
            'keys.*' => 'string',
        ]);

        try {
            $keys = $this->sanitizeKeys($request->input('keys', []));
            $warmedKeys = [];

            foreach ($keys as $key) {
                Cache::put($key, [
                    'cached_at' => now()->toISOString(),
                    'source' => 'api_warmup',
                ], self::DEFAULT_TTL);
                $warmedKeys[] = $key;
            }

            return $this->respond([
                'success' => true,
                'data' => [
                    'warmed_keys' => $warmedKeys,
                    'message' => 'Cache warmup completed.',
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'error' => 'Cache warm up failed',
                'message' => $e->getMessage(),
                'code' => 'CACHE_WARMUP_ERROR',
            ], 500);
        }
    }

    public function clearAll(): JsonResponse
    {
        try {
            Cache::flush();

            return $this->respond([
                'success' => true,
                'data' => [
                    'cleared_keys' => 0,
                    'message' => 'Cache cleared successfully.',
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->respond([
                'success' => false,
                'error' => 'Cache clear failed',
                'message' => $e->getMessage(),
                'code' => 'CACHE_CLEAR_ERROR',
            ], 500);
        }
    }

    public function error(): JsonResponse
    {
        try {
            throw new \RuntimeException('Cache error endpoint triggered for diagnostics');
        } catch (\Throwable $e) {
            $response = $this->respond([
                'success' => false,
                'error' => [
                    'id' => 'cache_error_endpoint',
                    'code' => 'CACHE_ERROR_ENDPOINT',
                    'message' => $e->getMessage(),
                    'details' => [],
                ],
                'message' => 'Cache error encountered',
                'code' => 'CACHE_ERROR_ENDPOINT',
            ], 500);
            $response->headers->set('X-Skip-Error-Envelope', '1');
            return $response;
        }
    }

    private function respond(array $payload, int $status = 200): JsonResponse
    {
        return response()->json($payload, $status, [], self::JSON_OPTIONS);
    }

    private function buildStatsPayload(?Connection $connection): array
    {
        $payload = [
            'driver' => config('cache.default'),
            'taggable' => $this->cacheStoreSupportsTags(),
            'supports_patterns' => true,
            'redis_available' => $connection !== null,
            'hit_rate' => 0.0,
            'miss_rate' => 0.0,
            'total_keys' => 0,
            'memory_usage' => 'N/A',
            'uptime' => 1,
            'connected_clients' => 0,
            'used_memory_human' => 'N/A',
            'redis_version' => 'unknown',
        ];

        if ($connection === null) {
            return $payload;
        }

        try {
            $info = $connection->info();
            $hits = (int) ($info['keyspace_hits'] ?? 0);
            $misses = (int) ($info['keyspace_misses'] ?? 0);
            $total = max(1, $hits + $misses);

            $payload['hit_rate'] = round($hits / $total, 4);
            $payload['miss_rate'] = round($misses / $total, 4);
            $payload['total_keys'] = (int) $connection->dbsize();
            $payload['memory_usage'] = $info['used_memory_human'] ?? $payload['memory_usage'];
            $payload['uptime'] = (int) ($info['uptime_in_seconds'] ?? $payload['uptime']);
            $payload['connected_clients'] = (int) ($info['connected_clients'] ?? 0);
            $payload['used_memory_human'] = $info['used_memory_human'] ?? $payload['used_memory_human'];
            $payload['redis_version'] = $info['redis_version'] ?? $payload['redis_version'];
        } catch (\Throwable $e) {
            // Silently degrade to default stats
        }

        return $payload;
    }

    private function sanitizeKeys(array $keys): array
    {
        return array_values(array_unique(array_filter(array_map('trim', $keys), 'strlen')));
    }

    private function cacheStoreSupportsTags(): bool
    {
        try {
            $store = Cache::getStore();
            return $store instanceof TaggableStore;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function flushTags(array $tags): int
    {
        $store = Cache::getStore();

        if ($store instanceof ArrayStore) {
            return $this->flushTagsInArrayStore($tags, $store);
        }

        try {
            Cache::tags($tags)->flush();
        } catch (\Throwable $e) {
            // Fall back silently
        }

        return 0;
    }

    private function flushTagsInArrayStore(array $tags, ArrayStore $store): int
    {
        $storage = $this->getArrayStoreStorage($store);
        $tagIdMap = $this->extractTagIdMap($storage);

        if (empty($tagIdMap)) {
            return 0;
        }

        $targetTags = array_values(array_intersect($tags, array_keys($tagIdMap)));

        if (empty($targetTags)) {
            return 0;
        }

        $namespaceMap = $this->buildNamespaceMap($tagIdMap, $targetTags);
        $deleted = 0;

        foreach (array_keys($storage ?? []) as $cachedKey) {
            if (str_starts_with($cachedKey, 'tag:') || !str_contains($cachedKey, ':')) {
                continue;
            }

            [$namespace] = explode(':', $cachedKey, 2);

            if (!isset($namespaceMap[$namespace])) {
                continue;
            }

            $entryTags = $namespaceMap[$namespace];

            if (!empty(array_intersect($entryTags, $targetTags))) {
                $deleted += $store->forget($cachedKey) ? 1 : 0;
            }
        }

        $this->resetTagIds($store, $targetTags);

        return $deleted;
    }

    private function getArrayStoreStorage(ArrayStore $store): array
    {
        $reflection = new ReflectionClass($store);

        if (!$reflection->hasProperty('storage')) {
            return [];
        }

        $property = $reflection->getProperty('storage');
        $property->setAccessible(true);

        return $property->getValue($store) ?? [];
    }

    private function extractTagIdMap(array $storage): array
    {
        $tagIds = [];

        foreach ($storage as $key => $entry) {
            if (!str_starts_with($key, 'tag:') || !str_ends_with($key, ':key')) {
                continue;
            }

            $parts = explode(':', $key);
            $tagName = $parts[1] ?? null;

            if (!$tagName || !isset($entry['value'])) {
                continue;
            }

            $tagIds[$tagName] = $entry['value'];
        }

        return $tagIds;
    }

    private function buildNamespaceMap(array $tagIdMap, array $targetTags, int $maxSize = 5, int $maxCombos = 512): array
    {
        $namespaceMap = [];
        $tagNames = array_keys($tagIdMap);

        $subsets = $this->generateSubsetsCoveringTargets($tagNames, $targetTags, $maxSize, $maxCombos);

        foreach ($subsets as $subset) {
            foreach ($this->permutations($subset) as $permutation) {
                $ids = array_map(fn ($tag) => $tagIdMap[$tag], $permutation);
                $namespace = sha1(implode('|', $ids));
                $namespaceMap[$namespace] = $permutation;
            }
        }

        return $namespaceMap;
    }

    private function generateSubsetsCoveringTargets(array $items, array $targets, int $maxSize, int $maxCombos): array
    {
        $subsets = [];
        $total = count($items);

        if ($total === 0) {
            return $subsets;
        }

        $limit = 1 << $total;

        for ($mask = 1; $mask < $limit; $mask++) {
            $subset = [];

            for ($i = 0; $i < $total; $i++) {
                if ($mask & (1 << $i)) {
                    $subset[] = $items[$i];
                }
            }

            if (count($subset) > $maxSize) {
                continue;
            }

            if (empty(array_intersect($subset, $targets))) {
                continue;
            }

            $subsets[] = $subset;

            if (count($subsets) >= $maxCombos) {
                break;
            }
        }

        return $subsets;
    }

    private function permutations(array $items): array
    {
        if (count($items) <= 1) {
            return [$items];
        }

        $perms = [];

        $count = count($items);
        for ($i = 0; $i < $count; $i++) {
            $current = $items[$i];
            $remaining = $items;
            array_splice($remaining, $i, 1);

            foreach ($this->permutations($remaining) as $perm) {
                array_unshift($perm, $current);
                $perms[] = $perm;
            }
        }

        return $perms;
    }

    private function resetTagIds(ArrayStore $store, array $tags): void
    {
        foreach ($tags as $tag) {
            $store->forever("tag:{$tag}:key", str_replace('.', '', uniqid('', true)));
        }
    }

    private function forgetPatternKeys(string $pattern): int
    {
        $deleted = 0;
        $store = Cache::getStore();

        if ($store instanceof ArrayStore) {
            $deleted += $this->deletePatternFromArrayStore($store, $pattern);
        } elseif ($connection = $this->getRedisConnection()) {
            $keys = $connection->keys($pattern);
            if (!empty($keys)) {
                $deleted += $connection->del($keys);
            }
        }

        return $deleted;
    }

    private function deletePatternFromArrayStore(ArrayStore $store, string $pattern): int
    {
        $deleted = 0;
        $reflection = new ReflectionClass($store);

        if (!$reflection->hasProperty('storage')) {
            return $deleted;
        }

        $property = $reflection->getProperty('storage');
        $property->setAccessible(true);
        $storage = $property->getValue($store);

        foreach (array_keys($storage ?? []) as $cachedKey) {
            if (fnmatch($pattern, $cachedKey)) {
                $deleted += $store->forget($cachedKey) ? 1 : 0;
            }
        }

        return $deleted;
    }

    private function isValidPattern(string $pattern): bool
    {
        return (bool) preg_match('/^[A-Za-z0-9_\-\*\?:.]+$/', $pattern);
    }

    private function getRedisConnection(): ?Connection
    {
        if ($this->redisConnection !== null) {
            return $this->redisConnection;
        }

        if (!$this->isRedisCacheDriver() || !$this->isRedisConfigured()) {
            return null;
        }

        try {
            $connection = Redis::connection();
            $pong = $connection->ping();

            if ($pong === 'PONG' || $pong === true || $pong === 'pong') {
                $this->redisConnection = $connection;
                return $connection;
            }
        } catch (\Throwable $e) {
            // Redis not available
        }

        return null;
    }

    private function isRedisCacheDriver(): bool
    {
        $default = config('cache.default');

        if ($default === 'redis') {
            return true;
        }

        $storeDriver = config("cache.stores.{$default}.driver");
        return $storeDriver === 'redis';
    }

    private function isRedisConfigured(): bool
    {
        $connectionConfig = config('database.redis.default');

        if (!is_array($connectionConfig)) {
            return false;
        }

        return !empty($connectionConfig['host'] ?? $connectionConfig['url'] ?? '');
    }
}
