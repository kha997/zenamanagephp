<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Query Optimization Service
 * 
 * Provides utilities for preventing N+1 queries and optimizing database performance
 */
class QueryOptimizationService
{
    /**
     * Common eager loading patterns for different models
     */
    public const EAGER_LOADING_PATTERNS = [
        'project' => ['owner:id,name,email', 'tasks:id,project_id,title,status,assignee_id,end_date'],
        'project_with_tasks' => [
            'owner:id,name,email',
            'tasks:id,project_id,title,status,assignee_id,end_date',
            'tasks.assignee:id,name,email'
        ],
        'task' => ['project:id,name', 'assignee:id,name,email', 'creator:id,name,email'],
        'client' => ['quotes:id,client_id,title,status,total_amount', 'projects:id,client_id,name,status'],
        'client_with_quotes' => [
            'quotes:id,client_id,title,status,total_amount,final_amount',
            'projects:id,client_id,name,status'
        ],
        'quote' => ['client:id,name,email,company', 'project:id,name'],
        'user' => ['tenant:id,name'],
    ];

    /**
     * Apply eager loading to a query based on model type
     */
    public static function eagerLoad(Builder $query, string $modelType, array $additionalRelations = []): Builder
    {
        $patterns = self::EAGER_LOADING_PATTERNS[$modelType] ?? [];
        $relations = array_merge($patterns, $additionalRelations);
        
        if (!empty($relations)) {
            $query->with($relations);
        }
        
        return $query;
    }

    /**
     * Get aggregated statistics with a single query
     */
    public static function getAggregatedStats(Builder $query, array $conditions): array
    {
        $selectRaw = [];
        
        foreach ($conditions as $condition) {
            if (is_string($condition)) {
                $selectRaw[] = $condition;
            } elseif (is_array($condition) && isset($condition['field'], $condition['value'])) {
                $selectRaw[] = "SUM(CASE WHEN {$condition['field']} = '{$condition['value']}' THEN 1 ELSE 0 END) as {$condition['alias']}";
            }
        }
        
        if (empty($selectRaw)) {
            return [];
        }
        
        return $query->selectRaw(implode(', ', $selectRaw))->first()->toArray();
    }

    /**
     * Cache query results with automatic cache key generation
     */
    public static function cacheQuery(string $cacheKey, callable $queryCallback, int $ttl = 300): mixed
    {
        return Cache::remember($cacheKey, $ttl, $queryCallback);
    }

    /**
     * Generate cache key for tenant-specific data
     */
    public static function generateTenantCacheKey(string $prefix, string $tenantId, array $params = []): string
    {
        $key = "{$prefix}-{$tenantId}";
        
        if (!empty($params)) {
            $key .= '-' . md5(serialize($params));
        }
        
        return $key;
    }

    /**
     * Clear cache for a specific tenant
     */
    public static function clearTenantCache(string $tenantId, array $prefixes = []): void
    {
        if (empty($prefixes)) {
            // Clear all tenant-related cache by flushing and rebuilding
            // Note: This is a simplified approach. In production, you might want to use
            // a more sophisticated cache tagging system
            Cache::flush();
        } else {
            // Clear specific prefixes
            foreach ($prefixes as $prefix) {
                Cache::forget("{$prefix}-{$tenantId}");
            }
        }
    }

    /**
     * Optimize pagination queries
     */
    public static function optimizePagination(Builder $query, int $perPage = 15, array $eagerLoad = []): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        if (!empty($eagerLoad)) {
            $query->with($eagerLoad);
        }
        
