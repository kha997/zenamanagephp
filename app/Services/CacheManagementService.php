<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class CacheManagementService
{
    /**
     * Cache configuration
     */
    private array $cacheConfig = [
        'kpi_data' => [
            'ttl' => 300, // 5 minutes
            'tags' => ['kpi', 'dashboard'],
        ],
        'user_data' => [
            'ttl' => 1800, // 30 minutes
            'tags' => ['users', 'profiles'],
        ],
        'project_data' => [
            'ttl' => 600, // 10 minutes
            'tags' => ['projects', 'lists'],
        ],
        'task_data' => [
            'ttl' => 300, // 5 minutes
            'tags' => ['tasks', 'lists'],
        ],
        'tenant_data' => [
            'ttl' => 3600, // 1 hour
            'tags' => ['tenants', 'settings'],
        ],
        'api_responses' => [
            'ttl' => 60, // 1 minute
            'tags' => ['api', 'responses'],
        ],
    ];
    
    /**
     * Cache KPI data with tenant-specific key
     */
    public function cacheKpiData(string $tenantId, array $data): void
    {
        $key = "kpi:tenant:{$tenantId}:" . now()->format('Y-m-d-H');
        
        Cache::tags(['kpi', 'tenant:' . $tenantId])
            ->put($key, $data, $this->cacheConfig['kpi_data']['ttl']);
    }
    
    /**
     * Get cached KPI data
     */
    public function getCachedKpiData(string $tenantId): ?array
    {
        $key = "kpi:tenant:{$tenantId}:" . now()->format('Y-m-d-H');
        
        return Cache::tags(['kpi', 'tenant:' . $tenantId])->get($key);
    }
    
    /**
     * Cache user data
     */
    public function cacheUserData(string $userId, array $data): void
    {
        $key = "user:{$userId}";
        
        Cache::tags(['users', 'profiles'])
            ->put($key, $data, $this->cacheConfig['user_data']['ttl']);
    }
    
    /**
     * Get cached user data
     */
    public function getCachedUserData(string $userId): ?array
    {
        $key = "user:{$userId}";
        
        return Cache::tags(['users', 'profiles'])->get($key);
    }
    
    /**
     * Cache project list with filters
     */
    public function cacheProjectList(string $tenantId, array $filters, array $data): void
    {
        $filterHash = md5(serialize($filters));
        $key = "projects:tenant:{$tenantId}:filters:{$filterHash}";
        
        Cache::tags(['projects', 'tenant:' . $tenantId])
            ->put($key, $data, $this->cacheConfig['project_data']['ttl']);
    }
    
    /**
     * Get cached project list
     */
    public function getCachedProjectList(string $tenantId, array $filters): ?array
    {
        $filterHash = md5(serialize($filters));
        $key = "projects:tenant:{$tenantId}:filters:{$filterHash}";
        
        return Cache::tags(['projects', 'tenant:' . $tenantId])->get($key);
    }
    
    /**
     * Cache task list with filters
     */
    public function cacheTaskList(string $tenantId, array $filters, array $data): void
    {
        $filterHash = md5(serialize($filters));
        $key = "tasks:tenant:{$tenantId}:filters:{$filterHash}";
        
        Cache::tags(['tasks', 'tenant:' . $tenantId])
            ->put($key, $data, $this->cacheConfig['task_data']['ttl']);
    }
    
    /**
     * Get cached task list
     */
    public function getCachedTaskList(string $tenantId, array $filters): ?array
    {
        $filterHash = md5(serialize($filters));
        $key = "tasks:tenant:{$tenantId}:filters:{$filterHash}";
        
        return Cache::tags(['tasks', 'tenant:' . $tenantId])->get($key);
    }
    
    /**
     * Cache API response
     */
    public function cacheApiResponse(string $endpoint, array $params, array $data): void
    {
        $paramHash = md5(serialize($params));
        $key = "api:{$endpoint}:{$paramHash}";
        
        Cache::tags(['api', 'responses'])
            ->put($key, $data, $this->cacheConfig['api_responses']['ttl']);
    }
    
    /**
     * Get cached API response
     */
    public function getCachedApiResponse(string $endpoint, array $params): ?array
    {
        $paramHash = md5(serialize($params));
        $key = "api:{$endpoint}:{$paramHash}";
        
        return Cache::tags(['api', 'responses'])->get($key);
    }
    
    /**
     * Clear cache by tags
     */
    public function clearCacheByTags(array $tags): void
    {
        Cache::tags($tags)->flush();
    }
    
    /**
     * Clear tenant-specific cache
     */
    public function clearTenantCache(string $tenantId): void
    {
        Cache::tags(['tenant:' . $tenantId])->flush();
    }
    
    /**
     * Clear all cache
     */
    public function clearAllCache(): void
    {
        Cache::flush();
    }
    
    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        $stats = [
            'driver' => config('cache.default'),
            'memory_usage' => $this->getMemoryUsage(),
            'hit_rate' => $this->getHitRate(),
            'keys_count' => $this->getKeysCount(),
        ];
        
        if (config('cache.default') === 'redis') {
            $stats['redis_info'] = $this->getRedisInfo();
        }
        
        return $stats;
    }
    
    /**
     * Get memory usage
     */
    private function getMemoryUsage(): array
    {
        $memory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        return [
            'current' => $this->formatBytes($memory),
            'peak' => $this->formatBytes($peakMemory),
            'current_bytes' => $memory,
            'peak_bytes' => $peakMemory,
        ];
    }
    
    /**
     * Get cache hit rate (Redis only)
     */
    private function getHitRate(): ?float
    {
        if (config('cache.default') !== 'redis') {
            return null;
        }
        
        try {
            $info = Redis::info();
            $hits = $info['keyspace_hits'] ?? 0;
            $misses = $info['keyspace_misses'] ?? 0;
            $total = $hits + $misses;
            
            return $total > 0 ? round(($hits / $total) * 100, 2) : 0;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get keys count (Redis only)
     */
    private function getKeysCount(): ?int
    {
        if (config('cache.default') !== 'redis') {
            return null;
        }
        
        try {
            return Redis::dbsize();
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Get Redis info
     */
    private function getRedisInfo(): ?array
    {
        if (config('cache.default') !== 'redis') {
            return null;
        }
        
        try {
            $info = Redis::info();
            
            return [
                'version' => $info['redis_version'] ?? 'unknown',
                'uptime' => $info['uptime_in_seconds'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'used_memory' => $info['used_memory_human'] ?? 'unknown',
                'used_memory_peak' => $info['used_memory_peak_human'] ?? 'unknown',
            ];
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Warm up cache with frequently accessed data
     */
    public function warmUpCache(string $tenantId): void
    {
        // Cache KPI data
        $kpiData = $this->generateKpiData($tenantId);
        $this->cacheKpiData($tenantId, $kpiData);
        
        // Cache project list
        $projectList = $this->generateProjectList($tenantId);
        $this->cacheProjectList($tenantId, [], $projectList);
        
        // Cache task list
        $taskList = $this->generateTaskList($tenantId);
        $this->cacheTaskList($tenantId, [], $taskList);
    }
    
    /**
     * Generate KPI data for caching
     */
    private function generateKpiData(string $tenantId): array
    {
        // This would typically fetch from database
        return [
            'total_projects' => 25,
            'active_projects' => 18,
            'completed_projects' => 7,
            'total_tasks' => 156,
            'completed_tasks' => 89,
            'overdue_tasks' => 12,
            'team_members' => 8,
            'budget_utilization' => 75.5,
        ];
    }
    
    /**
     * Generate project list for caching
     */
    private function generateProjectList(string $tenantId): array
    {
        // This would typically fetch from database
        return [
            ['id' => '1', 'name' => 'Project Alpha', 'status' => 'active'],
            ['id' => '2', 'name' => 'Project Beta', 'status' => 'planning'],
            ['id' => '3', 'name' => 'Project Gamma', 'status' => 'completed'],
        ];
    }
    
    /**
     * Generate task list for caching
     */
    private function generateTaskList(string $tenantId): array
    {
        // This would typically fetch from database
        return [
            ['id' => '1', 'title' => 'Task 1', 'status' => 'pending'],
            ['id' => '2', 'title' => 'Task 2', 'status' => 'in_progress'],
            ['id' => '3', 'title' => 'Task 3', 'status' => 'completed'],
        ];
    }
}
