<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Redis Caching Service
 * 
 * Provides intelligent caching for frequently accessed data
 */
class RedisCachingService
{
    private string $defaultStore = 'redis';
    private int $defaultTtl = 3600; // 1 hour
    private array $cacheStats = [];

    /**
     * Cache user data with intelligent invalidation
     */
    public function cacheUserData(int $userId, callable $callback, int $ttl = null): mixed
    {
        $key = "user:{$userId}";
        $ttl = $ttl ?? $this->defaultTtl;

        return Cache::store($this->defaultStore)->remember($key, $ttl, function () use ($callback, $userId) {
            $this->logCacheHit('user', $userId);
            return $callback();
        });
    }

    /**
     * Cache project data with relationships
     */
    public function cacheProjectData(string $projectId, callable $callback, int $ttl = null): mixed
    {
        $key = "project:{$projectId}";
        $ttl = $ttl ?? $this->defaultTtl;

        return Cache::store($this->defaultStore)->remember($key, $ttl, function () use ($callback, $projectId) {
            $this->logCacheHit('project', $projectId);
            return $callback();
        });
    }

    /**
     * Cache task data with filters
     */
    public function cacheTaskData(string $taskId, callable $callback, int $ttl = null): mixed
    {
        $key = "task:{$taskId}";
        $ttl = $ttl ?? $this->defaultTtl;

        return Cache::store($this->defaultStore)->remember($key, $ttl, function () use ($callback, $taskId) {
            $this->logCacheHit('task', $taskId);
            return $callback();
        });
    }

    /**
     * Cache dashboard data with tenant isolation
     */
    public function cacheDashboardData(string $tenantId, string $userId, callable $callback, int $ttl = null): mixed
    {
        $key = "dashboard:{$tenantId}:{$userId}";
        $ttl = $ttl ?? 1800; // 30 minutes for dashboard data

        return Cache::store($this->defaultStore)->remember($key, $ttl, function () use ($callback, $tenantId, $userId) {
            $this->logCacheHit('dashboard', "{$tenantId}:{$userId}");
            return $callback();
        });
    }

    /**
     * Cache analytics data with time-based invalidation
     */
    public function cacheAnalyticsData(string $type, array $filters, callable $callback, int $ttl = null): mixed
    {
        $key = "analytics:{$type}:" . md5(serialize($filters));
        $ttl = $ttl ?? 3600; // 1 hour for analytics

        return Cache::store($this->defaultStore)->remember($key, $ttl, function () use ($callback, $type, $filters) {
            $this->logCacheHit('analytics', $type);
            return $callback();
        });
    }

    /**
     * Cache search results with query-based keys
     */
    public function cacheSearchResults(string $entity, array $query, callable $callback, int $ttl = null): mixed
    {
        $key = "search:{$entity}:" . md5(serialize($query));
        $ttl = $ttl ?? 1800; // 30 minutes for search results

        return Cache::store($this->defaultStore)->remember($key, $ttl, function () use ($callback, $entity, $query) {
            $this->logCacheHit('search', $entity);
            return $callback();
        });
    }

    /**
     * Cache frequently accessed lists
     */
    public function cacheListData(string $listType, array $filters, callable $callback, int $ttl = null): mixed
    {
        $key = "list:{$listType}:" . md5(serialize($filters));
        $ttl = $ttl ?? 1800; // 30 minutes for lists

        return Cache::store($this->defaultStore)->remember($key, $ttl, function () use ($callback, $listType, $filters) {
            $this->logCacheHit('list', $listType);
            return $callback();
        });
    }

    /**
     * Invalidate cache by pattern
     */
    public function invalidateByPattern(string $pattern): int
    {
        try {
            $keys = Redis::keys($pattern);
            if (empty($keys)) {
                return 0;
            }

            $deleted = Redis::del($keys);
            $this->logCacheInvalidation($pattern, $deleted);
            
            return $deleted;

        } catch (\Exception $e) {
            Log::error('Failed to invalidate cache by pattern', [
                'pattern' => $pattern,
                'error' => $e->getMessage()
            ]);
            
            return 0;
        }
    }

