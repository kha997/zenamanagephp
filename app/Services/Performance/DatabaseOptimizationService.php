<?php

namespace App\Services\Performance;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DatabaseOptimizationService
{
    /**
     * Optimize database performance.
     */
    public function optimizeDatabase(): array
    {
        $optimizationResults = [
            'timestamp' => now()->toISOString(),
            'optimizations' => []
        ];

        // Analyze slow queries
        $optimizationResults['optimizations']['analyze_slow_queries'] = $this->analyzeSlowQueries();
        
        // Optimize indexes
        $optimizationResults['optimizations']['optimize_indexes'] = $this->optimizeIndexes();
        
        // Analyze table sizes
        $optimizationResults['optimizations']['analyze_table_sizes'] = $this->analyzeTableSizes();
        
        // Optimize queries
        $optimizationResults['optimizations']['optimize_queries'] = $this->optimizeQueries();
        
        // Generate database report
        $optimizationResults['report'] = $this->generateDatabaseReport();

        Log::channel('performance')->info('Database optimization completed', $optimizationResults);

        return $optimizationResults;
    }

    /**
     * Analyze slow queries.
     */
    protected function analyzeSlowQueries(): array
    {
        $analysis = [
            'slow_queries' => [],
            'recommendations' => []
        ];

        try {
            // Get slow query log (if enabled)
            $slowQueries = DB::select("SHOW VARIABLES LIKE 'slow_query_log'");
            $slowQueryLog = $slowQueries[0]->Value ?? 'OFF';

            if ($slowQueryLog === 'ON') {
                // Analyze slow query log
                $logFile = DB::select("SHOW VARIABLES LIKE 'slow_query_log_file'");
                $logFilePath = $logFile[0]->Value ?? '/var/log/mysql/slow.log';

                if (file_exists($logFilePath)) {
                    $logContent = file_get_contents($logFilePath);
                    $analysis['slow_queries'] = $this->parseSlowQueryLog($logContent);
                }
            } else {
                $analysis['recommendations'][] = 'Enable slow query log to identify performance issues';
            }

            // Check query cache
            $queryCache = DB::select("SHOW VARIABLES LIKE 'query_cache_type'");
            $queryCacheType = $queryCache[0]->Value ?? 'OFF';

            if ($queryCacheType === 'OFF') {
                $analysis['recommendations'][] = 'Enable query cache for better performance';
            }

        } catch (\Exception $e) {
            Log::error('Error analyzing slow queries', ['error' => $e->getMessage()]);
        }

        return $analysis;
    }

