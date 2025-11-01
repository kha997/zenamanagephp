<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PerformanceMetricsService
{
    /**
     * Collect comprehensive performance metrics
     */
    public function collectMetrics(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'system' => $this->getSystemMetrics(),
            'database' => $this->getDatabaseMetrics(),
            'cache' => $this->getCacheMetrics(),
            'memory' => $this->getMemoryMetrics(),
            'requests' => $this->getRequestMetrics(),
            'errors' => $this->getErrorMetrics(),
        ];
    }
    
    /**
     * Get system performance metrics
     */
    private function getSystemMetrics(): array
    {
        return [
            'load_average' => $this->getLoadAverage(),
            'cpu_usage' => $this->getCpuUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'uptime' => $this->getUptime(),
        ];
    }
    
    /**
     * Get database performance metrics
     */
    private function getDatabaseMetrics(): array
    {
        $queries = DB::getQueryLog();
        
        return [
            'total_queries' => count($queries),
            'total_time' => array_sum(array_column($queries, 'time')),
            'avg_query_time' => count($queries) > 0 ? array_sum(array_column($queries, 'time')) / count($queries) : 0,
            'slow_queries' => count(array_filter($queries, fn($q) => $q['time'] > 100)),
            'connection_count' => $this->getConnectionCount(),
            'query_efficiency' => $this->calculateQueryEfficiency($queries),
        ];
    }
    
    /**
     * Get cache performance metrics
     */
    private function getCacheMetrics(): array
    {
        $cacheService = new CacheManagementService();
        $stats = $cacheService->getCacheStats();
        
        return [
            'driver' => $stats['driver'],
            'hit_rate' => $stats['hit_rate'],
            'keys_count' => $stats['keys_count'],
            'memory_usage' => $stats['memory_usage'],
            'redis_info' => $stats['redis_info'] ?? null,
        ];
    }
    
    /**
     * Get memory usage metrics
     */
    private function getMemoryMetrics(): array
    {
        $memory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        
        return [
            'current' => $this->formatBytes($memory),
            'peak' => $this->formatBytes($peakMemory),
            'current_bytes' => $memory,
            'peak_bytes' => $peakMemory,
            'limit' => ini_get('memory_limit'),
            'usage_percentage' => $this->getMemoryUsagePercentage($memory),
        ];
    }
    
    /**
     * Get request performance metrics
     */
    private function getRequestMetrics(): array
    {
        // This would typically be collected from middleware
        return [
            'total_requests' => $this->getTotalRequests(),
            'avg_response_time' => $this->getAverageResponseTime(),
            'requests_per_minute' => $this->getRequestsPerMinute(),
            'slow_requests' => $this->getSlowRequestsCount(),
            'status_codes' => $this->getStatusCodesDistribution(),
        ];
    }
    
    /**
     * Get error metrics
     */
    private function getErrorMetrics(): array
    {
        return [
            'total_errors' => $this->getTotalErrors(),
            'error_rate' => $this->getErrorRate(),
            'error_types' => $this->getErrorTypes(),
            'recent_errors' => $this->getRecentErrors(),
        ];
    }
    
    /**
     * Get load average
     */
    private function getLoadAverage(): ?array
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return [
                '1min' => $load[0],
                '5min' => $load[1],
                '15min' => $load[2],
            ];
        }
        
        return null;
    }
    
    /**
     * Get CPU usage
     */
    private function getCpuUsage(): ?float
    {
        // This is a simplified implementation
        // In production, you'd use more sophisticated methods
        return null;
    }
    
    /**
     * Get disk usage
     */
    private function getDiskUsage(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $used = $total - $free;
        
        return [
            'total' => $this->formatBytes($total),
            'used' => $this->formatBytes($used),
            'free' => $this->formatBytes($free),
            'usage_percentage' => round(($used / $total) * 100, 2),
        ];
    }
    
    /**
     * Get system uptime
     */
    private function getUptime(): ?int
    {
        if (file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            return (int) explode(' ', $uptime)[0];
        }
        
        return null;
    }
    
    /**
     * Get database connection count
     */
    private function getConnectionCount(): int
    {
        try {
            $result = DB::select('SHOW STATUS LIKE "Threads_connected"');
            return $result[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
    
    /**
     * Calculate query efficiency score
     */
    private function calculateQueryEfficiency(array $queries): float
    {
        if (empty($queries)) {
            return 100.0;
        }
        
        $score = 100.0;
        
        // Deduct points for slow queries
        $slowQueries = array_filter($queries, fn($q) => $q['time'] > 100);
        $score -= count($slowQueries) * 5;
        
        // Deduct points for too many queries
        if (count($queries) > 20) {
            $score -= (count($queries) - 20) * 2;
        }
        
        return max(0, $score);
    }
    
    /**
     * Get memory usage percentage
     */
    private function getMemoryUsagePercentage(int $currentMemory): float
    {
        $limit = $this->parseMemoryLimit(ini_get('memory_limit'));
        
        if ($limit === -1) { // Unlimited
            return 0;
        }
        
        return round(($currentMemory / $limit) * 100, 2);
    }
    
    /**
     * Parse memory limit string to bytes
     */
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
     * Get total requests (simplified)
     */
    private function getTotalRequests(): int
    {
        // This would typically come from a metrics store
        return Cache::get('metrics:total_requests', 0);
    }
    
    /**
     * Get average response time (simplified)
     */
    private function getAverageResponseTime(): float
    {
        // This would typically come from a metrics store
        return Cache::get('metrics:avg_response_time', 0);
    }
    
    /**
     * Get requests per minute (simplified)
     */
    private function getRequestsPerMinute(): int
    {
        // This would typically come from a metrics store
        return Cache::get('metrics:requests_per_minute', 0);
    }
    
    /**
     * Get slow requests count (simplified)
     */
    private function getSlowRequestsCount(): int
    {
        // This would typically come from a metrics store
        return Cache::get('metrics:slow_requests', 0);
    }
    
    /**
     * Get status codes distribution (simplified)
     */
    private function getStatusCodesDistribution(): array
    {
        // This would typically come from a metrics store
        return Cache::get('metrics:status_codes', [
            '200' => 0,
            '400' => 0,
            '401' => 0,
            '403' => 0,
            '404' => 0,
            '500' => 0,
        ]);
    }
    
    /**
     * Get total errors (simplified)
     */
    private function getTotalErrors(): int
    {
        // This would typically come from a metrics store
        return Cache::get('metrics:total_errors', 0);
    }
    
    /**
     * Get error rate (simplified)
     */
    private function getErrorRate(): float
    {
        $totalRequests = $this->getTotalRequests();
        $totalErrors = $this->getTotalErrors();
        
        if ($totalRequests === 0) {
            return 0;
        }
        
        return round(($totalErrors / $totalRequests) * 100, 2);
    }
    
    /**
     * Get error types (simplified)
     */
    private function getErrorTypes(): array
    {
        // This would typically come from a metrics store
        return Cache::get('metrics:error_types', [
            'validation' => 0,
            'authentication' => 0,
            'authorization' => 0,
            'not_found' => 0,
            'server_error' => 0,
        ]);
    }
    
    /**
     * Get recent errors (simplified)
     */
    private function getRecentErrors(): array
    {
        // This would typically come from a metrics store
        return Cache::get('metrics:recent_errors', []);
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int|float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Store metrics for historical analysis
     */
    public function storeMetrics(array $metrics): void
    {
        $timestamp = now()->format('Y-m-d-H-i');
        $key = "metrics:historical:{$timestamp}";
        
        Cache::put($key, $metrics, 86400); // Store for 24 hours
        
        // Also store aggregated metrics
        $this->updateAggregatedMetrics($metrics);
    }
    
    /**
     * Update aggregated metrics
     */
    private function updateAggregatedMetrics(array $metrics): void
    {
        $aggregated = Cache::get('metrics:aggregated', []);
        
        // Update counters
        $aggregated['total_requests'] = ($aggregated['total_requests'] ?? 0) + 1;
        $aggregated['total_queries'] = ($aggregated['total_queries'] ?? 0) + $metrics['database']['total_queries'];
        $aggregated['total_errors'] = ($aggregated['total_errors'] ?? 0) + $metrics['errors']['total_errors'];
        
        // Update averages
        $aggregated['avg_response_time'] = $this->updateAverage(
            $aggregated['avg_response_time'] ?? 0,
            $metrics['requests']['avg_response_time'] ?? 0,
            $aggregated['total_requests']
        );
        
        Cache::put('metrics:aggregated', $aggregated, 3600); // Store for 1 hour
    }
    
    /**
     * Update running average
     */
    private function updateAverage(float $current, float $new, int $count): float
    {
        return (($current * ($count - 1)) + $new) / $count;
    }
    
    /**
     * Get performance alerts
     */
    public function getPerformanceAlerts(): array
    {
        $alerts = [];
        $metrics = $this->collectMetrics();
        
        // Check for slow queries
        if ($metrics['database']['slow_queries'] > 5) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'High number of slow queries detected',
                'value' => $metrics['database']['slow_queries'],
                'threshold' => 5,
            ];
        }
        
        // Check for high memory usage
        if ($metrics['memory']['usage_percentage'] > 80) {
            $alerts[] = [
                'type' => 'critical',
                'message' => 'High memory usage detected',
                'value' => $metrics['memory']['usage_percentage'],
                'threshold' => 80,
            ];
        }
        
        // Check for high error rate
        if ($metrics['errors']['error_rate'] > 5) {
            $alerts[] = [
                'type' => 'warning',
                'message' => 'High error rate detected',
                'value' => $metrics['errors']['error_rate'],
                'threshold' => 5,
            ];
        }
        
        return $alerts;
    }
}