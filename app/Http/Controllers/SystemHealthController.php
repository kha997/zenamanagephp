<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use App\Models\PerformanceMetric;
use App\Models\SystemLog;
use Carbon\Carbon;

class SystemHealthController extends Controller
{
    /**
     * Get comprehensive system health status
     */
    public function index()
    {
        $health = [
            'overall_status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'services' => [],
            'metrics' => [],
            'alerts' => [],
            'recommendations' => []
        ];

        // Check database health
        $health['services']['database'] = $this->checkDatabaseHealth();
        
        // Check Redis health
        $health['services']['redis'] = $this->checkRedisHealth();
        
        // Check storage health
        $health['services']['storage'] = $this->checkStorageHealth();
        
        // Check queue health
        $health['services']['queue'] = $this->checkQueueHealth();
        
        // Check WebSocket health
        $health['services']['websocket'] = $this->checkWebSocketHealth();

        // Get system metrics
        $health['metrics'] = $this->getSystemMetrics();

        // Check for alerts
        $health['alerts'] = $this->checkAlerts();

        // Generate recommendations
        $health['recommendations'] = $this->generateRecommendations($health);

        // Determine overall status
        $health['overall_status'] = $this->determineOverallStatus($health);

        return response()->json($health);
    }

    /**
     * Check database health
     */
    private function checkDatabaseHealth()
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            // Get connection count
            $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0;
            
