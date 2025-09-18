<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * DatabaseOptimizationService - Service cho database optimization
 */
class DatabaseOptimizationService
{
    private array $optimizationConfig;

    public function __construct()
    {
        $this->optimizationConfig = [
            'enable_query_logging' => config('database.optimization.enable_query_logging', false),
            'slow_query_threshold' => config('database.optimization.slow_query_threshold', 1000),
            'max_queries_per_request' => config('database.optimization.max_queries_per_request', 100),
            'enable_query_caching' => config('database.optimization.enable_query_caching', true),
            'auto_optimize_tables' => config('database.optimization.auto_optimize_tables', false),
            'index_optimization' => config('database.optimization.index_optimization', true)
        ];
    }

    /**
     * Optimize all tables
     */
    public function optimizeAllTables(): array
    {
        $results = [];
        
        try {
            $tables = $this->getAllTables();
            
            foreach ($tables as $table) {
                $results[$table] = $this->optimizeTable($table);
            }
            
            $results['summary'] = [
                'total_tables' => count($tables),
                'optimized_tables' => count(array_filter($results, fn($r) => $r['success'])),
                'failed_tables' => count(array_filter($results, fn($r) => !$r['success']))
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to optimize all tables', [
                'error' => $e->getMessage()
            ]);
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Optimize specific table
     */
    public function optimizeTable(string $tableName): array
    {
        try {
            $startTime = microtime(true);
            
            // Analyze table
            DB::statement("ANALYZE TABLE {$tableName}");
            
            // Optimize table
            DB::statement("OPTIMIZE TABLE {$tableName}");
            
            $executionTime = (microtime(true) - $startTime) * 1000;
            
            return [
                'success' => true,
                'table' => $tableName,
                'execution_time' => $executionTime,
                'optimized_at' => now()->toISOString()
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to optimize table', [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'table' => $tableName,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Analyze table structure
     */
    public function analyzeTable(string $tableName): array
    {
        try {
            $analysis = [];
            
            // Get table structure
            $columns = DB::select("DESCRIBE {$tableName}");
            $analysis['columns'] = $columns;
            
            // Get indexes
            $indexes = DB::select("SHOW INDEX FROM {$tableName}");
            $analysis['indexes'] = $indexes;
            
            // Get table size
            $size = DB::select("SELECT 
                table_name,
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size_MB',
                table_rows
                FROM information_schema.tables 
                WHERE table_schema = DATABASE() 
                AND table_name = '{$tableName}'");
            $analysis['size'] = $size[0] ?? null;
            
            // Get table status
            $status = DB::select("SHOW TABLE STATUS LIKE '{$tableName}'");
            $analysis['status'] = $status[0] ?? null;
            
            return [
                'success' => true,
                'table' => $tableName,
                'analysis' => $analysis
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to analyze table', [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'table' => $tableName,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Create missing indexes
     */
    public function createMissingIndexes(): array
    {
        $results = [];
        
        try {
            $tables = $this->getAllTables();
            
            foreach ($tables as $table) {
                $results[$table] = $this->createIndexesForTable($table);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to create missing indexes', [
                'error' => $e->getMessage()
            ]);
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Create indexes for specific table
     */
    private function createIndexesForTable(string $tableName): array
    {
        $results = [];
        
        try {
            // Define common indexes for different table types
            $indexes = $this->getRecommendedIndexes($tableName);
            
            foreach ($indexes as $indexName => $indexConfig) {
                if (!$this->indexExists($tableName, $indexName)) {
                    $this->createIndex($tableName, $indexName, $indexConfig);
                    $results[] = [
                        'index' => $indexName,
                        'created' => true
                    ];
                } else {
                    $results[] = [
                        'index' => $indexName,
                        'created' => false,
                        'reason' => 'Already exists'
                    ];
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to create indexes for table', [
                'table' => $tableName,
                'error' => $e->getMessage()
            ]);
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Get recommended indexes for table
     */
    private function getRecommendedIndexes(string $tableName): array
    {
        $indexes = [];
        
        switch ($tableName) {
            case 'projects':
                $indexes = [
                    'idx_projects_status' => ['status'],
                    'idx_projects_priority' => ['priority'],
                    'idx_projects_start_date' => ['start_date'],
                    'idx_projects_end_date' => ['end_date'],
                    'idx_projects_tenant_status' => ['tenant_id', 'status'],
                    'idx_projects_pm_status' => ['pm_id', 'status']
                ];
                break;
                
            case 'tasks':
                $indexes = [
                    'idx_tasks_status' => ['status'],
                    'idx_tasks_priority' => ['priority'],
                    'idx_tasks_due_date' => ['due_date'],
                    'idx_tasks_project_status' => ['project_id', 'status'],
                    'idx_tasks_assignee_status' => ['assignee_id', 'status'],
                    'idx_tasks_tenant_status' => ['tenant_id', 'status']
                ];
                break;
                
            case 'users':
                $indexes = [
                    'idx_users_email' => ['email'],
                    'idx_users_active' => ['is_active'],
                    'idx_users_tenant' => ['tenant_id'],
                    'idx_users_last_login' => ['last_login_at']
                ];
                break;
                
            case 'calendar_events':
                $indexes = [
                    'idx_calendar_events_start_time' => ['start_time'],
                    'idx_calendar_events_end_time' => ['end_time'],
                    'idx_calendar_events_project' => ['project_id'],
                    'idx_calendar_events_integration' => ['calendar_integration_id'],
                    'idx_calendar_events_status' => ['status']
                ];
                break;
                
            case 'project_activities':
                $indexes = [
                    'idx_project_activities_project' => ['project_id'],
                    'idx_project_activities_user' => ['user_id'],
                    'idx_project_activities_type' => ['type'],
                    'idx_project_activities_created' => ['created_at'],
                    'idx_project_activities_project_created' => ['project_id', 'created_at']
                ];
                break;
        }
        
        return $indexes;
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $tableName, string $indexName): bool
    {
        try {
            $indexes = DB::select("SHOW INDEX FROM {$tableName} WHERE Key_name = '{$indexName}'");
            return count($indexes) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Create index
     */
    private function createIndex(string $tableName, string $indexName, array $columns): void
    {
        $columnList = implode(', ', $columns);
        DB::statement("CREATE INDEX {$indexName} ON {$tableName} ({$columnList})");
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStatistics(): array
    {
        $stats = [];
        
        try {
            // Get database size
            $dbSize = DB::select("SELECT 
                ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB_Size_MB'
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()");
            $stats['database_size_mb'] = $dbSize[0]->DB_Size_MB ?? 0;
            
            // Get table count
            $tableCount = DB::select("SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = DATABASE()");
            $stats['table_count'] = $tableCount[0]->table_count ?? 0;
            
            // Get index count
            $indexCount = DB::select("SELECT COUNT(*) as index_count FROM information_schema.statistics WHERE table_schema = DATABASE()");
            $stats['index_count'] = $indexCount[0]->index_count ?? 0;
            
            // Get connection count
            $connectionCount = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            $stats['connections'] = $connectionCount[0]->Value ?? 0;
            
            // Get query statistics
            $queryStats = DB::select("SHOW STATUS LIKE 'Queries'");
            $stats['total_queries'] = $queryStats[0]->Value ?? 0;
            
            // Get slow query count
            $slowQueries = DB::select("SHOW STATUS LIKE 'Slow_queries'");
            $stats['slow_queries'] = $slowQueries[0]->Value ?? 0;
            
        } catch (\Exception $e) {
            Log::error('Failed to get database statistics', [
                'error' => $e->getMessage()
            ]);
            $stats['error'] = $e->getMessage();
        }
        
        return $stats;
    }

    /**
     * Get slow queries
     */
    public function getSlowQueries(int $limit = 10): array
    {
        try {
            // Enable slow query log
            DB::statement("SET GLOBAL slow_query_log = 'ON'");
            DB::statement("SET GLOBAL long_query_time = 1");
            
            // Get slow queries from log
            $slowQueries = DB::select("
                SELECT 
                    sql_text,
                    exec_count,
                    avg_timer_wait/1000000000 as avg_time_seconds,
                    max_timer_wait/1000000000 as max_time_seconds
                FROM performance_schema.events_statements_summary_by_digest 
                WHERE avg_timer_wait > 1000000000 
                ORDER BY avg_timer_wait DESC 
                LIMIT {$limit}
            ");
            
            return [
                'success' => true,
                'slow_queries' => $slowQueries
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get slow queries', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Optimize queries
     */
    public function optimizeQueries(): array
    {
        $results = [];
        
        try {
            // Get query cache status
            $queryCache = DB::select("SHOW VARIABLES LIKE 'query_cache%'");
            $results['query_cache'] = $queryCache;
            
            // Enable query cache if not enabled
            if (!$this->isQueryCacheEnabled()) {
                DB::statement("SET GLOBAL query_cache_type = ON");
                DB::statement("SET GLOBAL query_cache_size = 268435456"); // 256MB
                $results['query_cache_enabled'] = true;
            }
            
            // Optimize buffer pool
            $bufferPool = DB::select("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
            $results['buffer_pool'] = $bufferPool;
            
            // Analyze all tables
            $tables = $this->getAllTables();
            foreach ($tables as $table) {
                DB::statement("ANALYZE TABLE {$table}");
            }
            $results['tables_analyzed'] = count($tables);
            
        } catch (\Exception $e) {
            Log::error('Failed to optimize queries', [
                'error' => $e->getMessage()
            ]);
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Check if query cache is enabled
     */
    private function isQueryCacheEnabled(): bool
    {
        try {
            $result = DB::select("SHOW VARIABLES LIKE 'query_cache_type'");
            return $result[0]->Value === 'ON';
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get all tables
     */
    private function getAllTables(): array
    {
        $tables = DB::select('SHOW TABLES');
        return array_map(function($table) {
            return array_values((array) $table)[0];
        }, $tables);
    }

    /**
     * Vacuum database (for PostgreSQL)
     */
    public function vacuumDatabase(): array
    {
        try {
            $results = [];
            
            // Check if PostgreSQL
            if (DB::getDriverName() === 'pgsql') {
                DB::statement('VACUUM ANALYZE');
                $results['vacuum_completed'] = true;
            } else {
                $results['vacuum_completed'] = false;
                $results['message'] = 'Vacuum is only available for PostgreSQL';
            }
            
            return $results;
            
        } catch (\Exception $e) {
            Log::error('Failed to vacuum database', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get table fragmentation
     */
    public function getTableFragmentation(): array
    {
        $fragmentation = [];
        
        try {
            $tables = $this->getAllTables();
            
            foreach ($tables as $table) {
                $result = DB::select("
                    SELECT 
                        table_name,
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size_MB',
                        ROUND((data_free / 1024 / 1024), 2) AS 'Free_MB',
                        ROUND((data_free / (data_length + index_length)) * 100, 2) AS 'Fragmentation_Percent'
                    FROM information_schema.tables 
                    WHERE table_schema = DATABASE() 
                    AND table_name = '{$table}'
                ");
                
                if (!empty($result)) {
                    $fragmentation[$table] = $result[0];
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to get table fragmentation', [
                'error' => $e->getMessage()
            ]);
            $fragmentation['error'] = $e->getMessage();
        }
        
        return $fragmentation;
    }
}
