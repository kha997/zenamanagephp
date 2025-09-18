<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * PerformanceOptimizationService - Service cho performance optimization
 */
class PerformanceOptimizationService
{
    private array $cacheConfig;
    private array $queryConfig;

    public function __construct()
    {
        $this->cacheConfig = [
            'default_ttl' => 3600, // 1 hour
            'short_ttl' => 300,    // 5 minutes
            'long_ttl' => 86400,   // 24 hours
            'prefix' => 'zena_',
            'tags' => [
                'projects' => 'zena_projects',
                'users' => 'zena_users',
                'tasks' => 'zena_tasks',
                'analytics' => 'zena_analytics',
                'integrations' => 'zena_integrations'
            ]
        ];

        $this->queryConfig = [
            'max_queries_per_request' => 100,
            'slow_query_threshold' => 1000, // milliseconds
            'enable_query_logging' => config('app.debug', false),
            'enable_query_caching' => true,
            'cache_query_results' => true
        ];
    }

    /**
     * Cache Management
     */
    public function cache(string $key, $value, int $ttl = null, array $tags = []): void
    {
        $ttl = $ttl ?? $this->cacheConfig['default_ttl'];
        $prefixedKey = $this->cacheConfig['prefix'] . $key;

        if (!empty($tags)) {
            Cache::tags($tags)->put($prefixedKey, $value, $ttl);
        } else {
            Cache::put($prefixedKey, $value, $ttl);
        }
    }

    public function getFromCache(string $key, array $tags = [])
    {
        $prefixedKey = $this->cacheConfig['prefix'] . $key;

        if (!empty($tags)) {
            return Cache::tags($tags)->get($prefixedKey);
        }

        return Cache::get($prefixedKey);
    }

    public function forgetCache(string $key, array $tags = []): void
    {
        $prefixedKey = $this->cacheConfig['prefix'] . $key;

        if (!empty($tags)) {
            Cache::tags($tags)->forget($prefixedKey);
        } else {
            Cache::forget($prefixedKey);
        }
    }

    public function flushCache(array $tags = []): void
    {
        if (!empty($tags)) {
            Cache::tags($tags)->flush();
        } else {
            Cache::flush();
        }
    }

    /**
     * Query Optimization
     */
    public function optimizeQuery(Builder $query, array $options = []): Builder
    {
        $options = array_merge([
            'select' => null,
            'with' => [],
            'limit' => null,
            'order_by' => null,
            'cache' => true,
            'cache_ttl' => $this->cacheConfig['default_ttl']
        ], $options);

        // Apply select optimization
        if ($options['select']) {
            $query->select($options['select']);
        }

        // Apply eager loading
        if (!empty($options['with'])) {
            $query->with($options['with']);
        }

        // Apply limit
        if ($options['limit']) {
            $query->limit($options['limit']);
        }

        // Apply ordering
        if ($options['order_by']) {
            $query->orderBy($options['order_by']);
        }

        return $query;
    }

    /**
     * Database Query Caching
     */
    public function cacheQuery(string $key, callable $callback, int $ttl = null, array $tags = []): mixed
    {
        $ttl = $ttl ?? $this->cacheConfig['default_ttl'];
        
        return Cache::remember(
            $this->cacheConfig['prefix'] . $key,
            $ttl,
            function () use ($callback) {
                $startTime = microtime(true);
                $result = $callback();
                $executionTime = (microtime(true) - $startTime) * 1000;

                // Log slow queries
                if ($executionTime > $this->queryConfig['slow_query_threshold']) {
                    Log::warning('Slow query detected', [
                        'execution_time' => $executionTime,
                        'threshold' => $this->queryConfig['slow_query_threshold']
                    ]);
                }

                return $result;
            }
        );
    }

    /**
     * Model Caching
     */
    public function cacheModel(string $modelClass, $id, int $ttl = null): ?Model
    {
        $ttl = $ttl ?? $this->cacheConfig['default_ttl'];
        $key = strtolower(class_basename($modelClass)) . '_' . $id;
        
        return $this->cacheQuery($key, function () use ($modelClass, $id) {
            return $modelClass::find($id);
        }, $ttl, [$this->getModelTag($modelClass)]);
    }

