<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

/**
 * Service quản lý caching strategy cho toàn bộ hệ thống
 * Sử dụng Redis làm cache driver chính với các TTL khác nhau
 */
class CacheService
{
    // Cache TTL constants (in seconds)
    public const TTL_SHORT = 300;      // 5 minutes - for frequently changing data
    public const TTL_MEDIUM = 1800;    // 30 minutes - for moderate changing data
    public const TTL_LONG = 3600;      // 1 hour - for stable data
    public const TTL_VERY_LONG = 86400; // 24 hours - for rarely changing data

    /**
     * Cache project data với hierarchical invalidation
     * 
     * @param int $projectId
     * @param callable $callback
     * @param int $ttl
     * @return mixed
     */
    public function cacheProject(int $projectId, callable $callback, int $ttl = self::TTL_MEDIUM)
    {
        $key = "project:{$projectId}";
        
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Cache project components với nested structure
     * 
     * @param int $projectId
     * @param callable $callback
     * @return mixed
     */
    public function cacheProjectComponents(int $projectId, callable $callback)
    {
        $key = "project:{$projectId}:components";
        
        return Cache::remember($key, self::TTL_MEDIUM, $callback);
    }

    /**
     * Cache dashboard data với tenant isolation
     * 
     * @param string $tenantId
     * @param callable $callback
     * @param int $ttl
     * @return mixed
     */
    public function cacheDashboardData(string $tenantId, callable $callback, int $ttl = self::TTL_MEDIUM)
    {
        $key = "dashboard:{$tenantId}";
        
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Cache KPI data với tenant isolation
     * 
     * @param string $tenantId
     * @param callable $callback
     * @param int $ttl
     * @return mixed
     */
    public function cacheKPIs(string $tenantId, callable $callback, int $ttl = self::TTL_SHORT)
    {
        $key = "kpis:{$tenantId}";
        
        return Cache::remember($key, $ttl, $callback);
    }

    /**
     * Cache user permissions cho RBAC system
     * 
     * @param int $userId
     * @param int|null $projectId
     * @param callable $callback
     * @return mixed
     */
    public function cacheUserPermissions(int $userId, ?int $projectId, callable $callback)
    {
        $key = $projectId 
            ? "user:{$userId}:project:{$projectId}:permissions"
            : "user:{$userId}:permissions";
            
        return Cache::remember($key, self::TTL_LONG, $callback);
    }

    /**
     * Cache dashboard statistics
     * 
     * @param int $userId
     * @param callable $callback
     * @return mixed
     */
    public function cacheDashboardStats(int $userId, callable $callback)
    {
        $key = "user:{$userId}:dashboard:stats";
        
        return Cache::remember($key, self::TTL_SHORT, $callback);
    }

    /**
     * Cache notification counts
     * 
     * @param int $userId
     * @param callable $callback
     * @return mixed
     */
    public function cacheNotificationCounts(int $userId, callable $callback)
    {
        $key = "user:{$userId}:notifications:count";
        
        return Cache::remember($key, self::TTL_SHORT, $callback);
    }

    /**
     * Invalidate all caches related to a project
     * 
     * @param int $projectId
     * @return void
     */
    public function invalidateProject(int $projectId): void
    {
        $patterns = [
            "project:{$projectId}*",
            "*:project:{$projectId}:*"
        ];

        foreach ($patterns as $pattern) {
            $this->invalidateByPattern($pattern);
        }
    }

    /**
     * Invalidate user-related caches
     * 
     * @param int $userId
     * @return void
     */
    public function invalidateUser(int $userId): void
    {
        $this->invalidateByPattern("user:{$userId}*");
    }

    /**
     * Invalidate caches by pattern using Redis SCAN
     * 
     * @param string $pattern
     * @return void
     */
    private function invalidateByPattern(string $pattern): void
    {
        $redis = Redis::connection();
        $cursor = 0;
        
        do {
            $result = $redis->scan($cursor, ['match' => $pattern, 'count' => 100]);
            $cursor = $result[0];
            $keys = $result[1];
            
            if (!empty($keys)) {
                $redis->del($keys);
            }
        } while ($cursor !== 0);
    }

    /**
     * Warm up critical caches
     * 
     * @param int $userId
     * @return void
     */
    public function warmUpUserCaches(int $userId): void
    {
        // Warm up user permissions
        $this->cacheUserPermissions($userId, null, function () {
            // TODO: Implement user permissions retrieval logic
            return [];
        });

        // Warm up dashboard stats
        $this->cacheDashboardStats($userId, function () {
            // TODO: Implement dashboard stats retrieval logic
            return [];
        });
    }

    /**
     * Get cache statistics
     * 
     * @return array
     */
    public function getStats(): array
    {
        $redis = Redis::connection();
        
        return [
            'memory_usage' => $redis->info('memory')['used_memory_human'] ?? 'N/A',
            'connected_clients' => $redis->info('clients')['connected_clients'] ?? 0,
            'total_commands_processed' => $redis->info('stats')['total_commands_processed'] ?? 0,
            'keyspace_hits' => $redis->info('stats')['keyspace_hits'] ?? 0,
            'keyspace_misses' => $redis->info('stats')['keyspace_misses'] ?? 0,
        ];
    }
}