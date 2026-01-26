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
            ]);
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
            Cache::forget($key);
            $success = $this->cacheService->invalidate($key);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => "Cache key '{$key}' invalidated successfully",
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
                return response()->json([
                    'success' => true,
                    'message' => 'Cache tags invalidated successfully',
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
            $success = $this->cacheService->invalidate(null, null, $pattern);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => "Cache pattern '{$pattern}' invalidated successfully",
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
            'data_provider' => 'sometimes|string|in:dashboard,projects,tasks,users',
        ]);

        try {
            $keys = $request->input('keys');
            $providerKey = $request->input('data_provider', 'dashboard');
            $dataProviderCallback = $this->getDataProvider($providerKey);
            $success = $this->cacheService->warmUp($keys, $dataProviderCallback);
            
            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Cache warmed up successfully',
                    'data' => [
                        'keys' => $keys,
                        'provider' => $providerKey,
                    ],
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
                return response()->json([
                    'success' => true,
                    'message' => 'All cache cleared successfully',
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
                'strategies' => [
                    'user_data' => ['ttl' => 1800, 'tags' => ['user'], 'strategy' => 'write_through'],
                    'dashboard_data' => ['ttl' => 300, 'tags' => ['dashboard'], 'strategy' => 'write_behind'],
                    'project_data' => ['ttl' => 3600, 'tags' => ['project'], 'strategy' => 'write_through'],
                    'task_data' => ['ttl' => 1800, 'tags' => ['task'], 'strategy' => 'write_behind'],
                    'analytics_data' => ['ttl' => 600, 'tags' => ['analytics'], 'strategy' => 'write_through'],
                    'permissions' => ['ttl' => 3600, 'tags' => ['permissions'], 'strategy' => 'write_through'],
                    'tenant_data' => ['ttl' => 7200, 'tags' => ['tenant'], 'strategy' => 'write_through'],
                ],
                'default_ttl' => 3600,
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
