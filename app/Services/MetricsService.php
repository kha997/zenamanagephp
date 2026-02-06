<?php declare(strict_types=1);

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class MetricsService
{
    /**
     * Collect application metrics
     */
    public function collectMetrics(): array
    {
        return [
            'timestamp' => Carbon::now()->toISOString(),
            'application' => $this->getApplicationMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'queue' => $this->getQueueMetrics(),
            'storage' => $this->getStorageMetrics(),
            'business' => $this->getBusinessMetrics(),
        ];
    }

    /**
     * Get application metrics
     */
    private function getApplicationMetrics(): array
    {
        return [
            'uptime_seconds' => time() - filemtime(base_path('bootstrap/app.php')),
            'memory_usage_bytes' => memory_get_usage(true),
            'memory_peak_bytes' => memory_get_peak_usage(true),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'environment' => app()->environment(),
        ];
    }

    /**
     * Get database metrics
     */
    private function getDatabaseMetrics(): array
    {
        try {
            $startTime = microtime(true);
            
            // Basic connection test
            DB::connection()->getPdo();
            
            // Get database size
            $size = DB::select("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB'
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ")[0]->{'DB Size in MB'} ?? 0;

            // Get connection count
            $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0;
            
            // Get slow queries
            $slowQueries = DB::select("SHOW STATUS LIKE 'Slow_queries'")[0]->Value ?? 0;
            
            // Get query count
            $queries = DB::select("SHOW STATUS LIKE 'Queries'")[0]->Value ?? 0;

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'database_size_mb' => $size,
                'active_connections' => (int) $connections,
                'slow_queries' => (int) $slowQueries,
                'total_queries' => (int) $queries,
            ];
        } catch (\Exception $e) {
            Log::error('Database metrics collection failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get cache metrics
     */
    private function getCacheMetrics(): array
    {
        try {
            $startTime = microtime(true);
            
            // Test Redis connection
            Redis::ping();
            
            // Get Redis info
            $info = Redis::info();
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'memory_used_bytes' => $info['used_memory'] ?? 0,
                'memory_peak_bytes' => $info['used_memory_peak'] ?? 0,
                'connected_clients' => $info['connected_clients'] ?? 0,
                'total_commands_processed' => $info['total_commands_processed'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
            ];
        } catch (\Exception $e) {
            Log::error('Cache metrics collection failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get queue metrics
     */
    private function getQueueMetrics(): array
    {
        try {
            $startTime = microtime(true);
            
            $queue = app('queue');
            $connection = $queue->connection();
            
            // Get queue sizes
            $defaultSize = $connection->size();
            $failedSize = $connection->size('failed');
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'default_queue_size' => $defaultSize,
                'failed_queue_size' => $failedSize,
                'total_pending_jobs' => $defaultSize + $failedSize,
            ];
        } catch (\Exception $e) {
            Log::error('Queue metrics collection failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get storage metrics
     */
    private function getStorageMetrics(): array
    {
        try {
            $startTime = microtime(true);
            
            $totalSpace = disk_total_space(storage_path());
            $freeSpace = disk_free_space(storage_path());
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercentage = round(($usedSpace / $totalSpace) * 100, 2);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'total_space_bytes' => $totalSpace,
                'free_space_bytes' => $freeSpace,
                'used_space_bytes' => $usedSpace,
                'usage_percentage' => $usagePercentage,
            ];
        } catch (\Exception $e) {
            Log::error('Storage metrics collection failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get business metrics
     */
    private function getBusinessMetrics(): array
    {
        try {
            $startTime = microtime(true);
            
            // Get user metrics
            $totalUsers = DB::table('users')->count();
            $activeUsers = DB::table('users')
                ->where('last_login_at', '>=', Carbon::now()->subDays(30))
                ->count();
            
            // Get project metrics
            $totalProjects = DB::table('projects')->count();
            $activeProjects = DB::table('projects')
                ->where('status', 'active')
                ->count();
            
            // Get task metrics
            $totalTasks = DB::table('tasks')->count();
            $completedTasks = DB::table('tasks')
                ->where('status', 'completed')
                ->count();
            $pendingTasks = DB::table('tasks')
                ->where('status', 'pending')
                ->count();
            
            // Get document metrics
            $totalDocuments = DB::table('documents')->count();
            $totalDocumentSize = DB::table('documents')
                ->sum('file_size');

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'users' => [
                    'total' => $totalUsers,
                    'active_last_30_days' => $activeUsers,
                ],
                'projects' => [
                    'total' => $totalProjects,
                    'active' => $activeProjects,
                ],
                'tasks' => [
                    'total' => $totalTasks,
                    'completed' => $completedTasks,
                    'pending' => $pendingTasks,
                ],
                'documents' => [
                    'total' => $totalDocuments,
                    'total_size_bytes' => $totalDocumentSize,
                ],
            ];
        } catch (\Exception $e) {
            Log::error('Business metrics collection failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Export metrics in Prometheus format
     */
    public function exportPrometheusMetrics(): string
    {
        $metrics = $this->collectMetrics();
        $prometheusMetrics = [];

        // Application metrics
        $app = $metrics['application'];
        $prometheusMetrics[] = '# HELP app_uptime_seconds Application uptime in seconds';
        $prometheusMetrics[] = '# TYPE app_uptime_seconds counter';
        $prometheusMetrics[] = 'app_uptime_seconds ' . $app['uptime_seconds'];

        $prometheusMetrics[] = '# HELP app_memory_usage_bytes Application memory usage in bytes';
        $prometheusMetrics[] = '# TYPE app_memory_usage_bytes gauge';
        $prometheusMetrics[] = 'app_memory_usage_bytes ' . $app['memory_usage_bytes'];

        $prometheusMetrics[] = '# HELP app_memory_peak_bytes Application peak memory usage in bytes';
        $prometheusMetrics[] = '# TYPE app_memory_peak_bytes gauge';
        $prometheusMetrics[] = 'app_memory_peak_bytes ' . $app['memory_peak_bytes'];

        // Database metrics
        if ($metrics['database']['status'] === 'healthy') {
            $db = $metrics['database'];
            $prometheusMetrics[] = '# HELP app_database_size_mb Database size in MB';
            $prometheusMetrics[] = '# TYPE app_database_size_mb gauge';
            $prometheusMetrics[] = 'app_database_size_mb ' . $db['database_size_mb'];

            $prometheusMetrics[] = '# HELP app_database_connections Database active connections';
            $prometheusMetrics[] = '# TYPE app_database_connections gauge';
            $prometheusMetrics[] = 'app_database_connections ' . $db['active_connections'];

            $prometheusMetrics[] = '# HELP app_database_slow_queries Database slow queries';
            $prometheusMetrics[] = '# TYPE app_database_slow_queries counter';
            $prometheusMetrics[] = 'app_database_slow_queries ' . $db['slow_queries'];
        }

        // Cache metrics
        if ($metrics['cache']['status'] === 'healthy') {
            $cache = $metrics['cache'];
            $prometheusMetrics[] = '# HELP app_redis_memory_used_bytes Redis memory used in bytes';
            $prometheusMetrics[] = '# TYPE app_redis_memory_used_bytes gauge';
            $prometheusMetrics[] = 'app_redis_memory_used_bytes ' . $cache['memory_used_bytes'];

            $prometheusMetrics[] = '# HELP app_redis_connected_clients Redis connected clients';
            $prometheusMetrics[] = '# TYPE app_redis_connected_clients gauge';
            $prometheusMetrics[] = 'app_redis_connected_clients ' . $cache['connected_clients'];
        }

        // Storage metrics
        if ($metrics['storage']['status'] === 'healthy') {
            $storage = $metrics['storage'];
            $prometheusMetrics[] = '# HELP app_storage_total_bytes Total storage space in bytes';
            $prometheusMetrics[] = '# TYPE app_storage_total_bytes gauge';
            $prometheusMetrics[] = 'app_storage_total_bytes ' . $storage['total_space_bytes'];

            $prometheusMetrics[] = '# HELP app_storage_free_bytes Free storage space in bytes';
            $prometheusMetrics[] = '# TYPE app_storage_free_bytes gauge';
            $prometheusMetrics[] = 'app_storage_free_bytes ' . $storage['free_space_bytes'];

            $prometheusMetrics[] = '# HELP app_storage_used_bytes Used storage space in bytes';
            $prometheusMetrics[] = '# TYPE app_storage_used_bytes gauge';
            $prometheusMetrics[] = 'app_storage_used_bytes ' . $storage['used_space_bytes'];
        }

        // Business metrics
        if ($metrics['business']['status'] === 'healthy') {
            $business = $metrics['business'];
            $prometheusMetrics[] = '# HELP app_users_total Total number of users';
            $prometheusMetrics[] = '# TYPE app_users_total gauge';
            $prometheusMetrics[] = 'app_users_total ' . $business['users']['total'];

            $prometheusMetrics[] = '# HELP app_users_active_total Active users in last 30 days';
            $prometheusMetrics[] = '# TYPE app_users_active_total gauge';
            $prometheusMetrics[] = 'app_users_active_total ' . $business['users']['active_last_30_days'];

            $prometheusMetrics[] = '# HELP app_projects_total Total number of projects';
            $prometheusMetrics[] = '# TYPE app_projects_total gauge';
            $prometheusMetrics[] = 'app_projects_total ' . $business['projects']['total'];

            $prometheusMetrics[] = '# HELP app_tasks_total Total number of tasks';
            $prometheusMetrics[] = '# TYPE app_tasks_total gauge';
            $prometheusMetrics[] = 'app_tasks_total ' . $business['tasks']['total'];

            $prometheusMetrics[] = '# HELP app_tasks_completed_total Completed tasks';
            $prometheusMetrics[] = '# TYPE app_tasks_completed_total gauge';
            $prometheusMetrics[] = 'app_tasks_completed_total ' . $business['tasks']['completed'];

            $prometheusMetrics[] = '# HELP app_documents_total Total number of documents';
            $prometheusMetrics[] = '# TYPE app_documents_total gauge';
            $prometheusMetrics[] = 'app_documents_total ' . $business['documents']['total'];

            $prometheusMetrics[] = '# HELP app_documents_size_bytes Total document size in bytes';
            $prometheusMetrics[] = '# TYPE app_documents_size_bytes gauge';
            $prometheusMetrics[] = 'app_documents_size_bytes ' . $business['documents']['total_size_bytes'];
        }

        return implode("\n", $prometheusMetrics) . "\n";
    }

    /**
     * Get metrics summary for dashboard
     */
    public function getMetricsSummary(): array
    {
        $metrics = $this->collectMetrics();
        
        return [
            'overall_status' => $this->determineOverallStatus($metrics),
            'timestamp' => $metrics['timestamp'],
            'application' => [
                'uptime_hours' => round($metrics['application']['uptime_seconds'] / 3600, 2),
                'memory_usage_mb' => round($metrics['application']['memory_usage_bytes'] / 1024 / 1024, 2),
            ],
            'database' => [
                'status' => $metrics['database']['status'],
                'size_mb' => $metrics['database']['database_size_mb'] ?? 0,
                'connections' => $metrics['database']['active_connections'] ?? 0,
            ],
            'cache' => [
                'status' => $metrics['cache']['status'],
                'memory_mb' => round(($metrics['cache']['memory_used_bytes'] ?? 0) / 1024 / 1024, 2),
                'clients' => $metrics['cache']['connected_clients'] ?? 0,
            ],
            'storage' => [
                'status' => $metrics['storage']['status'],
                'usage_percentage' => $metrics['storage']['usage_percentage'] ?? 0,
                'free_gb' => round(($metrics['storage']['free_space_bytes'] ?? 0) / 1024 / 1024 / 1024, 2),
            ],
            'business' => [
                'users' => $metrics['business']['users']['total'] ?? 0,
                'projects' => $metrics['business']['projects']['total'] ?? 0,
                'tasks' => $metrics['business']['tasks']['total'] ?? 0,
                'documents' => $metrics['business']['documents']['total'] ?? 0,
            ],
        ];
    }

    /**
     * Determine overall system status
     */
    private function determineOverallStatus(array $metrics): string
    {
        $criticalServices = ['database', 'cache', 'storage'];
        $unhealthyCritical = 0;
        
        foreach ($criticalServices as $service) {
            if ($metrics[$service]['status'] !== 'healthy') {
                $unhealthyCritical++;
            }
        }
        
        if ($unhealthyCritical > 0) {
            return 'unhealthy';
        }
        
        $unhealthyServices = 0;
        foreach ($metrics as $key => $metric) {
            if (is_array($metric) && isset($metric['status']) && $metric['status'] !== 'healthy') {
                $unhealthyServices++;
            }
        }
        
        if ($unhealthyServices > 0) {
            return 'degraded';
        }
        
        return 'healthy';
    }

    public function getProjectMetrics(string $projectId): array
    {
        return [
            'project_id' => $projectId,
            'progress' => 0,
            'tasks' => [
                'total' => 0,
                'completed' => 0,
                'pending' => 0,
            ],
            'status' => 'planning',
        ];
    }
}