            // Get database size
            $size = DB::select("
                SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ")[0]->size_mb ?? 0;

            // Check for slow queries
            $slowQueries = DB::select("SHOW STATUS LIKE 'Slow_queries'")[0]->Value ?? 0;

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'connections' => (int) $connections,
                'size_mb' => $size,
                'slow_queries' => (int) $slowQueries,
                'last_check' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString()
            ];
        }
    }

    /**
     * Check Redis health
     */
    private function checkRedisHealth()
    {
        try {
            $start = microtime(true);
            Cache::store('redis')->put('health_check', 'ok', 10);
            $responseTime = round((microtime(true) - $start) * 1000, 2);

            // Get Redis info
            $redis = Cache::store('redis')->getRedis();
            $info = $redis->info();

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'memory_used' => $info['used_memory_human'] ?? '0B',
                'connected_clients' => $info['connected_clients'] ?? 0,
                'keyspace_hits' => $info['keyspace_hits'] ?? 0,
                'keyspace_misses' => $info['keyspace_misses'] ?? 0,
                'last_check' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString()
            ];
        }
    }

    /**
     * Check storage health
     */
    private function checkStorageHealth()
    {
        try {
            $totalSpace = disk_total_space('/');
            $freeSpace = disk_free_space('/');
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercentage = round(($usedSpace / $totalSpace) * 100, 2);

            // Check storage directories
            $directories = [
                'storage/logs' => storage_path('logs'),
                'storage/app' => storage_path('app'),
                'storage/framework' => storage_path('framework'),
                'public/uploads' => public_path('uploads')
            ];

            $directoryStatus = [];
            foreach ($directories as $name => $path) {
                $directoryStatus[$name] = [
                    'exists' => is_dir($path),
                    'writable' => is_writable($path),
                    'size' => $this->getDirectorySize($path)
                ];
            }

            return [
                'status' => $usagePercentage > 90 ? 'critical' : ($usagePercentage > 80 ? 'warning' : 'healthy'),
                'total_space' => $this->formatBytes($totalSpace),
                'used_space' => $this->formatBytes($usedSpace),
                'free_space' => $this->formatBytes($freeSpace),
                'usage_percentage' => $usagePercentage,
                'directories' => $directoryStatus,
                'last_check' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString()
            ];
        }
    }

    /**
     * Check queue health
     */
    private function checkQueueHealth()
    {
        try {
            // Check if queue is processing
            $pendingJobs = DB::table('jobs')->count();
            $failedJobs = DB::table('failed_jobs')->count();

            return [
                'status' => 'healthy',
                'pending_jobs' => $pendingJobs,
                'failed_jobs' => $failedJobs,
                'last_check' => now()->toISOString()
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString()
            ];
        }
    }

    /**
     * Check WebSocket health
     */
    private function checkWebSocketHealth()
    {
        try {
            // Check WebSocket server status
            $websocketPort = config('websocket.port', 6001);
            $connection = @fsockopen('localhost', $websocketPort, $errno, $errstr, 5);
            
            if ($connection) {
                fclose($connection);
                return [
                    'status' => 'healthy',
                    'port' => $websocketPort,
                    'last_check' => now()->toISOString()
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'error' => "Cannot connect to WebSocket server on port {$websocketPort}",
                    'last_check' => now()->toISOString()
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'last_check' => now()->toISOString()
            ];
        }
    }

    /**
     * Get system metrics
     */
    private function getSystemMetrics()
    {
        $metrics = [];

        // Memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        $memoryPercentage = $memoryLimitBytes > 0 ? ($memoryUsage / $memoryLimitBytes) * 100 : 0;

        $metrics['memory'] = [
            'usage_bytes' => $memoryUsage,
            'usage_formatted' => $this->formatBytes($memoryUsage),
            'limit_bytes' => $memoryLimitBytes,
            'limit_formatted' => $this->formatBytes($memoryLimitBytes),
            'percentage' => round($memoryPercentage, 2)
        ];

        // CPU usage (approximation)
        $loadAverage = sys_getloadavg();
        $metrics['cpu'] = [
            'load_average_1min' => $loadAverage[0] ?? 0,
            'load_average_5min' => $loadAverage[1] ?? 0,
            'load_average_15min' => $loadAverage[2] ?? 0
        ];

        // PHP version and extensions
        $metrics['php'] = [
            'version' => PHP_VERSION,
            'extensions' => get_loaded_extensions(),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];

        // Laravel application info
        $metrics['application'] = [
            'version' => app()->version(),
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_driver' => config('queue.default')
        ];

        return $metrics;
    }

    /**
     * Check for alerts
     */
    private function checkAlerts()
    {
        $alerts = [];

        // Check for recent errors
        $recentErrors = SystemLog::where('level', 'error')
            ->where('created_at', '>=', Carbon::now()->subHours(1))
            ->count();

        if ($recentErrors > 10) {
            $alerts[] = [
                'type' => 'error',
                'message' => "High error rate: {$recentErrors} errors in the last hour",
                'severity' => 'high'
            ];
        }

        // Check disk space
        $diskUsage = $this->checkStorageHealth();
        if ($diskUsage['usage_percentage'] > 90) {
            $alerts[] = [
                'type' => 'storage',
                'message' => "Critical disk usage: {$diskUsage['usage_percentage']}%",
                'severity' => 'critical'
            ];
        } elseif ($diskUsage['usage_percentage'] > 80) {
            $alerts[] = [
                'type' => 'storage',
                'message' => "High disk usage: {$diskUsage['usage_percentage']}%",
                'severity' => 'warning'
            ];
        }

        // Check memory usage
        $memoryUsage = $this->getSystemMetrics()['memory'];
        if ($memoryUsage['percentage'] > 90) {
            $alerts[] = [
                'type' => 'memory',
                'message' => "Critical memory usage: {$memoryUsage['percentage']}%",
                'severity' => 'critical'
            ];
        } elseif ($memoryUsage['percentage'] > 80) {
            $alerts[] = [
                'type' => 'memory',
                'message' => "High memory usage: {$memoryUsage['percentage']}%",
                'severity' => 'warning'
            ];
        }

        return $alerts;
    }

    /**
     * Generate recommendations
     */
    private function generateRecommendations($health)
    {
        $recommendations = [];

        // Database recommendations
        if (isset($health['services']['database']['slow_queries']) && $health['services']['database']['slow_queries'] > 10) {
            $recommendations[] = [
                'category' => 'database',
                'message' => 'Consider optimizing slow queries',
                'priority' => 'medium'
            ];
        }

        // Storage recommendations
        if (isset($health['services']['storage']['usage_percentage']) && $health['services']['storage']['usage_percentage'] > 70) {
            $recommendations[] = [
                'category' => 'storage',
                'message' => 'Consider cleaning up old files and logs',
                'priority' => 'high'
            ];
        }

        // Memory recommendations
        if (isset($health['metrics']['memory']['percentage']) && $health['metrics']['memory']['percentage'] > 80) {
            $recommendations[] = [
                'category' => 'performance',
                'message' => 'Consider increasing memory limit or optimizing memory usage',
                'priority' => 'high'
            ];
        }

        return $recommendations;
    }

    /**
     * Determine overall system status
     */
    private function determineOverallStatus($health)
    {
        $criticalIssues = 0;
        $warningIssues = 0;

        // Check service statuses
        foreach ($health['services'] as $service) {
            if ($service['status'] === 'unhealthy') {
                $criticalIssues++;
            } elseif ($service['status'] === 'warning') {
                $warningIssues++;
            }
        }

        // Check alerts
        foreach ($health['alerts'] as $alert) {
            if ($alert['severity'] === 'critical') {
                $criticalIssues++;
            } elseif ($alert['severity'] === 'warning') {
                $warningIssues++;
            }
        }

        if ($criticalIssues > 0) {
            return 'critical';
        } elseif ($warningIssues > 0) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }

    /**
     * Get directory size
     */
    private function getDirectorySize($path)
    {
        if (!is_dir($path)) {
            return 0;
        }

        $size = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $this->formatBytes($size);
    }

    /**
     * Convert memory limit string to bytes
     */
    private function convertToBytes($val)
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;

        switch ($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }

        return $val;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    /**
     * Get performance metrics for dashboard
     */
    public function getPerformanceMetrics()
    {
        $metrics = PerformanceMetric::where('created_at', '>=', Carbon::now()->subDays(7))
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('metric_name');

        $formattedMetrics = [];
        foreach ($metrics as $name => $values) {
            $formattedMetrics[$name] = [
                'current' => $values->first()->metric_value,
                'average' => $values->avg('metric_value'),
                'max' => $values->max('metric_value'),
                'min' => $values->min('metric_value'),
                'trend' => $this->calculateTrend($values->take(10)->pluck('metric_value')->toArray()),
                'unit' => $values->first()->metric_unit,
                'category' => $values->first()->category
            ];
        }

        return response()->json($formattedMetrics);
    }

    /**
     * Calculate trend for metrics
     */
    private function calculateTrend($values)
    {
        if (count($values) < 2) {
            return 'stable';
        }

        $first = $values[count($values) - 1];
        $last = $values[0];

        if ($last > $first * 1.1) {
            return 'increasing';
        } elseif ($last < $first * 0.9) {
            return 'decreasing';
        }

        return 'stable';
    }
}
