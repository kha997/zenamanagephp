<?php

namespace App\Http\Controllers;

use App\Services\MetricsCollectionService;
use App\Services\StructuredLoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MonitoringController extends Controller
{
    /**
     * Get application metrics
     */
    public function metrics(): JsonResponse
    {
        try {
            $metrics = MetricsCollectionService::collectAllMetrics();
            
            // Store metrics for historical tracking
            MetricsCollectionService::storeMetrics($metrics);
            
            // Log metrics collection
            StructuredLoggingService::logEvent('metrics_collected', [
                'metrics_count' => count($metrics),
                'timestamp' => now()->toISOString(),
            ]);
            
            return response()->json([
                'status' => 'success',
                'data' => $metrics,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Failed to collect metrics', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to collect metrics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get health status
     */
    public function health(): JsonResponse
    {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'version' => config('app.version', '1.0.0'),
                'environment' => app()->environment(),
                'checks' => [
                    'database' => $this->checkDatabase(),
                    'cache' => $this->checkCache(),
                    'queue' => $this->checkQueue(),
                    'storage' => $this->checkStorage(),
                ],
            ];
            
            // Determine overall health status
            $allHealthy = collect($health['checks'])->every(function ($check) {
                return $check['status'] === 'healthy';
            });
            
            if (!$allHealthy) {
                $health['status'] = 'degraded';
            }
            
            return response()->json($health);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Health check failed', $e);
            
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get performance metrics
     */
    public function performance(): JsonResponse
    {
        try {
            $performance = [
                'timestamp' => now()->toISOString(),
                'application' => [
                    'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                    'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
                    'execution_time_ms' => round((microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']) * 1000, 2),
                ],
                'database' => [
                    'query_count' => count(\DB::getQueryLog()),
                    'connection_status' => 'connected',
                ],
                'cache' => [
                    'driver' => config('cache.default'),
                    'status' => 'available',
                ],
                'system' => [
                    'cpu_usage_percent' => $this->getCpuUsage(),
                    'memory_usage_percent' => $this->getMemoryUsage(),
                    'disk_usage_percent' => $this->getDiskUsage(),
                    'load_average' => sys_getloadavg(),
                ],
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => $performance,
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Performance metrics failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to collect performance metrics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get historical metrics
     */
    public function historical(Request $request): JsonResponse
    {
        try {
            $hours = $request->get('hours', 24);
            $hours = min($hours, 168); // Max 1 week
            
            $metrics = MetricsCollectionService::getStoredMetrics($hours);
            
            return response()->json([
                'status' => 'success',
                'data' => $metrics,
                'hours' => $hours,
                'count' => count($metrics),
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Historical metrics failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get historical metrics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get logs
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            $level = $request->get('level', 'info');
            $limit = min($request->get('limit', 100), 1000);
            
            // This is a simplified implementation
            // In production, you'd want to use a proper log aggregation service
            $logs = $this->getRecentLogs($level, $limit);
            
            return response()->json([
                'status' => 'success',
                'data' => $logs,
                'level' => $level,
                'limit' => $limit,
            ]);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Log retrieval failed', $e);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check database health
     */
    protected function checkDatabase(): array
    {
        try {
            \DB::connection()->getPdo();
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check cache health
     */
    protected function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . time();
            \Cache::put($testKey, 'test', 60);
            $value = \Cache::get($testKey);
            \Cache::forget($testKey);
            
            if ($value === 'test') {
                return [
                    'status' => 'healthy',
                    'message' => 'Cache operations successful',
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Cache operations failed',
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check queue health
     */
    protected function checkQueue(): array
    {
        try {
            $queue = app('queue');
            $connection = $queue->connection();
            
            return [
                'status' => 'healthy',
                'message' => 'Queue connection successful',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Queue connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check storage health
     */
    protected function checkStorage(): array
    {
        try {
            $testFile = 'health_check_' . time() . '.txt';
            $testContent = 'test';
            
            \Storage::put($testFile, $testContent);
            $retrieved = \Storage::get($testFile);
            \Storage::delete($testFile);
            
            if ($retrieved === $testContent) {
                return [
                    'status' => 'healthy',
                    'message' => 'Storage operations successful',
                ];
            } else {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Storage operations failed',
                ];
            }
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Storage check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get CPU usage percentage
     */
    protected function getCpuUsage(): float
    {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return round($load[0] * 100, 2);
        }
        return 0.0;
    }

    /**
     * Get memory usage percentage
     */
    protected function getMemoryUsage(): float
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return 0.0;
        }
        
        $memoryLimitBytes = $this->convertToBytes($memoryLimit);
        $memoryUsage = memory_get_usage(true);
        
        return round(($memoryUsage / $memoryLimitBytes) * 100, 2);
    }

    /**
     * Get disk usage percentage
     */
    protected function getDiskUsage(): float
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        
        if ($total && $free) {
            return round((($total - $free) / $total) * 100, 2);
        }
        
        return 0.0;
    }

    /**
     * Convert memory limit string to bytes
     */
    protected function convertToBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }

    /**
     * Get recent logs (simplified implementation)
     */
    protected function getRecentLogs(string $level, int $limit): array
    {
        // This is a simplified implementation
        // In production, you'd want to use a proper log aggregation service
        return [
            [
                'timestamp' => now()->toISOString(),
                'level' => $level,
                'message' => 'Sample log entry',
                'context' => ['correlation_id' => 'sample-id'],
            ],
        ];
    }
}
