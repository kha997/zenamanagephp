<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class PerformanceMonitoringService
{
    /**
     * Get comprehensive performance metrics
     */
    public function getAllMetrics(): array
    {
        return [
            'memory' => $this->getMemoryMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'requests' => $this->getRequestMetrics(),
            'errors' => $this->getErrorMetrics(),
            'system' => $this->getSystemMetrics(),
        ];
    }

    /**
     * Get memory usage metrics
     */
    public function getMemoryMetrics(): array
    {
        return [
            'current_usage' => memory_get_usage(true),
            'peak_usage' => memory_get_peak_usage(true),
            'current_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_usage_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'limit' => ini_get('memory_limit'),
            'usage_percentage' => $this->getMemoryUsagePercentage(),
        ];
    }

    /**
     * Get database performance metrics
     */
    public function getDatabaseMetrics(): array
    {
        $queries = DB::getQueryLog();
        
        return [
            'total_queries' => count($queries),
            'total_time' => array_sum(array_column($queries, 'time')),
            'average_time' => count($queries) > 0 ? array_sum(array_column($queries, 'time')) / count($queries) : 0,
            'slow_queries' => array_filter($queries, fn($q) => $q['time'] > 100),
            'connection_count' => count(DB::getConnections()),
        ];
    }

    /**
     * Get cache performance metrics
     */
    public function getCacheMetrics(): array
    {
        try {
            $cacheService = new CacheOptimizationService();
            return $cacheService->getCacheMetrics();
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get request performance metrics
     */
    public function getRequestMetrics(): array
    {
        return [
            'current_time' => microtime(true),
            'execution_time' => microtime(true) - (defined('LARAVEL_START') ? LARAVEL_START : microtime(true)),
            'request_count' => $this->getRequestCount(),
            'average_response_time' => $this->getAverageResponseTime(),
        ];
    }

    /**
     * Get error metrics
     */
    public function getErrorMetrics(): array
    {
        return [
            'error_count' => $this->getErrorCount(),
            'error_rate' => $this->getErrorRate(),
            'last_error' => $this->getLastError(),
        ];
    }

    /**
     * Get system metrics
     */
    public function getSystemMetrics(): array
    {
        return [
            'cpu_usage' => $this->getCpuUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'load_average' => $this->getLoadAverage(),
            'uptime' => $this->getUptime(),
        ];
    }

    /**
     * Get performance alerts
     */
    public function getPerformanceAlerts(): array
    {
        $alerts = [];
        $metrics = $this->getAllMetrics();

        // Memory alerts
        if ($metrics['memory']['usage_percentage'] > 80) {
            $alerts[] = [
                'type' => 'memory',
                'severity' => 'warning',
                'message' => 'High memory usage detected',
                'value' => $metrics['memory']['usage_percentage'] . '%',
            ];
        }

        if ($metrics['memory']['usage_percentage'] > 90) {
            $alerts[] = [
                'type' => 'memory',
                'severity' => 'critical',
                'message' => 'Critical memory usage detected',
                'value' => $metrics['memory']['usage_percentage'] . '%',
            ];
        }

        // Database alerts
        if ($metrics['database']['average_time'] > 100) {
            $alerts[] = [
                'type' => 'database',
                'severity' => 'warning',
                'message' => 'Slow database queries detected',
                'value' => round($metrics['database']['average_time'], 2) . 'ms',
            ];
        }

        // Request alerts
        if ($metrics['requests']['average_response_time'] > 1000) {
            $alerts[] = [
                'type' => 'request',
                'severity' => 'warning',
                'message' => 'Slow response times detected',
                'value' => round($metrics['requests']['average_response_time'], 2) . 'ms',
            ];
        }

        return $alerts;
    }

    /**
     * Get memory usage percentage
     */
    private function getMemoryUsagePercentage(): float
    {
        $current = memory_get_usage(true);
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        if ($limit === -1) {
            return 0; // No limit
        }
        
        return round(($current / $limit) * 100, 2);
    }

    /**
     * Parse memory limit string to bytes
     */
    private function parseMemoryLimit(string $limit): int
    {
        if ($limit === '-1') {
            return -1;
        }
        
        $unit = strtolower(substr($limit, -1));
        $value = (int) $limit;
        
        switch ($unit) {
            case 'g':
                return $value * 1024 * 1024 * 1024;
            case 'm':
                return $value * 1024 * 1024;
            case 'k':
                return $value * 1024;
            default:
                return $value;
        }
    }

    /**
     * Get request count (simplified)
     */
    private function getRequestCount(): int
    {
        $today = now()->toDateString();
        $key = "performance:requests:{$today}";
        return Cache::get($key, 0);
    }

    /**
     * Get average response time (simplified)
     */
    private function getAverageResponseTime(): float
    {
        $today = now()->toDateString();
        $key = "performance:response_time:{$today}";
        $data = Cache::get($key, ['sum' => 0, 'count' => 0]);
        
        return $data['count'] > 0 ? round($data['sum'] / $data['count'], 2) : 0;
    }

    /**
     * Get error count (simplified)
     */
    private function getErrorCount(): int
    {
        $today = now()->toDateString();
        $key = "performance:errors:{$today}";
        return Cache::get($key, 0);
    }

    /**
     * Get error rate (simplified)
     */
    private function getErrorRate(): float
    {
        $errors = $this->getErrorCount();
        $requests = $this->getRequestCount();
        
        return $requests > 0 ? round(($errors / $requests) * 100, 2) : 0;
    }

    /**
     * Get last error (simplified)
     */
    private function getLastError(): ?array
    {
        return Cache::get('performance:last_error');
    }

    /**
     * Get CPU usage (simplified)
     */
    private function getCpuUsage(): float
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $load = sys_getloadavg();
            return $load[0] ?? 0;
        }
        
        return 0;
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage(): array
    {
        $total = disk_total_space(storage_path());
        $free = disk_free_space(storage_path());
        $used = $total - $free;
        
        return [
            'total' => $total,
            'used' => $used,
            'free' => $free,
            'usage_percentage' => round(($used / $total) * 100, 2),
        ];
    }

    /**
     * Get load average (simplified)
     */
    private function getLoadAverage(): array
    {
        if (PHP_OS_FAMILY === 'Linux') {
            return sys_getloadavg() ?: [0, 0, 0];
        }
        
        return [0, 0, 0];
    }

    /**
     * Get uptime (simplified)
     */
    private function getUptime(): int
    {
        if (PHP_OS_FAMILY === 'Linux') {
            $uptime = file_get_contents('/proc/uptime');
            return (int) explode(' ', $uptime)[0];
        }
        
        return time() - (defined('LARAVEL_START') ? LARAVEL_START : time());
    }

    /**
     * Log performance metrics
     */
    public function logMetrics(): void
    {
        $metrics = $this->getAllMetrics();
        
        Log::info('Performance Metrics', [
            'timestamp' => now()->toISOString(),
            'metrics' => $metrics,
        ]);
    }

    /**
     * Get performance dashboard data
     */
    public function getDashboardData(): array
    {
        $metrics = $this->getAllMetrics();
        $alerts = $this->getPerformanceAlerts();
        
        return [
            'status' => empty($alerts) ? 'healthy' : 'degraded',
            'metrics' => $metrics,
            'alerts' => $alerts,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get performance trends (simplified)
     */
    public function getPerformanceTrends(): array
    {
        // This would typically store historical data
        return [
            'memory_trend' => 'stable',
            'response_time_trend' => 'stable',
            'error_rate_trend' => 'stable',
        ];
    }

    /**
     * Get performance recommendations
     */
    public function getPerformanceRecommendations(): array
    {
        $recommendations = [];
        $metrics = $this->getAllMetrics();

        // Memory recommendations
        if ($metrics['memory']['usage_percentage'] > 70) {
            $recommendations[] = [
                'type' => 'memory',
                'priority' => 'high',
                'message' => 'Consider increasing memory limit or optimizing memory usage',
            ];
        }

        // Database recommendations
        if ($metrics['database']['average_time'] > 50) {
            $recommendations[] = [
                'type' => 'database',
                'priority' => 'medium',
                'message' => 'Consider optimizing database queries or adding indexes',
            ];
        }

        // Cache recommendations
        if (isset($metrics['cache']['redis']['hit_rate']) && $metrics['cache']['redis']['hit_rate'] < 80) {
            $recommendations[] = [
                'type' => 'cache',
                'priority' => 'medium',
                'message' => 'Consider optimizing cache strategy or increasing cache TTL',
            ];
        }

        return $recommendations;
    }
}