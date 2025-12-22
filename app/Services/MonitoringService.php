<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class MonitoringService
{
    /**
     * Get API performance metrics
     */
    public function getApiMetrics(): array
    {
        try {
            $metrics = Cache::remember('api_metrics', 60, function () {
                return [
                    'avg_response_time' => $this->getAverageResponseTime(),
                    'p95_response_time' => $this->getP95ResponseTime(),
                    'error_rate' => $this->getErrorRate(),
                    'requests_per_minute' => $this->getRequestsPerMinute(),
                    'total_requests' => $this->getTotalRequests(),
                ];
            });

            return $metrics;
        } catch (\Exception $e) {
            Log::error('Failed to get API metrics', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get database performance metrics
     */
    public function getDatabaseMetrics(): array
    {
        try {
            $metrics = Cache::remember('database_metrics', 60, function () {
                return [
                    'connection_count' => $this->getConnectionCount(),
                    'slow_queries' => $this->getSlowQueries(),
                    'table_sizes' => $this->getTableSizes(),
                    'cache_hit_ratio' => $this->getCacheHitRatio(),
                ];
            });

            return $metrics;
        } catch (\Exception $e) {
            Log::error('Failed to get database metrics', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get queue metrics
     */
    public function getQueueMetrics(): array
    {
        try {
            $metrics = Cache::remember('queue_metrics', 30, function () {
                return [
                    'pending_jobs' => $this->getPendingJobs(),
                    'failed_jobs' => $this->getFailedJobs(),
                    'processed_jobs' => $this->getProcessedJobs(),
                    'queue_size' => $this->getQueueSize(),
                ];
            });

            return $metrics;
        } catch (\Exception $e) {
            Log::error('Failed to get queue metrics', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get system health status
     */
    public function getSystemHealth(): array
    {
        try {
            return [
                'status' => $this->getOverallStatus(),
                'timestamp' => now()->toISOString(),
                'uptime' => $this->getUptime(),
                'memory_usage' => $this->getMemoryUsage(),
                'disk_usage' => $this->getDiskUsage(),
                'api_metrics' => $this->getApiMetrics(),
                'database_metrics' => $this->getDatabaseMetrics(),
                'queue_metrics' => $this->getQueueMetrics(),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to get system health', [
                'error' => $e->getMessage()
            ]);
            return [
                'status' => 'error',
                'message' => 'Failed to get system health',
                'timestamp' => now()->toISOString(),
            ];
        }
    }

    /**
     * Log API request metrics
     */
    public function logApiRequest(string $method, string $path, float $responseTime, int $statusCode): void
    {
        try {
            $logData = [
                'timestamp' => now()->toISOString(),
                'method' => $method,
                'path' => $path,
                'response_time' => $responseTime,
                'status_code' => $statusCode,
                'tenant_id' => Auth::user()?->tenant_id,
                'user_id' => Auth::id(),
                'request_id' => request()->header('X-Request-Id'),
            ];

            Log::info('API Request', $logData);

            // Store metrics in cache for real-time monitoring
            $this->updateApiMetrics($responseTime, $statusCode);

        } catch (\Exception $e) {
            Log::error('Failed to log API request', [
                'error' => $e->getMessage(),
                'method' => $method,
                'path' => $path,
            ]);
        }
    }

    /**
     * Log page load metrics
     */
    public function logPageLoad(string $route, float $loadTime, int $statusCode): void
    {
        try {
            $logData = [
                'timestamp' => now()->toISOString(),
                'route' => $route,
                'load_time' => $loadTime,
                'status_code' => $statusCode,
                'tenant_id' => Auth::user()?->tenant_id,
                'user_id' => Auth::id(),
                'request_id' => request()->header('X-Request-Id'),
            ];

            Log::info('Page Load', $logData);

        } catch (\Exception $e) {
            Log::error('Failed to log page load', [
                'error' => $e->getMessage(),
                'route' => $route,
            ]);
        }
    }

    /**
     * Get average response time
     */
    private function getAverageResponseTime(): float
    {
        // This would typically come from a metrics store like Redis or InfluxDB
        // For now, return a mock value
        return 150.5;
    }

    /**
     * Get P95 response time
     */
    private function getP95ResponseTime(): float
    {
        // This would typically come from a metrics store
        return 300.0;
    }

    /**
     * Get error rate
     */
    private function getErrorRate(): float
    {
        // This would typically come from a metrics store
        return 0.02; // 2%
    }

    /**
     * Get requests per minute
     */
    private function getRequestsPerMinute(): int
    {
        // This would typically come from a metrics store
        return 45;
    }

    /**
     * Get total requests
     */
    private function getTotalRequests(): int
    {
        // This would typically come from a metrics store
        return 1250;
    }

    /**
     * Get connection count
     */
    private function getConnectionCount(): int
    {
        try {
            return DB::select('SHOW STATUS LIKE "Threads_connected"')[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get slow queries
     */
    private function getSlowQueries(): int
    {
        try {
            return DB::select('SHOW STATUS LIKE "Slow_queries"')[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get table sizes
     */
    private function getTableSizes(): array
    {
        try {
            $tables = ['users', 'tenants', 'projects', 'tasks', 'clients', 'quotes', 'notifications'];
            $sizes = [];

            foreach ($tables as $table) {
                $result = DB::select("
                    SELECT 
                        ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
                    FROM information_schema.TABLES 
                    WHERE table_schema = DATABASE() 
                    AND table_name = ?
                ", [$table]);

                $sizes[$table] = $result[0]->size_mb ?? 0;
            }

            return $sizes;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get cache hit ratio
     */
    private function getCacheHitRatio(): float
    {
        try {
            $hits = Cache::get('cache_hits', 0);
            $misses = Cache::get('cache_misses', 0);
            $total = $hits + $misses;

            return $total > 0 ? ($hits / $total) * 100 : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get pending jobs
     */
    private function getPendingJobs(): int
    {
        try {
            return Queue::size();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get failed jobs
     */
    private function getFailedJobs(): int
    {
        try {
            return DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get processed jobs
     */
    private function getProcessedJobs(): int
    {
        // This would typically come from a metrics store
        return 1250;
    }

    /**
     * Get queue size
     */
    private function getQueueSize(): int
    {
        try {
            return Queue::size();
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get overall status
     */
    private function getOverallStatus(): string
    {
        $errorRate = $this->getErrorRate();
        $avgResponseTime = $this->getAverageResponseTime();

        if ($errorRate > 0.05 || $avgResponseTime > 500) {
            return 'warning';
        } elseif ($errorRate > 0.1 || $avgResponseTime > 1000) {
            return 'error';
        }

        return 'healthy';
    }

    /**
     * Get uptime
     */
    private function getUptime(): string
    {
        try {
            $uptime = shell_exec('uptime -p');
            return trim($uptime) ?: 'Unknown';
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }

    /**
     * Get memory usage
     */
    private function getMemoryUsage(): array
    {
        try {
            $memory = memory_get_usage(true);
            $peakMemory = memory_get_peak_usage(true);

            return [
                'current' => round($memory / 1024 / 1024, 2), // MB
                'peak' => round($peakMemory / 1024 / 1024, 2), // MB
            ];
        } catch (\Exception $e) {
            return ['current' => 0, 'peak' => 0];
        }
    }

    /**
     * Get disk usage
     */
    private function getDiskUsage(): array
    {
        try {
            $total = disk_total_space('/');
            $free = disk_free_space('/');
            $used = $total - $free;

            return [
                'total' => round($total / 1024 / 1024 / 1024, 2), // GB
                'used' => round($used / 1024 / 1024 / 1024, 2), // GB
                'free' => round($free / 1024 / 1024 / 1024, 2), // GB
                'percentage' => round(($used / $total) * 100, 2),
            ];
        } catch (\Exception $e) {
            return ['total' => 0, 'used' => 0, 'free' => 0, 'percentage' => 0];
        }
    }

    /**
     * Update API metrics
     */
    private function updateApiMetrics(float $responseTime, int $statusCode): void
    {
        try {
            $isError = $statusCode >= 400;
            
            // Update cache hits/misses
            if ($isError) {
                Cache::increment('api_errors');
            } else {
                Cache::increment('api_success');
            }

            // Store response times for P95 calculation
            $responseTimes = Cache::get('response_times', []);
            $responseTimes[] = $responseTime;
            
            // Keep only last 1000 response times
            if (count($responseTimes) > 1000) {
                $responseTimes = array_slice($responseTimes, -1000);
            }
            
            Cache::put('response_times', $responseTimes, 300);

        } catch (\Exception $e) {
            Log::error('Failed to update API metrics', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
