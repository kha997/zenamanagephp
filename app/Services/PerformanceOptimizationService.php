<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Artisan;

class PerformanceOptimizationService
{
    /**
     * Optimize database tables
     */
    public function optimizeTables(): array
    {
        $results = [];
        $tables = $this->getTablesToOptimize();
        
        foreach ($tables as $table) {
            try {
                $startTime = microtime(true);
                DB::statement("OPTIMIZE TABLE {$table}");
                $endTime = microtime(true);
                
                $results[$table] = [
                    'status' => 'success',
                    'execution_time' => round(($endTime - $startTime) * 1000, 2) . 'ms'
                ];
            } catch (\Exception $e) {
                $results[$table] = [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Analyze table performance
     */
    public function analyzeTables(): array
    {
        $results = [];
        $tables = $this->getTablesToAnalyze();
        
        foreach ($tables as $table) {
            try {
                $startTime = microtime(true);
                DB::statement("ANALYZE TABLE {$table}");
                $endTime = microtime(true);
                
                $results[$table] = [
                    'status' => 'success',
                    'execution_time' => round(($endTime - $startTime) * 1000, 2) . 'ms'
                ];
            } catch (\Exception $e) {
                $results[$table] = [
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Get slow query log
     */
    public function getSlowQueries(int $limit = 10): array
    {
        try {
            $queries = DB::select("
                SELECT 
                    sql_text,
                    exec_count,
                    avg_timer_wait/1000000000 as avg_time_seconds,
                    max_timer_wait/1000000000 as max_time_seconds,
                    sum_timer_wait/1000000000 as total_time_seconds
                FROM performance_schema.events_statements_summary_by_digest 
                WHERE avg_timer_wait > 1000000000 
                ORDER BY avg_timer_wait DESC 
                LIMIT ?
            ", [$limit]);
            
            return $queries;
        } catch (\Exception $e) {
            Log::error('Failed to get slow queries', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Clear application caches
     */
    public function clearCaches(): array
    {
        $results = [];
        
        try {
            // Clear Laravel caches
            Artisan::call('cache:clear');
            $results['laravel_cache'] = 'cleared';
            
            Artisan::call('config:clear');
            $results['config_cache'] = 'cleared';
            
            Artisan::call('route:clear');
            $results['route_cache'] = 'cleared';
            
            Artisan::call('view:clear');
            $results['view_cache'] = 'cleared';
            
            // Clear Redis cache
            Cache::flush();
            $results['redis_cache'] = 'cleared';
            
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): array
    {
        try {
            $redis = Cache::getRedis();
            
            return [
                'redis_memory_used' => $redis->info('memory')['used_memory_human'] ?? 'N/A',
                'redis_keys' => $redis->dbSize(),
                'redis_connected_clients' => $redis->info('clients')['connected_clients'] ?? 'N/A',
                'redis_uptime' => $redis->info('server')['uptime_in_seconds'] ?? 'N/A',
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStats(): array
    {
        try {
            $stats = [];
            
            // Get table sizes
            $tables = DB::select("
                SELECT 
                    table_name,
                    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb,
                    table_rows
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
                ORDER BY (data_length + index_length) DESC
                LIMIT 10
            ");
            
            $stats['table_sizes'] = $tables;
            
            // Get index usage
            $indexUsage = DB::select("
                SELECT 
                    table_name,
                    index_name,
                    cardinality
                FROM information_schema.statistics 
                WHERE table_schema = DATABASE()
                ORDER BY cardinality DESC
                LIMIT 10
            ");
            
            $stats['index_usage'] = $indexUsage;
            
            return $stats;
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Optimize queries by adding missing indexes
     */
    public function suggestIndexes(): array
    {
        $suggestions = [];
        
        try {
            // Check for missing indexes on foreign keys
            $missingIndexes = DB::select("
                SELECT 
                    t.table_name,
                    k.column_name,
                    k.referenced_table_name,
                    k.referenced_column_name
                FROM information_schema.table_constraints t
                JOIN information_schema.key_column_usage k 
                    ON t.constraint_name = k.constraint_name
                LEFT JOIN information_schema.statistics s 
                    ON s.table_name = t.table_name 
                    AND s.column_name = k.column_name
                WHERE t.constraint_type = 'FOREIGN KEY'
                    AND s.index_name IS NULL
                    AND t.table_schema = DATABASE()
            ");
            
            foreach ($missingIndexes as $index) {
                $suggestions[] = [
                    'type' => 'missing_foreign_key_index',
                    'table' => $index->table_name,
                    'column' => $index->column_name,
                    'suggestion' => "CREATE INDEX idx_{$index->table_name}_{$index->column_name} ON {$index->table_name} ({$index->column_name});"
                ];
            }
            
            // Check for tables without primary keys
            $tablesWithoutPK = DB::select("
                SELECT table_name
                FROM information_schema.tables t
                LEFT JOIN information_schema.table_constraints tc 
                    ON t.table_name = tc.table_name 
                    AND tc.constraint_type = 'PRIMARY KEY'
                WHERE t.table_schema = DATABASE()
                    AND tc.constraint_name IS NULL
                    AND t.table_type = 'BASE TABLE'
            ");
            
            foreach ($tablesWithoutPK as $table) {
                $suggestions[] = [
                    'type' => 'missing_primary_key',
                    'table' => $table->table_name,
                    'suggestion' => "Table {$table->table_name} is missing a primary key"
                ];
            }
            
        } catch (\Exception $e) {
            $suggestions[] = [
                'type' => 'error',
                'error' => $e->getMessage()
            ];
        }
        
        return $suggestions;
    }

    /**
     * Monitor query performance
     */
    public function monitorQueryPerformance(): array
    {
        try {
            // Get current query performance metrics
            $metrics = DB::select("
                SELECT 
                    COUNT(*) as total_queries,
                    AVG(timer_wait/1000000000) as avg_query_time,
                    MAX(timer_wait/1000000000) as max_query_time,
                    SUM(timer_wait/1000000000) as total_query_time
                FROM performance_schema.events_statements_summary_by_digest
                WHERE last_seen > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            
            return $metrics[0] ?? [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get memory usage statistics
     */
    public function getMemoryStats(): array
    {
        try {
            $memoryUsage = memory_get_usage(true);
            $peakMemoryUsage = memory_get_peak_usage(true);
            
        return [
                'current_memory' => $this->formatBytes($memoryUsage),
                'peak_memory' => $this->formatBytes($peakMemoryUsage),
                'memory_limit' => ini_get('memory_limit'),
                'current_memory_bytes' => $memoryUsage,
                'peak_memory_bytes' => $peakMemoryUsage,
            ];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Optimize file storage
     */
    public function optimizeFileStorage(): array
    {
        $results = [];
        
        try {
            // Get storage statistics
            $storagePath = storage_path('app');
            $totalSize = $this->getDirectorySize($storagePath);
            
            $results['storage_path'] = $storagePath;
            $results['total_size'] = $this->formatBytes($totalSize);
            $results['total_size_bytes'] = $totalSize;
            
            // Check for large files
            $largeFiles = $this->findLargeFiles($storagePath, 10 * 1024 * 1024); // 10MB
            $results['large_files'] = $largeFiles;
            
            // Check for old log files
            $oldLogs = $this->findOldLogFiles($storagePath . '/logs');
            $results['old_log_files'] = $oldLogs;
            
        } catch (\Exception $e) {
            $results['error'] = $e->getMessage();
        }
        
        return $results;
    }

    /**
     * Get tables to optimize
     */
    private function getTablesToOptimize(): array
    {
        return [
            'users', 'projects', 'tasks', 'documents', 'project_activities', 
            'notifications', 'audit_logs', 'document_versions'
        ];
    }

    /**
     * Get tables to analyze
     */
    private function getTablesToAnalyze(): array
    {
        return [
            'users', 'projects', 'tasks', 'documents', 'project_activities', 
            'notifications', 'audit_logs', 'document_versions'
        ];
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get directory size recursively
     */
    private function getDirectorySize(string $directory): int
    {
        $size = 0;
        
        if (is_dir($directory)) {
            $files = glob($directory . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    $size += filesize($file);
                } elseif (is_dir($file)) {
                    $size += $this->getDirectorySize($file);
                }
            }
        }
        
        return $size;
    }

    /**
     * Find large files in directory
     */
    private function findLargeFiles(string $directory, int $minSize): array
    {
        $largeFiles = [];
        
        if (is_dir($directory)) {
            $files = glob($directory . '/*');
            foreach ($files as $file) {
                if (is_file($file) && filesize($file) > $minSize) {
                    $largeFiles[] = [
                        'path' => $file,
                        'size' => $this->formatBytes(filesize($file)),
                        'size_bytes' => filesize($file),
                        'modified' => date('Y-m-d H:i:s', filemtime($file))
                    ];
                } elseif (is_dir($file)) {
                    $largeFiles = array_merge($largeFiles, $this->findLargeFiles($file, $minSize));
                }
            }
        }
        
        return $largeFiles;
    }

    /**
     * Find old log files
     */
    private function findOldLogFiles(string $logDirectory): array
    {
        $oldLogs = [];
        
        if (is_dir($logDirectory)) {
            $files = glob($logDirectory . '/*.log');
            foreach ($files as $file) {
                if (filemtime($file) < strtotime('-30 days')) {
                    $oldLogs[] = [
                        'path' => $file,
                        'size' => $this->formatBytes(filesize($file)),
                        'modified' => date('Y-m-d H:i:s', filemtime($file)),
                        'age_days' => floor((time() - filemtime($file)) / 86400)
                    ];
                }
            }
        }
        
        return $oldLogs;
    }
}