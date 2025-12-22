<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;

/**
 * API Response Cache Service
 * 
 * Handles caching of API responses for performance optimization
 */
class ApiResponseCacheService
{
    private const CACHE_PREFIX = 'api:';
    private const DEFAULT_TTL = 300; // 5 minutes
    private const LONG_TTL = 3600; // 1 hour
    private const SHORT_TTL = 60; // 1 minute

    /**
     * Cache API response
     */
    public function cacheResponse(string $key, $response, int $ttl = null): void
    {
        $ttl = $ttl ?? self::DEFAULT_TTL;
        $cacheKey = $this->getCacheKey($key);

        $cacheData = [
            'response' => $response,
            'cached_at' => now()->toISOString(),
            'expires_at' => now()->addSeconds($ttl)->toISOString(),
            'ttl' => $ttl,
        ];

        Cache::put($cacheKey, $cacheData, $ttl);

        Log::debug('API response cached', [
            'cache_key' => $cacheKey,
            'ttl' => $ttl
        ]);
    }

    /**
     * Get cached API response
     */
    public function getCachedResponse(string $key): ?array
    {
        $cacheKey = $this->getCacheKey($key);
        $cachedData = Cache::get($cacheKey);

        if (!$cachedData) {
            return null;
        }

        // Check if response is still valid
        if (isset($cachedData['expires_at'])) {
            $expiresAt = \Carbon\Carbon::parse($cachedData['expires_at']);
            if ($expiresAt->isPast()) {
                Cache::forget($cacheKey);
                return null;
            }
        }

        return $cachedData['response'] ?? null;
    }

    /**
     * Generate cache key from request
     */
    public function generateCacheKey(Request $request, string $userId = null): string
    {
        $path = $request->path();
        $query = $request->query();
        $method = $request->method();

        // Sort query parameters for consistent keys
        ksort($query);

        $keyData = [
            'path' => $path,
            'method' => $method,
            'query' => $query,
            'user_id' => $userId,
        ];

        return Hash::make(serialize($keyData));
    }

    /**
     * Determine TTL based on endpoint
     */
    public function getTtlForEndpoint(string $endpoint): int
    {
        $longCacheEndpoints = [
            'dashboard',
            'projects',
            'team',
            'clients',
            'templates',
        ];

        $shortCacheEndpoints = [
            'tasks',
            'notifications',
            'activities',
        ];

        if (in_array($endpoint, $longCacheEndpoints)) {
            return self::LONG_TTL;
        }

        if (in_array($endpoint, $shortCacheEndpoints)) {
            return self::SHORT_TTL;
        }

        return self::DEFAULT_TTL;
    }

    /**
     * Cache dashboard data
     */
    public function cacheDashboardData(string $userId, array $data): void
    {
        $key = "dashboard:user:{$userId}";
        $this->cacheResponse($key, $data, self::LONG_TTL);
    }

    /**
     * Get cached dashboard data
     */
    public function getCachedDashboardData(string $userId): ?array
    {
        $key = "dashboard:user:{$userId}";
        return $this->getCachedResponse($key);
    }

    /**
     * Cache projects data
     */
    public function cacheProjectsData(string $userId, array $data): void
    {
        $key = "projects:user:{$userId}";
        $this->cacheResponse($key, $data, self::LONG_TTL);
    }

    /**
     * Get cached projects data
     */
    public function getCachedProjectsData(string $userId): ?array
    {
        $key = "projects:user:{$userId}";
        return $this->getCachedResponse($key);
    }

    /**
     * Cache tasks data
     */
    public function cacheTasksData(string $userId, array $data): void
    {
        $key = "tasks:user:{$userId}";
        $this->cacheResponse($key, $data, self::SHORT_TTL);
    }

    /**
     * Get cached tasks data
     */
    public function getCachedTasksData(string $userId): ?array
    {
        $key = "tasks:user:{$userId}";
        return $this->getCachedResponse($key);
    }

    /**
     * Cache team data
     */
    public function cacheTeamData(string $userId, array $data): void
    {
        $key = "team:user:{$userId}";
        $this->cacheResponse($key, $data, self::LONG_TTL);
    }

    /**
     * Get cached team data
     */
    public function getCachedTeamData(string $userId): ?array
    {
        $key = "team:user:{$userId}";
        return $this->getCachedResponse($key);
    }

    /**
     * Clear user cache
     */
    public function clearUserCache(string $userId): void
    {
        $patterns = [
            "dashboard:user:{$userId}",
            "projects:user:{$userId}",
            "tasks:user:{$userId}",
            "team:user:{$userId}",
        ];

        foreach ($patterns as $pattern) {
            $cacheKey = $this->getCacheKey($pattern);
            Cache::forget($cacheKey);
        }

        Log::info('User API cache cleared', ['user_id' => $userId]);
    }

    /**
     * Clear endpoint cache
     */
    public function clearEndpointCache(string $endpoint): void
    {
        // This is a simplified implementation
        // In production, you might want to use Redis SCAN to find and delete keys
        $pattern = self::CACHE_PREFIX . $endpoint . '*';
        
        try {
            $keys = Cache::store('redis')->keys($pattern);
            foreach ($keys as $key) {
                Cache::forget($key);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear endpoint cache', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage()
            ]);
        }

        Log::info('Endpoint cache cleared', ['endpoint' => $endpoint]);
    }

    /**
     * Clear all API cache
     */
    public function clearAllCache(): void
    {
        Cache::flush();
        Log::info('All API cache cleared');
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        return [
            'cache_driver' => config('cache.default'),
            'redis_connected' => $this->isRedisConnected(),
            'cache_prefix' => self::CACHE_PREFIX,
            'default_ttl' => self::DEFAULT_TTL,
            'long_ttl' => self::LONG_TTL,
            'short_ttl' => self::SHORT_TTL,
        ];
    }

    /**
     * Check if response should be cached
     */
    public function shouldCacheResponse(Request $request, Response $response): bool
    {
        // Don't cache non-GET requests
        if ($request->method() !== 'GET') {
            return false;
        }

        // Don't cache error responses
        if ($response->getStatusCode() >= 400) {
            return false;
        }

        // Don't cache if no-cache header is present
        if ($request->header('Cache-Control') === 'no-cache') {
            return false;
        }

        // Don't cache if response has no-cache header
        if ($response->headers->get('Cache-Control') === 'no-cache') {
            return false;
        }

        return true;
    }

    /**
     * Get cache key
     */
    private function getCacheKey(string $key): string
    {
        return self::CACHE_PREFIX . $key;
    }

    /**
     * Check if Redis is connected
     */
    private function isRedisConnected(): bool
    {
        try {
            Cache::store('redis')->get('test');
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Warm up cache for user
     */
    public function warmUpUserCache(string $userId): void
    {
        // This would typically call the actual API endpoints to populate cache
        Log::info('User cache warm-up initiated', ['user_id' => $userId]);
        
        // In a real implementation, you would:
        // 1. Call dashboard API
        // 2. Call projects API
        // 3. Call tasks API
        // 4. Call team API
        // This would populate the cache with fresh data
    }

    /**
     * Get cache hit rate
     */
    public function getCacheHitRate(): array
    {
        // This is a simplified implementation
        // In production, you might want to track cache hits/misses in Redis
        return [
            'hit_rate' => 0.0,
            'miss_rate' => 0.0,
            'total_requests' => 0,
            'cached_requests' => 0,
        ];
    }
}