    /**
     * Invalidate user-related cache
     */
    public function invalidateUserCache(int $userId): void
    {
        $patterns = [
            "user:{$userId}",
            "dashboard:*:{$userId}",
            "user_preferences:{$userId}",
            "user_permissions:{$userId}"
        ];

        foreach ($patterns as $pattern) {
            $this->invalidateByPattern($pattern);
        }
    }

    /**
     * Invalidate project-related cache
     */
    public function invalidateProjectCache(string $projectId): void
    {
        $patterns = [
            "project:{$projectId}",
            "project_tasks:{$projectId}",
            "project_documents:{$projectId}",
            "project_analytics:{$projectId}",
            "project_team:{$projectId}"
        ];

        foreach ($patterns as $pattern) {
            $this->invalidateByPattern($pattern);
        }
    }

    /**
     * Invalidate task-related cache
     */
    public function invalidateTaskCache(string $taskId): void
    {
        $patterns = [
            "task:{$taskId}",
            "task_assignments:{$taskId}",
            "task_documents:{$taskId}",
            "task_comments:{$taskId}"
        ];

        foreach ($patterns as $pattern) {
            $this->invalidateByPattern($pattern);
        }
    }

    /**
     * Warm up cache with frequently accessed data
     */
    public function warmUpCache(): void
    {
        try {
            Log::info('Starting cache warm-up process');

            // Warm up user data
            $this->warmUpUserCache();
            
            // Warm up project data
            $this->warmUpProjectCache();
            
            // Warm up dashboard data
            $this->warmUpDashboardCache();

            Log::info('Cache warm-up completed successfully');

        } catch (\Exception $e) {
            Log::error('Cache warm-up failed', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        try {
            $info = Redis::info();
            
            return [
                'redis_version' => $info['redis_version'] ?? 'unknown',
                'used_memory' => $info['used_memory_human'] ?? 'unknown',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'hit_rate' => $this->calculateHitRate($info),
                'cache_stats' => $this->cacheStats
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get cache statistics', [
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Clear all cache
     */
    public function clearAllCache(): bool
    {
        try {
            Redis::flushdb();
            $this->cacheStats = [];
            
            Log::info('All cache cleared successfully');
            return true;

        } catch (\Exception $e) {
            Log::error('Failed to clear all cache', [
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Warm up user cache
     */
    private function warmUpUserCache(): void
    {
        $users = DB::table('users')
            ->where('status', 'active')
            ->limit(100)
            ->get(['id', 'name', 'email', 'role']);

        foreach ($users as $user) {
            $this->cacheUserData($user->id, function () use ($user) {
                return $user;
            }, 7200); // 2 hours
        }
    }

    /**
     * Warm up project cache
     */
    private function warmUpProjectCache(): void
    {
        $projects = DB::table('projects')
            ->where('status', 'active')
            ->limit(50)
            ->get(['id', 'name', 'status', 'client_id']);

        foreach ($projects as $project) {
            $this->cacheProjectData($project->id, function () use ($project) {
                return $project;
            }, 3600); // 1 hour
        }
    }

    /**
     * Warm up dashboard cache
     */
    private function warmUpDashboardCache(): void
    {
        $tenants = DB::table('tenants')
            ->where('status', 'active')
            ->limit(20)
            ->get(['id']);

        foreach ($tenants as $tenant) {
            $this->cacheDashboardData($tenant->id, 'system', function () use ($tenant) {
                return [
                    'tenant_id' => $tenant->id,
                    'cached_at' => now()
                ];
            }, 1800); // 30 minutes
        }
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
        
        return round(($hits / ($hits + $misses)) * 100, 2);
    }

    /**
     * Log cache hit
     */
    private function logCacheHit(string $type, string $key): void
    {
        if (!isset($this->cacheStats[$type])) {
            $this->cacheStats[$type] = ['hits' => 0, 'misses' => 0];
        }
        
        $this->cacheStats[$type]['hits']++;
    }

    /**
     * Log cache invalidation
     */
    private function logCacheInvalidation(string $pattern, int $count): void
    {
        Log::info('Cache invalidated', [
            'pattern' => $pattern,
            'keys_deleted' => $count
        ]);
    }
}