        return $query->paginate($perPage);
    }

    /**
     * Get count statistics for different statuses
     */
    public static function getStatusCounts(Builder $query, string $statusField, array $statuses): array
    {
        $selectRaw = [];
        
        foreach ($statuses as $status) {
            $selectRaw[] = "SUM(CASE WHEN {$statusField} = '{$status}' THEN 1 ELSE 0 END) as {$status}_count";
        }
        
        $selectRaw[] = "COUNT(*) as total_count";
        
        return $query->selectRaw(implode(', ', $selectRaw))->first()->toArray();
    }

    /**
     * Optimize dashboard KPI queries
     */
    public static function getDashboardKpis(string $tenantId, array $kpiConfigs): array
    {
        $kpis = [];
        
        foreach ($kpiConfigs as $config) {
            $cacheKey = self::generateTenantCacheKey($config['cache_key'], $tenantId);
            
            $kpis[] = self::cacheQuery($cacheKey, function () use ($config) {
                $query = $config['model']::where('tenant_id', $config['tenant_id']);
                
                if (isset($config['conditions'])) {
                    foreach ($config['conditions'] as $condition) {
                        $query->where($condition['field'], $condition['operator'] ?? '=', $condition['value']);
                    }
                }
                
                $result = $query->selectRaw($config['select_raw'])->first();
                
                return [
                    'label' => $config['label'],
                    'value' => $result->{$config['value_field']} ?? 0,
                    'subtitle' => $config['subtitle'],
                    'icon' => $config['icon'],
                    'gradient' => $config['gradient'],
                    'action' => $config['action'],
                ];
            }, $config['ttl'] ?? 300);
        }
        
        return $kpis;
    }

    /**
     * Prevent N+1 queries in collection operations
     */
    public static function optimizeCollection($collection, array $relations): void
    {
        if ($collection->isNotEmpty()) {
            $collection->load($relations);
        }
    }

    /**
     * Log slow queries for monitoring
     */
    public static function logSlowQuery(string $query, float $executionTime, array $context = []): void
    {
        if ($executionTime > 1.0) { // Log queries taking more than 1 second
            Log::warning('Slow query detected', [
                'query' => $query,
                'execution_time' => $executionTime,
                'context' => $context,
            ]);
        }
    }

    /**
     * Get optimized project statistics
     */
    public static function getProjectStats(string $tenantId): array
    {
        $cacheKey = self::generateTenantCacheKey('project-stats', $tenantId);
        
        return self::cacheQuery($cacheKey, function () use ($tenantId) {
            return \App\Models\Project::where('tenant_id', $tenantId)
                ->selectRaw('
                    COUNT(*) as total_projects,
                    SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_projects,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_projects,
                    SUM(CASE WHEN status = "archived" THEN 1 ELSE 0 END) as archived_projects,
                    SUM(CASE WHEN status = "on_hold" THEN 1 ELSE 0 END) as on_hold_projects,
                    AVG(CASE WHEN status = "active" THEN progress_pct ELSE NULL END) as avg_progress,
                    SUM(budget_total) as total_budget,
                    SUM(budget_actual) as actual_budget
                ')
                ->first()
                ->toArray();
        }, 300);
    }

    /**
     * Get optimized task statistics
     */
    public static function getTaskStats(string $tenantId): array
    {
        $cacheKey = self::generateTenantCacheKey('task-stats', $tenantId);
        
        return self::cacheQuery($cacheKey, function () use ($tenantId) {
            return \App\Models\Task::where('tenant_id', $tenantId)
                ->selectRaw('
                    COUNT(*) as total_tasks,
                    SUM(CASE WHEN status = "pending" THEN 1 ELSE 0 END) as pending_tasks,
                    SUM(CASE WHEN status = "in_progress" THEN 1 ELSE 0 END) as in_progress_tasks,
                    SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_tasks,
                    SUM(CASE WHEN status = "cancelled" THEN 1 ELSE 0 END) as cancelled_tasks,
                    SUM(CASE WHEN end_date < NOW() AND status NOT IN ("completed", "cancelled") THEN 1 ELSE 0 END) as overdue_tasks,
                    AVG(estimated_hours) as avg_estimated_hours,
                    SUM(actual_hours) as total_actual_hours
                ')
                ->first()
                ->toArray();
        }, 300);
    }

    /**
     * Get optimized client statistics
     */
    public static function getClientStats(string $tenantId): array
    {
        $cacheKey = self::generateTenantCacheKey('client-stats', $tenantId);
        
        return self::cacheQuery($cacheKey, function () use ($tenantId) {
            $clientStats = \App\Models\Client::where('tenant_id', $tenantId)
                ->selectRaw('
                    COUNT(*) as total_clients,
                    SUM(CASE WHEN lifecycle_stage = "customer" THEN 1 ELSE 0 END) as customers,
                    SUM(CASE WHEN lifecycle_stage = "prospect" THEN 1 ELSE 0 END) as prospects,
                    SUM(CASE WHEN lifecycle_stage = "lead" THEN 1 ELSE 0 END) as leads,
                    SUM(CASE WHEN lifecycle_stage = "inactive" THEN 1 ELSE 0 END) as inactive_clients
                ')
                ->first();

            $quoteStats = \App\Models\Quote::where('tenant_id', $tenantId)
                ->selectRaw('
                    COUNT(*) as total_quotes,
                    SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) as accepted_quotes,
                    SUM(CASE WHEN status = "accepted" THEN final_amount ELSE 0 END) as total_value
                ')
                ->first();

            return array_merge($clientStats->toArray(), $quoteStats->toArray());
        }, 300);
    }
}