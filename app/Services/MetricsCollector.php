<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class MetricsCollector
{
    private $metrics = [];
    private $startTime;
    private $startMemory;

    public function __construct()
    {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    /**
     * Collect all application metrics
     */
    public function collectAll(): array
    {
        $this->metrics = [
            'timestamp' => now()->toISOString(),
            'application' => $this->collectApplicationMetrics(),
            'database' => $this->collectDatabaseMetrics(),
            'cache' => $this->collectCacheMetrics(),
            'queue' => $this->collectQueueMetrics(),
            'system' => $this->collectSystemMetrics(),
            'performance' => $this->collectPerformanceMetrics(),
        ];

        return $this->metrics;
    }

    /**
     * Collect application-level metrics
     */
    private function collectApplicationMetrics(): array
    {
        return [
            'active_users' => $this->getActiveUsers(),
            'requests_per_minute' => $this->getRequestsPerMinute(),
            'error_rate' => $this->getErrorRate(),
            'response_time_avg' => $this->getAverageResponseTime(),
            'response_time_p95' => $this->getP95ResponseTime(),
            'memory_usage' => $this->getMemoryUsage(),
            'memory_peak' => memory_get_peak_usage(true),
        ];
    }

    /**
     * Collect database metrics
     */
    private function collectDatabaseMetrics(): array
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            
            return [
                'connection_count' => $this->getConnectionCount(),
                'slow_queries' => $this->getSlowQueries(),
                'query_count' => $this->getQueryCount(),
                'connection_status' => 'connected',
                'database_size' => $this->getDatabaseSize(),
            ];
        } catch (\Exception $e) {
            return [
                'connection_status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect cache metrics
     */
    private function collectCacheMetrics(): array
    {
        try {
            $hitRate = $this->getCacheHitRate();
            return [
                'hit_rate' => $hitRate,
                'miss_rate' => 100 - $hitRate,
                'status' => 'connected',
                'driver' => config('cache.default'),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect queue metrics
     */
    private function collectQueueMetrics(): array
    {
        try {
            return [
                'pending_jobs' => $this->getPendingJobs(),
                'failed_jobs' => $this->getFailedJobs(),
                'processed_jobs' => $this->getProcessedJobs(),
                'queue_size' => $this->getQueueSize(),
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect system metrics
     */
    private function collectSystemMetrics(): array
    {
        return [
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getSystemMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'load_average' => $this->getLoadAverage(),
            'uptime' => $this->getUptime(),
        ];
    }

    /**
     * Collect performance metrics
     */
    private function collectPerformanceMetrics(): array
    {
        $executionTime = (microtime(true) - $this->startTime) * 1000;
        $memoryUsed = memory_get_usage(true) - $this->startMemory;

        return [
            'execution_time_ms' => round($executionTime, 2),
            'memory_used_bytes' => $memoryUsed,
            'memory_used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'throughput_rps' => $this->getThroughput(),
        ];
    }

    // Helper methods for specific metrics

    private function getActiveUsers(): int
    {
        return Cache::remember('active_users_count', 60, function () {
            return DB::table('sessions')
                ->where('last_activity', '>', now()->subMinutes(5))
                ->count();
        });
    }

    private function getRequestsPerMinute(): int
    {
        return Cache::remember('requests_per_minute', 60, function () {
            return DB::table('audit_logs')
                ->where('created_at', '>', now()->subMinute())
                ->count();
        });
    }

    private function getErrorRate(): float
    {
        $total = DB::table('audit_logs')
            ->where('created_at', '>', now()->subHour())
            ->count();
            
        $errors = DB::table('audit_logs')
            ->where('created_at', '>', now()->subHour())
            ->where('action', 'like', '%error%')
            ->count();
            
        return $total > 0 ? round(($errors / $total) * 100, 2) : 0;
    }

    private function getAverageResponseTime(): float
    {
        return Cache::remember('avg_response_time', 60, function () {
            // This would be calculated from actual request logs
            return 150.5; // Placeholder
        });
    }

    private function getP95ResponseTime(): float
    {
        return Cache::remember('p95_response_time', 60, function () {
            // This would be calculated from actual request logs
            return 300.0; // Placeholder
        });
    }

    private function getMemoryUsage(): array
    {
        $usage = memory_get_usage(true);
        $limit = ini_get('memory_limit');
        $limitBytes = $this->parseMemoryLimit($limit);
        
        return [
            'used_bytes' => $usage,
            'used_mb' => round($usage / 1024 / 1024, 2),
            'limit_bytes' => $limitBytes,
            'limit_mb' => round($limitBytes / 1024 / 1024, 2),
            'percentage' => round(($usage / $limitBytes) * 100, 2),
        ];
    }

    private function getConnectionCount(): int
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Threads_connected'");
            return $result[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getSlowQueries(): int
    {
        try {
            $result = DB::select("SHOW STATUS LIKE 'Slow_queries'");
            return $result[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getQueryCount(): int
    {
        return count(DB::getQueryLog());
    }

    private function getDatabaseSize(): string
    {
        try {
            $result = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB'
                FROM information_schema.tables 
                WHERE table_schema = ?
            ", [config('database.connections.mysql.database')]);
            
            return $result[0]->{'DB Size in MB'} . ' MB';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    private function getCacheHitRate(): float
    {
        // This would be calculated from actual cache statistics
        return 85.5; // Placeholder
    }

    private function getPendingJobs(): int
    {
        try {
            return DB::table('jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getFailedJobs(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getProcessedJobs(): int
    {
        return Cache::remember('processed_jobs_count', 300, function () {
            // This would be calculated from job logs
            return 1250; // Placeholder
        });
    }

    private function getQueueSize(): int
    {
        return $this->getPendingJobs();
    }

    private function getCpuUsage(): float
    {
        $load = sys_getloadavg();
        // Load average is typically 0-4 for most systems, convert to percentage
        $cpuUsage = min($load[0] * 25, 100); // Scale load average to percentage
        return round($cpuUsage, 2);
    }

    private function getSystemMemoryUsage(): array
    {
        // Check if we're on Linux
        if (file_exists('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
            
            $totalKB = $total[1] ?? 0;
            $availableKB = $available[1] ?? 0;
            $usedKB = $totalKB - $availableKB;
            
            return [
                'total_mb' => round($totalKB / 1024, 2),
                'used_mb' => round($usedKB / 1024, 2),
                'available_mb' => round($availableKB / 1024, 2),
                'percentage' => $totalKB > 0 ? round(($usedKB / $totalKB) * 100, 2) : 0,
            ];
        }
        
        // Fallback for macOS/Windows - use PHP memory info
        $memoryLimit = $this->parseMemoryLimit(ini_get('memory_limit'));
        $memoryUsed = memory_get_usage(true);
        $memoryPeak = memory_get_peak_usage(true);
        
        return [
            'total_mb' => round($memoryLimit / 1024 / 1024, 2),
            'used_mb' => round($memoryUsed / 1024 / 1024, 2),
            'available_mb' => round(($memoryLimit - $memoryUsed) / 1024 / 1024, 2),
            'percentage' => round(($memoryUsed / $memoryLimit) * 100, 2),
        ];
    }

    private function getDiskUsage(): array
    {
        $bytes = disk_free_space('/');
        $totalBytes = disk_total_space('/');
        $usedBytes = $totalBytes - $bytes;
        
        return [
            'total_gb' => round($totalBytes / 1024 / 1024 / 1024, 2),
            'used_gb' => round($usedBytes / 1024 / 1024 / 1024, 2),
            'free_gb' => round($bytes / 1024 / 1024 / 1024, 2),
            'percentage' => round(($usedBytes / $totalBytes) * 100, 2),
        ];
    }

    private function getLoadAverage(): array
    {
        $load = sys_getloadavg();
        return [
            '1min' => $load[0],
            '5min' => $load[1],
            '15min' => $load[2],
        ];
    }

    private function getUptime(): string
    {
        $uptime = shell_exec('uptime');
        return $uptime ? trim($uptime) : 'Unknown';
    }

    private function getThroughput(): float
    {
        return Cache::remember('throughput_rps', 60, function () {
            // This would be calculated from actual request logs
            return 45.2; // Placeholder
        });
    }

    private function parseMemoryLimit(string $limit): int
    {
        $limit = trim($limit);
        $last = strtolower($limit[strlen($limit) - 1]);
        $limit = (int) $limit;

        switch ($last) {
            case 'g':
                $limit *= 1024;
            case 'm':
                $limit *= 1024;
            case 'k':
                $limit *= 1024;
        }

        return $limit;
    }

    /**
     * Export metrics in Prometheus format
     */
    public function exportPrometheusFormat(): string
    {
        $metrics = $this->collectAll();
        $output = [];

        // Application metrics
        $output[] = "# HELP zenamanage_active_users Number of active users";
        $output[] = "# TYPE zenamanage_active_users gauge";
        $output[] = "zenamanage_active_users " . $metrics['application']['active_users'];

        $output[] = "# HELP zenamanage_requests_per_minute Requests per minute";
        $output[] = "# TYPE zenamanage_requests_per_minute counter";
        $output[] = "zenamanage_requests_per_minute " . $metrics['application']['requests_per_minute'];

        $output[] = "# HELP zenamanage_error_rate Error rate percentage";
        $output[] = "# TYPE zenamanage_error_rate gauge";
        $output[] = "zenamanage_error_rate " . $metrics['application']['error_rate'];

        $output[] = "# HELP zenamanage_response_time_avg Average response time in milliseconds";
        $output[] = "# TYPE zenamanage_response_time_avg gauge";
        $output[] = "zenamanage_response_time_avg " . $metrics['application']['response_time_avg'];

        // Database metrics
        $output[] = "# HELP zenamanage_db_connections Database connections";
        $output[] = "# TYPE zenamanage_db_connections gauge";
        $output[] = "zenamanage_db_connections " . $metrics['database']['connection_count'];

        $output[] = "# HELP zenamanage_slow_queries Slow queries count";
        $output[] = "# TYPE zenamanage_slow_queries counter";
        $output[] = "zenamanage_slow_queries " . $metrics['database']['slow_queries'];

        // System metrics
        $output[] = "# HELP zenamanage_cpu_usage CPU usage percentage";
        $output[] = "# TYPE zenamanage_cpu_usage gauge";
        $output[] = "zenamanage_cpu_usage " . $metrics['system']['cpu_usage'];

        $output[] = "# HELP zenamanage_memory_usage Memory usage percentage";
        $output[] = "# TYPE zenamanage_memory_usage gauge";
        $output[] = "zenamanage_memory_usage " . $metrics['system']['memory_usage']['percentage'];

        return implode("\n", $output);
    }
}