    public function cacheModelCollection(string $modelClass, array $ids, int $ttl = null): Collection
    {
        $ttl = $ttl ?? $this->cacheConfig['default_ttl'];
        $key = strtolower(class_basename($modelClass)) . '_collection_' . md5(implode(',', $ids));
        
        return $this->cacheQuery($key, function () use ($modelClass, $ids) {
            return $modelClass::whereIn('id', $ids)->get();
        }, $ttl, [$this->getModelTag($modelClass)]);
    }

    /**
     * Relationship Caching
     */
    public function cacheRelationships(Model $model, array $relationships, int $ttl = null): Model
    {
        $ttl = $ttl ?? $this->cacheConfig['short_ttl'];
        
        foreach ($relationships as $relationship) {
            $key = strtolower(class_basename($model)) . '_' . $model->id . '_' . $relationship;
            
            $this->cacheQuery($key, function () use ($model, $relationship) {
                return $model->load($relationship);
            }, $ttl, [$this->getModelTag(get_class($model))]);
        }

        return $model;
    }

    /**
     * Pagination Caching
     */
    public function cachePaginatedResults(string $key, callable $callback, int $page, int $perPage, int $ttl = null): array
    {
        $ttl = $ttl ?? $this->cacheConfig['default_ttl'];
        $cacheKey = $key . '_page_' . $page . '_per_' . $perPage;
        
        return $this->cacheQuery($cacheKey, $callback, $ttl);
    }

    /**
     * Search Results Caching
     */
    public function cacheSearchResults(string $query, array $filters, callable $callback, int $ttl = null): array
    {
        $ttl = $ttl ?? $this->cacheConfig['short_ttl'];
        $key = 'search_' . md5($query . serialize($filters));
        
        return $this->cacheQuery($key, $callback, $ttl, ['search']);
    }

    /**
     * Analytics Caching
     */
    public function cacheAnalytics(string $type, array $params, callable $callback, int $ttl = null): array
    {
        $ttl = $ttl ?? $this->cacheConfig['long_ttl'];
        $key = 'analytics_' . $type . '_' . md5(serialize($params));
        
        return $this->cacheQuery($key, $callback, $ttl, ['analytics']);
    }

    /**
     * Integration Caching
     */
    public function cacheIntegrationData(string $provider, string $endpoint, array $params, callable $callback, int $ttl = null): array
    {
        $ttl = $ttl ?? $this->cacheConfig['default_ttl'];
        $key = 'integration_' . $provider . '_' . $endpoint . '_' . md5(serialize($params));
        
        return $this->cacheQuery($key, $callback, $ttl, ['integrations']);
    }

    /**
     * Cache Invalidation
     */
    public function invalidateModelCache(string $modelClass, $id = null): void
    {
        $tag = $this->getModelTag($modelClass);
        
        if ($id) {
            $key = strtolower(class_basename($modelClass)) . '_' . $id;
            $this->forgetCache($key, [$tag]);
        } else {
            $this->flushCache([$tag]);
        }
    }

    public function invalidateSearchCache(): void
    {
        $this->flushCache(['search']);
    }

    public function invalidateAnalyticsCache(): void
    {
        $this->flushCache(['analytics']);
    }

    /**
     * Database Optimization
     */
    public function optimizeDatabase(): array
    {
        $results = [];

        try {
            // Analyze tables
            $tables = DB::select('SHOW TABLES');
            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];
                DB::statement("ANALYZE TABLE {$tableName}");
            }
            $results['analyze'] = 'Tables analyzed successfully';

