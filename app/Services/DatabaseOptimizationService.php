<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DatabaseOptimizationService
{
    /**
     * Optimize Eloquent queries with eager loading and column selection
     */
    public function optimizeQuery(Builder $query, array $options = []): Builder
    {
        // Apply eager loading if specified
        if (isset($options['with'])) {
            $query->with($options['with']);
        }
        
        // Apply column selection if specified
        if (isset($options['select'])) {
            $query->select($options['select']);
        }
        
        // Apply pagination if specified
        if (isset($options['paginate'])) {
            $query->limit($options['paginate']);
        }
        
        // Apply caching if specified
        if (isset($options['cache'])) {
            $cacheKey = $this->generateCacheKey($query, $options['cache']);
            $cacheTtl = $options['cache']['ttl'] ?? 300; // 5 minutes default
            
            return Cache::remember($cacheKey, $cacheTtl, function () use ($query) {
                return $query->get();
            });
        }

        return $query;
    }

    /**
     * Optimize project queries with common optimizations
     */
    public function optimizeProjectQuery(array $filters = []): Builder
    {
        $query = \App\Models\Project::query()
            ->select([
                'id',
                'name',
                'description',
                'status',
                'priority',
                'budget',
                'start_date',
                'end_date',
                'created_at',
                'updated_at',
            ])
            ->with([
                'tasks' => function ($query) {
                    $query->select(['id', 'project_id', 'title', 'status', 'due_date'])
                          ->where('status', '!=', 'completed')
                          ->limit(5);
                },
                'team' => function ($query) {
                    $query->select(['id', 'name', 'email', 'role']);
                },
        ]);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }
        
        if (isset($filters['date_range'])) {
            $query->whereBetween('created_at', $filters['date_range']);
        }

        return $query;
    }

    /**
     * Optimize task queries with common optimizations
     */
    public function optimizeTaskQuery(array $filters = []): Builder
    {
        $query = \App\Models\Task::query()
            ->select([
                'id',
                'title',
                'description',
                'status',
                'priority',
                'due_date',
                'project_id',
                'assigned_to',
                'created_at',
                'updated_at',
            ])
            ->with([
                'project' => function ($query) {
                    $query->select(['id', 'name', 'status']);
                },
                'assignee' => function ($query) {
                    $query->select(['id', 'name', 'email']);
                },
        ]);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        
        if (isset($filters['assigned_to'])) {
            $query->where('assigned_to', $filters['assigned_to']);
        }

        return $query;
    }

    /**
     * Optimize user queries with common optimizations
     */
    public function optimizeUserQuery(array $filters = []): Builder
    {
        $query = \App\Models\User::query()
            ->select([
                'id',
                'name',
                'email',
                'role',
                'status',
                'last_login_at',
                'created_at',
            ])
            ->withCount([
                'projects',
                'tasks',
                'assignedTasks',
        ]);

        // Apply filters
        if (isset($filters['role'])) {
            $query->where('role', $filters['role']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query;
    }

    /**
     * Generate cache key for query
     */
    private function generateCacheKey(Builder $query, array $cacheOptions): string
    {
        $sql = $query->toSql();
        $bindings = $query->getBindings();
        $tenantId = app('tenant')?->id ?? 'global';
        
        $key = md5($sql . serialize($bindings) . $tenantId);
        
        return 'query_cache:' . ($cacheOptions['prefix'] ?? 'default') . ':' . $key;
    }
    
    /**
     * Clear query cache
     */
    public function clearQueryCache(string $pattern = '*'): void
    {
        Cache::forget($pattern);
    }
    
    /**
     * Get query performance statistics
     */
    public function getQueryStats(): array
    {
        $queries = DB::getQueryLog();
        
        $stats = [
            'total_queries' => count($queries),
            'total_time' => array_sum(array_column($queries, 'time')),
            'avg_time' => count($queries) > 0 ? array_sum(array_column($queries, 'time')) / count($queries) : 0,
            'slow_queries' => array_filter($queries, fn($q) => $q['time'] > 100),
            'duplicate_queries' => $this->findDuplicateQueries($queries),
        ];
        
        return $stats;
    }
    
    /**
     * Find duplicate queries
     */
    private function findDuplicateQueries(array $queries): array
    {
        $queryCounts = [];
        
        foreach ($queries as $query) {
            $hash = md5($query['query']);
            if (!isset($queryCounts[$hash])) {
                $queryCounts[$hash] = [
                    'query' => $query['query'],
                    'count' => 0,
                    'total_time' => 0,
                ];
            }
            $queryCounts[$hash]['count']++;
            $queryCounts[$hash]['total_time'] += $query['time'];
        }
        
        return array_filter($queryCounts, fn($q) => $q['count'] > 1);
    }
    
    /**
     * Optimize N+1 queries by suggesting eager loading
     */
    public function suggestEagerLoading(string $model, array $queries): array
    {
        $suggestions = [];
        
        // Analyze queries to find potential N+1 patterns
        $queryPatterns = [];
        foreach ($queries as $query) {
            if (str_contains($query['query'], $model)) {
                $queryPatterns[] = $query['query'];
            }
        }
        
        // Look for foreign key patterns
        $foreignKeys = $this->extractForeignKeys($queryPatterns);
        
        foreach ($foreignKeys as $foreignKey) {
            $suggestions[] = [
                'type' => 'eager_loading',
                'suggestion' => "Add ->with('{$foreignKey}') to avoid N+1 queries",
                'impact' => 'high',
            ];
        }
        
        return $suggestions;
    }
    
    /**
     * Extract foreign keys from query patterns
     */
    private function extractForeignKeys(array $queryPatterns): array
    {
        $foreignKeys = [];
        
        foreach ($queryPatterns as $pattern) {
            // Look for WHERE clauses with foreign key patterns
            if (preg_match('/WHERE\s+(\w+_id)\s*=/', $pattern, $matches)) {
                $foreignKeys[] = str_replace('_id', '', $matches[1]);
            }
        }
        
        return array_unique($foreignKeys);
    }

    /**
     * Get database performance metrics for production
     */
    public function getProductionMetrics(): array
    {
        $metrics = [];

        // Get query execution time
        $startTime = microtime(true);
        DB::select('SELECT 1');
        $metrics['connection_time'] = (microtime(true) - $startTime) * 1000; // ms

        // Get table sizes
        $tables = ['projects', 'tasks', 'users', 'calendar_events', 'templates'];
        foreach ($tables as $table) {
            try {
                $result = DB::select("SELECT COUNT(*) as count FROM {$table}");
                $metrics['table_counts'][$table] = $result[0]->count ?? 0;
            } catch (\Exception $e) {
                $metrics['table_counts'][$table] = 0;
            }
        }

        return $metrics;
    }

    /**
     * Create recommended indexes for production
     */
    public function getRecommendedIndexes(): array
    {
        $indexes = [];

        // Multi-tenant indexes
        $tables = ['projects', 'tasks', 'users', 'calendar_events', 'templates'];
        foreach ($tables as $table) {
            $indexes[] = "CREATE INDEX idx_{$table}_tenant_id ON {$table} (tenant_id)";
        }

        // Composite indexes for common queries
        $indexes[] = "CREATE INDEX idx_projects_tenant_status ON projects (tenant_id, status)";
        $indexes[] = "CREATE INDEX idx_tasks_tenant_project ON tasks (tenant_id, project_id)";
        $indexes[] = "CREATE INDEX idx_tasks_tenant_assignee ON tasks (tenant_id, assigned_to)";
        $indexes[] = "CREATE INDEX idx_calendar_events_tenant_date ON calendar_events (tenant_id, start_date)";

        return $indexes;
    }

    /**
     * Analyze query performance
     */
    public function analyzeQueryPerformance(string $query): array
    {
        $startTime = microtime(true);
        $result = DB::select($query);
        $executionTime = (microtime(true) - $startTime) * 1000; // ms

        return [
            'execution_time' => $executionTime,
            'result_count' => count($result),
            'is_slow' => $executionTime > 100, // 100ms threshold
        ];
    }

    /**
     * Get production database configuration recommendations
     */
    public function getProductionConfig(): array
    {
        return [
            'optimizations' => [
                'query_cache' => true,
                'connection_pooling' => true,
                'prepared_statements' => true,
                'binary_logging' => false, // Disable for performance
            ],
            'indexes' => $this->getRecommendedIndexes(),
            'settings' => [
                'innodb_buffer_pool_size' => '70% of RAM',
                'query_cache_size' => '256M',
                'max_connections' => 200,
                'slow_query_log' => true,
                'long_query_time' => 1,
            ],
        ];
    }
}