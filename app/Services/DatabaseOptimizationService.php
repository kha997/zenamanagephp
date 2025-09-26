<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Database Query Optimization Service
 * 
 * Provides methods to optimize database queries for better performance
 */
class DatabaseOptimizationService
{
    /**
     * Optimize task queries with proper eager loading
     */
    public function optimizeTaskQuery(Builder $query, array $filters = []): Builder
    {
        // Eager load relationships to avoid N+1 queries
        $query->with([
            'project:id,name,status,client_id',
            'assignee:id,name,email',
            'creator:id,name,email',
            'component:id,name,type',
            'assignments.user:id,name,email'
        ]);

        // Apply filters with proper indexing
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['assignee_id'])) {
            $query->where('assignee_id', $filters['assignee_id']);
        }

        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        // Optimize date range queries
        if (isset($filters['start_date_from'])) {
            $query->where('start_date', '>=', $filters['start_date_from']);
        }

        if (isset($filters['start_date_to'])) {
            $query->where('start_date', '<=', $filters['start_date_to']);
        }

        // Optimize search queries
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Add proper ordering
        $query->orderBy('created_at', 'desc');

        return $query;
    }

    /**
     * Optimize project queries with proper eager loading
     */
    public function optimizeProjectQuery(Builder $query, array $filters = []): Builder
    {
        // Eager load relationships
        $query->with([
            'client:id,name,email',
            'pm:id,name,email',
            'tenant:id,name',
            'tasks:id,project_id,name,status,priority',
            'documents:id,project_id,name,type,status'
        ]);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['client_id'])) {
            $query->where('client_id', $filters['client_id']);
        }

        if (isset($filters['pm_id'])) {
            $query->where('pm_id', $filters['pm_id']);
        }

        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        // Optimize date range queries
        if (isset($filters['start_date_from'])) {
            $query->where('start_date', '>=', $filters['start_date_from']);
        }

        if (isset($filters['start_date_to'])) {
            $query->where('start_date', '<=', $filters['start_date_to']);
        }

        // Optimize search queries
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%")
                  ->orWhere('code', 'LIKE', "%{$search}%");
            });
        }

        // Add proper ordering
        $query->orderBy('created_at', 'desc');

        return $query;
    }

    /**
     * Optimize user queries with proper eager loading
     */
    public function optimizeUserQuery(Builder $query, array $filters = []): Builder
    {
        // Eager load relationships
        $query->with([
            'tenant:id,name',
            'organization:id,name'
        ]);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (isset($filters['organization_id'])) {
            $query->where('organization_id', $filters['organization_id']);
        }

        // Optimize search queries
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Add proper ordering
        $query->orderBy('created_at', 'desc');

        return $query;
    }

    /**
     * Optimize document queries with proper eager loading
     */
    public function optimizeDocumentQuery(Builder $query, array $filters = []): Builder
    {
        // Eager load relationships
        $query->with([
            'project:id,name,status',
            'task:id,name,status',
            'component:id,name,type',
            'uploadedBy:id,name,email',
            'tenant:id,name'
        ]);

        // Apply filters
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        // Optimize search queries
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('description', 'LIKE', "%{$search}%");
            });
        }

        // Add proper ordering
        $query->orderBy('created_at', 'desc');

        return $query;
    }

    /**
     * Execute optimized query with performance monitoring
     */
    public function executeOptimizedQuery(Builder $query, string $queryName = 'optimized_query'): mixed
    {
        $startTime = microtime(true);
        
        try {
            $result = $query->get();
            
            $executionTime = microtime(true) - $startTime;
            
            // Log slow queries
            if ($executionTime > 1.0) {
                Log::warning("Slow query detected: {$queryName}", [
                    'execution_time' => $executionTime,
                    'query' => $query->toSql(),
                    'bindings' => $query->getBindings()
                ]);
            }
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error("Query execution failed: {$queryName}", [
                'error' => $e->getMessage(),
                'query' => $query->toSql(),
                'bindings' => $query->getBindings()
            ]);
            
            throw $e;
        }
    }

    /**
     * Get query execution plan for analysis
     */
    public function getQueryExecutionPlan(Builder $query): array
    {
        try {
            $sql = $query->toSql();
            $bindings = $query->getBindings();
            
            // Replace bindings in SQL for analysis
            $fullSql = $sql;
            foreach ($bindings as $binding) {
                $fullSql = preg_replace('/\?/', "'{$binding}'", $fullSql, 1);
            }
            
            $explainResult = DB::select("EXPLAIN {$fullSql}");
            
            return [
                'sql' => $fullSql,
                'execution_plan' => $explainResult,
                'bindings' => $bindings
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get query execution plan', [
                'error' => $e->getMessage(),
                'query' => $query->toSql()
            ]);
            
            return [];
        }
    }

    /**
     * Optimize pagination queries
     */
    public function optimizePaginationQuery(Builder $query, int $perPage = 15): Builder
    {
        // Use cursor pagination for large datasets
        if ($perPage > 50) {
            // Add proper ordering for cursor pagination
            $query->orderBy('id', 'asc');
        }
        
        return $query;
    }

    /**
     * Cache frequently accessed data
     */
    public function cacheFrequentData(string $key, callable $callback, int $ttl = 3600): mixed
    {
        return cache()->remember($key, $ttl, $callback);
    }
}