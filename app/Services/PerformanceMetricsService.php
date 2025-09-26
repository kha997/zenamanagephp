<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

/**
 * Service theo dõi và báo cáo performance metrics
 * Cung cấp insights về database performance, cache hit rates, và response times
 */
class PerformanceMetricsService
{
    /**
     * Get comprehensive performance metrics
     * 
     * @return array
     */
    public function getMetrics(): array
    {
        return [
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'memory' => $this->getMemoryMetrics(),
            'queries' => $this->getQueryMetrics(),
        ];
    }

    /**
     * Get database performance metrics
     * 
     * @return array
     */
    private function getDatabaseMetrics(): array
    {
        $metrics = DB::select("
            SELECT 
                VARIABLE_NAME,
                VARIABLE_VALUE
            FROM performance_schema.global_status 
            WHERE VARIABLE_NAME IN (
                'Queries',
                'Questions', 
                'Slow_queries',
                'Connections',
                'Threads_connected',
                'Innodb_buffer_pool_read_requests',
                'Innodb_buffer_pool_reads',
                'Innodb_rows_read',
                'Innodb_rows_inserted',
                'Innodb_rows_updated',
                'Innodb_rows_deleted'
            )
        ");

        $result = [];
        foreach ($metrics as $metric) {
            $result[strtolower($metric->VARIABLE_NAME)] = (int) $metric->VARIABLE_VALUE;
        }

        // Calculate buffer pool hit rate
        if (isset($result['innodb_buffer_pool_read_requests']) && isset($result['innodb_buffer_pool_reads'])) {
            $hitRate = ($result['innodb_buffer_pool_read_requests'] - $result['innodb_buffer_pool_reads']) 
                     / $result['innodb_buffer_pool_read_requests'] * 100;
            $result['buffer_pool_hit_rate'] = round($hitRate, 2);
        }

        return $result;
    }

    /**
     * Get cache performance metrics
     * 
     * @return array
     */
    private function getCacheMetrics(): array
    {
        $redis = Redis::connection();
        $info = $redis->info();

        return [
            'memory_usage' => $info['used_memory_human'] ?? 'N/A',
            'memory_peak' => $info['used_memory_peak_human'] ?? 'N/A',
            'connected_clients' => (int) ($info['connected_clients'] ?? 0),
            'total_commands' => (int) ($info['total_commands_processed'] ?? 0),
            'keyspace_hits' => (int) ($info['keyspace_hits'] ?? 0),
            'keyspace_misses' => (int) ($info['keyspace_misses'] ?? 0),
            'hit_rate' => $this->calculateCacheHitRate($info),
        ];
    }

    /**
     * Calculate cache hit rate
     * 
     * @param array $info
     * @return float
     */
    private function calculateCacheHitRate(array $info): float
    {
        $hits = (int) ($info['keyspace_hits'] ?? 0);
        $misses = (int) ($info['keyspace_misses'] ?? 0);
        $total = $hits + $misses;

        return $total > 0 ? round(($hits / $total) * 100, 2) : 0.0;
    }

    /**
     * Get memory usage metrics
     * 
     * @return array
     */
    private function getMemoryMetrics(): array
    {
        return [
            'php_memory_usage' => memory_get_usage(true),
            'php_memory_peak' => memory_get_peak_usage(true),
            'php_memory_limit' => ini_get('memory_limit'),
            'php_memory_usage_formatted' => $this->formatBytes(memory_get_usage(true)),
            'php_memory_peak_formatted' => $this->formatBytes(memory_get_peak_usage(true)),
        ];
    }

    /**
     * Get query performance metrics
     * 
     * @return array
     */
    private function getQueryMetrics(): array
    {
        // Get top 5 slowest query patterns
        $slowQueries = DB::select("
            SELECT 
                DIGEST_TEXT as query_pattern,
                COUNT_STAR as exec_count,
                AVG_TIMER_WAIT/1000000000 as avg_time_seconds,
                SUM_TIMER_WAIT/1000000000 as total_time_seconds
            FROM performance_schema.events_statements_summary_by_digest 
            WHERE DIGEST_TEXT IS NOT NULL
            ORDER BY AVG_TIMER_WAIT DESC 
            LIMIT 5
        ");

        return [
            'slow_queries' => $slowQueries,
            'total_queries_today' => $this->getTotalQueriesToday(),
        ];
    }

    /**
     * Get total queries executed today
     * 
     * @return int
     */
    private function getTotalQueriesToday(): int
    {
        $result = DB::select("
            SELECT COUNT(*) as total
            FROM performance_schema.events_statements_history_long
            WHERE TIMER_START >= UNIX_TIMESTAMP(CURDATE()) * 1000000000000
        ");

        return (int) ($result[0]->total ?? 0);
    }

    /**
     * Format bytes to human readable format
     * 
     * @param int $bytes
     * @return string
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
     * Log performance metrics to file
     * 
     * @return void
     */
    public function logMetrics(): void
    {
        $metrics = $this->getMetrics();
        $logData = [
            'timestamp' => now()->toISOString(),
            'metrics' => $metrics
        ];

        file_put_contents(
            storage_path('logs/performance-' . now()->format('Y-m-d') . '.log'),
            json_encode($logData) . "\n",
            FILE_APPEND | LOCK_EX
        );
    }
}