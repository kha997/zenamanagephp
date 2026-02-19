<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AdvancedCacheService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Cache Management Controller
 * 
 * Provides endpoints for:
 * - Cache statistics and monitoring
 * - Cache invalidation
 * - Cache warming
 * - Cache configuration
 */
class CacheController extends Controller
{
    private AdvancedCacheService $cacheService;

    public function __construct(AdvancedCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Get cache statistics
     */
    public function getStats(): JsonResponse
    {
        try {
            $stats = $this->cacheService->getStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats,
                'timestamp' => now()->toISOString(),
            ], 200, [], JSON_PRESERVE_ZERO_FRACTION);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get cache statistics',
                'message' => $e->getMessage(),
                'code' => 'CACHE_STATS_ERROR',
            ], 500);
        }
    }

    /**
     * Invalidate cache by key
     */
    public function invalidateKey(Request $request): JsonResponse
    {
        $request->validate([
            'key' => 'required|string',
        ]);

        try {
            $key = $request->input('key');
            $tenantId = $request->header('X-Tenant-ID', 'default');
            $prefixedKey = "tenant:{$tenantId}:{$key}";
            $keyExisted = Cache::has($prefixedKey) || Cache::has($key);
            $success = $this->cacheService->invalidate($key);
            
            if ($success) {
                $message = "Cache key '{$key}' invalidated successfully";
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'invalidated_keys' => $keyExisted ? 1 : 0,
                        'message' => $message,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to invalidate cache key',
                    'code' => 'CACHE_INVALIDATION_ERROR',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Cache invalidation failed',
                'message' => $e->getMessage(),
                'code' => 'CACHE_INVALIDATION_ERROR',
            ], 500);
        }
    }

    /**
     * Invalidate cache by tags
     */
    public function invalidateTags(Request $request): JsonResponse
    {
        $request->validate([
            'tags' => 'required|array',
            'tags.*' => 'string',
        ]);

        try {
            $tags = $request->input('tags');
            $success = $this->cacheService->invalidate(null, $tags);
            
            if ($success) {
                $message = 'Cache tags invalidated successfully';
                if (Cache::supportsTags()) {
                    Cache::tags($tags)->flush();
                }
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'invalidated_keys' => count($tags),
                        'message' => $message,
                    ],
                    'tags' => $tags,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to invalidate cache tags',
                    'code' => 'CACHE_INVALIDATION_ERROR',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Cache tag invalidation failed',
                'message' => $e->getMessage(),
                'code' => 'CACHE_INVALIDATION_ERROR',
            ], 500);
        }
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidatePattern(Request $request): JsonResponse
    {
        $request->validate([
            'pattern' => 'required|string',
        ]);

        try {
            $pattern = $request->input('pattern');
            if (!preg_match('/^[\w\-\:\*]+$/', $pattern)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid cache pattern',
                    'message' => 'Cache pattern contains unsupported characters',
                    'code' => 'CACHE_PATTERN_INVALID',
                ], 400);
            }
            $success = $this->cacheService->invalidate(null, null, $pattern);
            
            if ($success) {
                $message = "Cache pattern '{$pattern}' invalidated successfully";
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'invalidated_keys' => 0,
                        'message' => $message,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to invalidate cache pattern',
                    'code' => 'CACHE_INVALIDATION_ERROR',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Cache pattern invalidation failed',
                'message' => $e->getMessage(),
                'code' => 'CACHE_INVALIDATION_ERROR',
            ], 500);
        }
    }

    /**
     * Warm up cache
     */
    public function warmUp(Request $request): JsonResponse
    {
        $request->validate([
            'keys' => 'required|array',
            'keys.*' => 'string',
            'data_provider' => 'required|string|in:dashboard,projects,tasks,users',
        ]);

        try {
            $keys = $request->input('keys');
            $dataProvider = $request->input('data_provider');
            
            $dataProviderCallback = $this->getDataProvider($dataProvider);
            $success = $this->cacheService->warmUp($keys, $dataProviderCallback);
            
            if ($success) {
                $message = 'Cache warmed up successfully';
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'warmed_keys' => $keys,
                        'message' => $message,
                    ],
                    'keys' => $keys,
                    'provider' => $dataProvider,
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to warm up cache',
                    'code' => 'CACHE_WARMUP_ERROR',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Cache warm up failed',
                'message' => $e->getMessage(),
                'code' => 'CACHE_WARMUP_ERROR',
            ], 500);
        }
    }

    /**
     * Clear all cache
     */
    public function clearAll(): JsonResponse
    {
        try {
            // This would typically require admin permissions
            $success = $this->cacheService->invalidate(null, null, '*');
            
            if ($success) {
                $message = 'All cache cleared successfully';
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'data' => [
                        'cleared_keys' => [],
                        'message' => $message,
                    ],
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to clear all cache',
                    'code' => 'CACHE_CLEAR_ERROR',
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Cache clear failed',
                'message' => $e->getMessage(),
                'code' => 'CACHE_CLEAR_ERROR',
            ], 500);
        }
    }

    /**
     * Get cache configuration
     */
    public function getConfig(): JsonResponse
    {
        try {
            $config = [
                'driver' => config('cache.default'),
                'default_ttl' => config('cache.ttl', 3600),
                'prefix' => config('cache.prefix'),
                'serializer' => config('cache.serializer', 'php'),
                'compression' => config('cache.compression', 'none'),
                'tags_enabled' => Cache::supportsTags(),
                'warmup_enabled' => true,
                'strategies' => [
                    'user_data' => ['ttl' => 1800, 'tags' => ['user'], 'strategy' => 'write_through'],
                    'dashboard_data' => ['ttl' => 300, 'tags' => ['dashboard'], 'strategy' => 'write_behind'],
                    'project_data' => ['ttl' => 3600, 'tags' => ['project'], 'strategy' => 'write_through'],
                    'task_data' => ['ttl' => 1800, 'tags' => ['task'], 'strategy' => 'write_behind'],
                    'analytics_data' => ['ttl' => 600, 'tags' => ['analytics'], 'strategy' => 'write_through'],
                    'permissions' => ['ttl' => 3600, 'tags' => ['permissions'], 'strategy' => 'write_through'],
                    'tenant_data' => ['ttl' => 7200, 'tags' => ['tenant'], 'strategy' => 'write_through'],
                ],
                'short_ttl' => 300,
                'long_ttl' => 86400,
                'very_long_ttl' => 604800,
            ];

            return response()->json([
                'success' => true,
                'data' => $config,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get cache configuration',
                'message' => $e->getMessage(),
                'code' => 'CACHE_CONFIG_ERROR',
            ], 500);
        }
    }

    /**
     * Get data provider callback
     */
    private function getDataProvider(string $provider): callable
    {
        return match ($provider) {
            'dashboard' => function ($key) {
                // Mock dashboard data provider
                return [
                    'key' => $key,
                    'data' => 'dashboard_data_' . time(),
                    'cached_at' => now()->toISOString(),
                ];
            },
            'projects' => function ($key) {
                // Mock projects data provider
                return [
                    'key' => $key,
                    'data' => 'projects_data_' . time(),
                    'cached_at' => now()->toISOString(),
                ];
            },
            'tasks' => function ($key) {
                // Mock tasks data provider
                return [
                    'key' => $key,
                    'data' => 'tasks_data_' . time(),
                    'cached_at' => now()->toISOString(),
                ];
            },
            'users' => function ($key) {
                // Mock users data provider
                return [
                    'key' => $key,
                    'data' => 'users_data_' . time(),
                    'cached_at' => now()->toISOString(),
                ];
            },
            default => function ($key) {
                return [
                    'key' => $key,
                    'data' => 'default_data_' . time(),
                    'cached_at' => now()->toISOString(),
                ];
            },
        };
    }
}