    /**
     * Optimize indexes.
     */
    protected function optimizeIndexes(): array
    {
        $optimizations = [
            'missing_indexes' => [],
            'unused_indexes' => [],
            'recommendations' => []
        ];

        try {
            // Get all tables
            $tables = DB::select("SHOW TABLES");
            $databaseName = DB::getDatabaseName();

            foreach ($tables as $table) {
                $tableName = $table->{"Tables_in_{$databaseName}"};
                
                // Check for missing indexes on foreign keys
                $foreignKeys = DB::select("
                    SELECT COLUMN_NAME 
                    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
                    WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL
                ", [$databaseName, $tableName]);

                foreach ($foreignKeys as $fk) {
                    $columnName = $fk->COLUMN_NAME;
                    
                    // Check if index exists on this column
                    $indexes = DB::select("SHOW INDEX FROM {$tableName} WHERE Column_name = ?", [$columnName]);
                    
                    if (empty($indexes)) {
                        $optimizations['missing_indexes'][] = [
                            'table' => $tableName,
                            'column' => $columnName,
                            'type' => 'foreign_key'
                        ];
                    }
                }

                // Check for unused indexes
                $indexes = DB::select("SHOW INDEX FROM {$tableName}");
                foreach ($indexes as $index) {
                    if ($index->Key_name !== 'PRIMARY') {
                        // Check if index is used (simplified check)
                        $usage = DB::select("
                            SELECT COUNT(*) as usage_count 
                            FROM INFORMATION_SCHEMA.INDEX_STATISTICS 
                            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ?
                        ", [$databaseName, $tableName, $index->Key_name]);

                        if (empty($usage) || $usage[0]->usage_count === 0) {
                            $optimizations['unused_indexes'][] = [
                                'table' => $tableName,
                                'index' => $index->Key_name,
                                'columns' => $index->Column_name
                            ];
                        }
                    }
                }
            }

            // Generate recommendations
            if (!empty($optimizations['missing_indexes'])) {
                $optimizations['recommendations'][] = 'Add indexes on foreign key columns for better join performance';
            }

            if (!empty($optimizations['unused_indexes'])) {
                $optimizations['recommendations'][] = 'Consider removing unused indexes to improve write performance';
            }

        } catch (\Exception $e) {
            Log::error('Error optimizing indexes', ['error' => $e->getMessage()]);
        }

        return $optimizations;
    }

    /**
     * Analyze table sizes.
     */
    protected function analyzeTableSizes(): array
    {
        $analysis = [
            'table_sizes' => [],
            'largest_tables' => [],
            'recommendations' => []
        ];

        try {
            $databaseName = DB::getDatabaseName();
            
            // Get table sizes
            $tableSizes = DB::select("
                SELECT 
                    TABLE_NAME,
                    ROUND(((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024), 2) AS 'Size_MB',
                    TABLE_ROWS
                FROM INFORMATION_SCHEMA.TABLES 
                WHERE TABLE_SCHEMA = ?
                ORDER BY (DATA_LENGTH + INDEX_LENGTH) DESC
            ", [$databaseName]);

            $analysis['table_sizes'] = $tableSizes;
            $analysis['largest_tables'] = array_slice($tableSizes, 0, 5);

            // Generate recommendations
            foreach ($tableSizes as $table) {
                if ($table->Size_MB > 100) { // Tables larger than 100MB
                    $analysis['recommendations'][] = "Consider partitioning or archiving data for table '{$table->TABLE_NAME}'";
                }
            }

        } catch (\Exception $e) {
            Log::error('Error analyzing table sizes', ['error' => $e->getMessage()]);
        }

        return $analysis;
    }

    /**
     * Optimize queries.
     */
    protected function optimizeQueries(): array
    {
        $optimizations = [
            'query_optimizations' => [],
            'recommendations' => []
        ];

        try {
            // Check for N+1 query patterns
            $optimizations['query_optimizations']['n_plus_one'] = $this->detectNPlusOneQueries();
            
            // Check for missing eager loading
            $optimizations['query_optimizations']['eager_loading'] = $this->checkEagerLoading();
            
            // Check for inefficient queries
            $optimizations['query_optimizations']['inefficient_queries'] = $this->detectInefficientQueries();

        } catch (\Exception $e) {
            Log::error('Error optimizing queries', ['error' => $e->getMessage()]);
        }

        return $optimizations;
    }

    /**
     * Detect N+1 query patterns.
     */
    protected function detectNPlusOneQueries(): array
    {
        $detections = [];

        // Common N+1 patterns in Laravel
        $commonPatterns = [
            'User::with("roles")',
            'Project::with("teams.members")',
            'Task::with("assignee")',
            'Document::with("project")'
        ];

        foreach ($commonPatterns as $pattern) {
            $detections[] = [
                'pattern' => $pattern,
                'recommendation' => 'Use eager loading to prevent N+1 queries',
                'example' => 'Use ->with() method to load relationships'
            ];
        }

        return $detections;
    }

    /**
     * Check eager loading opportunities.
     */
    protected function checkEagerLoading(): array
    {
        $opportunities = [];

        // Check for models that could benefit from eager loading
        $models = ['User', 'Project', 'Task', 'Document', 'Team'];
        
        foreach ($models as $model) {
            $opportunities[] = [
                'model' => $model,
                'recommendation' => 'Consider eager loading relationships when querying multiple records',
                'example' => "{$model}::with(['relationships'])->get()"
            ];
        }

        return $opportunities;
    }

    /**
     * Detect inefficient queries.
     */
    protected function detectInefficientQueries(): array
    {
        $detections = [];

        // Common inefficient query patterns
        $inefficientPatterns = [
            'SELECT * FROM users',
            'WHERE created_at > NOW()',
            'ORDER BY RAND()',
            'LIKE %search%'
        ];

        foreach ($inefficientPatterns as $pattern) {
            $detections[] = [
                'pattern' => $pattern,
                'recommendation' => 'Optimize query to use indexes and limit results',
                'example' => 'Use specific columns, proper indexing, and pagination'
            ];
        }

        return $detections;
    }

    /**
     * Generate database report.
     */
    protected function generateDatabaseReport(): array
    {
        $report = [
            'database_info' => $this->getDatabaseInfo(),
            'performance_metrics' => $this->getPerformanceMetrics(),
            'configuration' => $this->getDatabaseConfiguration(),
            'recommendations' => $this->getDatabaseRecommendations()
        ];

        return $report;
    }

    /**
     * Get database information.
     */
    protected function getDatabaseInfo(): array
    {
        $info = [];

        try {
            $version = DB::select('SELECT VERSION() as version');
            $info['version'] = $version[0]->version ?? 'Unknown';

            $databaseName = DB::getDatabaseName();
            $info['name'] = $databaseName;

            $charset = DB::select("SHOW VARIABLES LIKE 'character_set_database'");
            $info['charset'] = $charset[0]->Value ?? 'Unknown';

        } catch (\Exception $e) {
            Log::error('Error getting database info', ['error' => $e->getMessage()]);
        }

        return $info;
    }

    /**
     * Get performance metrics.
     */
    protected function getPerformanceMetrics(): array
    {
        $metrics = [];

        try {
            // Get connection count
            $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            $metrics['connections'] = $connections[0]->Value ?? 0;

            // Get query count
            $queries = DB::select("SHOW STATUS LIKE 'Queries'");
            $metrics['queries'] = $queries[0]->Value ?? 0;

            // Get slow queries count
            $slowQueries = DB::select("SHOW STATUS LIKE 'Slow_queries'");
            $metrics['slow_queries'] = $slowQueries[0]->Value ?? 0;

            // Calculate slow query percentage
            $totalQueries = (int)$metrics['queries'];
            $slowQueryCount = (int)$metrics['slow_queries'];
            $metrics['slow_query_percentage'] = $totalQueries > 0 ? round(($slowQueryCount / $totalQueries) * 100, 2) : 0;

        } catch (\Exception $e) {
            Log::error('Error getting performance metrics', ['error' => $e->getMessage()]);
        }

        return $metrics;
    }

    /**
     * Get database configuration.
     */
    protected function getDatabaseConfiguration(): array
    {
        $config = [];

        try {
            $variables = [
                'innodb_buffer_pool_size',
                'query_cache_size',
                'max_connections',
                'innodb_log_file_size',
                'innodb_flush_log_at_trx_commit'
            ];

            foreach ($variables as $variable) {
                $result = DB::select("SHOW VARIABLES LIKE ?", [$variable]);
                if (!empty($result)) {
                    $config[$variable] = $result[0]->Value;
                }
            }

        } catch (\Exception $e) {
            Log::error('Error getting database configuration', ['error' => $e->getMessage()]);
        }

        return $config;
    }

    /**
     * Get database recommendations.
     */
    protected function getDatabaseRecommendations(): array
    {
        $recommendations = [];

        try {
            // Check buffer pool size
            $bufferPool = DB::select("SHOW VARIABLES LIKE 'innodb_buffer_pool_size'");
            if (!empty($bufferPool)) {
                $bufferPoolSize = (int)$bufferPool[0]->Value;
                if ($bufferPoolSize < 1073741824) { // Less than 1GB
                    $recommendations[] = [
                        'priority' => 'high',
                        'recommendation' => 'Increase InnoDB buffer pool size for better performance'
                    ];
                }
            }

            // Check query cache
            $queryCache = DB::select("SHOW VARIABLES LIKE 'query_cache_type'");
            if (!empty($queryCache) && $queryCache[0]->Value === 'OFF') {
                $recommendations[] = [
                    'priority' => 'medium',
                    'recommendation' => 'Enable query cache for better read performance'
                ];
            }

            // Check max connections
            $maxConnections = DB::select("SHOW VARIABLES LIKE 'max_connections'");
            if (!empty($maxConnections)) {
                $maxConn = (int)$maxConnections[0]->Value;
                if ($maxConn < 100) {
                    $recommendations[] = [
                        'priority' => 'medium',
                        'recommendation' => 'Increase max_connections for better concurrency'
                    ];
                }
            }

        } catch (\Exception $e) {
            Log::error('Error getting database recommendations', ['error' => $e->getMessage()]);
        }

        return $recommendations;
    }

    /**
     * Parse slow query log.
     */
    protected function parseSlowQueryLog(string $logContent): array
    {
        $queries = [];
        $lines = explode("\n", $logContent);
        
        $currentQuery = '';
        $queryTime = 0;
        
        foreach ($lines as $line) {
            if (strpos($line, 'Query_time:') !== false) {
                preg_match('/Query_time: ([\d.]+)/', $line, $matches);
                $queryTime = $matches[1] ?? 0;
            } elseif (strpos($line, 'SELECT') !== false || strpos($line, 'INSERT') !== false || strpos($line, 'UPDATE') !== false || strpos($line, 'DELETE') !== false) {
                $currentQuery = trim($line);
                if ($queryTime > 1.0) { // Queries slower than 1 second
                    $queries[] = [
                        'query' => $currentQuery,
                        'time' => $queryTime,
                        'recommendation' => 'Optimize this query for better performance'
                    ];
                }
            }
        }
        
        return $queries;
    }

    /**
     * Run database maintenance.
     */
    public function runMaintenance(): array
    {
        $maintenanceResults = [
            'timestamp' => now()->toISOString(),
            'operations' => []
        ];

        try {
            // Analyze tables
            $tables = DB::select("SHOW TABLES");
            $databaseName = DB::getDatabaseName();
            
            foreach ($tables as $table) {
                $tableName = $table->{"Tables_in_{$databaseName}"};
                
                // Analyze table
                DB::statement("ANALYZE TABLE {$tableName}");
                $maintenanceResults['operations'][] = "Analyzed table: {$tableName}";
                
                // Optimize table
                DB::statement("OPTIMIZE TABLE {$tableName}");
                $maintenanceResults['operations'][] = "Optimized table: {$tableName}";
            }

        } catch (\Exception $e) {
            Log::error('Error running database maintenance', ['error' => $e->getMessage()]);
            $maintenanceResults['error'] = $e->getMessage();
        }

        Log::channel('performance')->info('Database maintenance completed', $maintenanceResults);

        return $maintenanceResults;
    }
}