            // Optimize tables
            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];
                DB::statement("OPTIMIZE TABLE {$tableName}");
            }
            $results['optimize'] = 'Tables optimized successfully';

        } catch (\Exception $e) {
            Log::error('Database optimization failed', [
                'error' => $e->getMessage()
            ]);
            $results['error'] = $e->getMessage();
        }

        return $results;
    }

    /**
     * Query Performance Analysis
     */
    public function analyzeQueryPerformance(string $query, array $bindings = []): array
    {
        $startTime = microtime(true);
        
        try {
            $result = DB::select($query, $bindings);
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'success' => true,
                'execution_time' => $executionTime,
                'result_count' => count($result),
                'is_slow' => $executionTime > $this->queryConfig['slow_query_threshold']
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'execution_time' => (microtime(true) - $startTime) * 1000
            ];
        }
    }

    /**
     * Memory Usage Optimization
     */
    public function optimizeMemoryUsage(): array
    {
        $results = [];

        // Clear unused variables
        $results['memory_before'] = memory_get_usage(true);
        $results['peak_memory_before'] = memory_get_peak_usage(true);

        // Force garbage collection
        gc_collect_cycles();
        
        $results['memory_after'] = memory_get_usage(true);
        $results['peak_memory_after'] = memory_get_peak_usage(true);
        $results['memory_freed'] = $results['memory_before'] - $results['memory_after'];

        return $results;
    }

    /**
     * Cache Statistics
     */
    public function getCacheStatistics(): array
    {
        $stats = [];

        try {
            // Redis statistics
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                $info = $redis->info();
                
                $stats['redis'] = [
                    'used_memory' => $info['used_memory'] ?? 0,
                    'used_memory_human' => $info['used_memory_human'] ?? '0B',
                    'connected_clients' => $info['connected_clients'] ?? 0,
                    'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                    'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                    'keyspace_misses' => $info['keyspace_misses'] ?? 0
                ];
            }

            // Cache hit rate
            $stats['hit_rate'] = $this->calculateCacheHitRate();
            
            // Cache size
            $stats['cache_size'] = $this->getCacheSize();

        } catch (\Exception $e) {
            Log::error('Failed to get cache statistics', [
                'error' => $e->getMessage()
            ]);
            $stats['error'] = $e->getMessage();
        }

        return $stats;
    }

    /**
     * Performance Monitoring
     */
    public function monitorPerformance(callable $callback, array $context = []): array
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        try {
            $result = $callback();
            $success = true;
            $error = null;
        } catch (\Exception $e) {
            $result = null;
            $success = false;
            $error = $e->getMessage();
        }
        
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $metrics = [
            'success' => $success,
            'execution_time' => ($endTime - $startTime) * 1000,
            'memory_used' => $endMemory - $startMemory,
            'peak_memory' => memory_get_peak_usage(true),
            'context' => $context,
            'timestamp' => now()->toISOString()
        ];
        
        if ($error) {
            $metrics['error'] = $error;
        }
        
        // Log performance metrics
        if ($metrics['execution_time'] > $this->queryConfig['slow_query_threshold']) {
            Log::warning('Performance issue detected', $metrics);
        }
        
        return $metrics;
    }

    /**
     * Helper Methods
     */
    private function getModelTag(string $modelClass): string
    {
        $modelName = strtolower(class_basename($modelClass));
        return $this->cacheConfig['tags'][$modelName] ?? 'zena_' . $modelName;
    }

    private function calculateCacheHitRate(): float
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                $info = $redis->info();
                
                $hits = $info['keyspace_hits'] ?? 0;
                $misses = $info['keyspace_misses'] ?? 0;
                $total = $hits + $misses;
                
                return $total > 0 ? ($hits / $total) * 100 : 0;
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getCacheSize(): int
    {
        try {
            if (config('cache.default') === 'redis') {
                $redis = Redis::connection();
                return $redis->dbsize();
            }
            
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Bulk Operations Optimization
     */
    public function optimizeBulkInsert(string $table, array $data, int $chunkSize = 1000): int
    {
        $inserted = 0;
        $chunks = array_chunk($data, $chunkSize);
        
        foreach ($chunks as $chunk) {
            DB::table($table)->insert($chunk);
            $inserted += count($chunk);
        }
        
        return $inserted;
    }

    public function optimizeBulkUpdate(string $table, array $data, string $keyColumn, int $chunkSize = 1000): int
    {
        $updated = 0;
        $chunks = array_chunk($data, $chunkSize);
        
        foreach ($chunks as $chunk) {
            foreach ($chunk as $row) {
                DB::table($table)
                  ->where($keyColumn, $row[$keyColumn])
                  ->update($row);
                $updated++;
            }
        }
        
        return $updated;
    }

    /**
     * Index Optimization
     */
    public function optimizeIndexes(): array
    {
        $results = [];
        
        try {
            // Get all tables
            $tables = DB::select('SHOW TABLES');
            
            foreach ($tables as $table) {
                $tableName = array_values((array) $table)[0];
                
                // Analyze table indexes
                $indexes = DB::select("SHOW INDEX FROM {$tableName}");
                $results[$tableName] = [
                    'indexes' => count($indexes),
                    'index_details' => $indexes
                ];
            }
            
        } catch (\Exception $e) {
            Log::error('Index optimization failed', [
                'error' => $e->getMessage()
            ]);
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }
}
