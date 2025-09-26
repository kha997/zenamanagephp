<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetricsCollectionService
{
    /**
     * Collect application metrics
     */
    public static function collectApplicationMetrics(): array
    {
        return [
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'app_version' => config('app.version', '1.0.0'),
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'uptime_seconds' => time() - $_SERVER['REQUEST_TIME_FLOAT'] ?? 0,
        ];
    }

    /**
     * Collect database metrics
     */
    public static function collectDatabaseMetrics(): array
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            
            return [
                'connection_status' => 'connected',
                'driver' => $connection->getDriverName(),
                'database' => $connection->getDatabaseName(),
                'server_version' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),
                'connection_timeout' => $pdo->getAttribute(\PDO::ATTR_TIMEOUT),
                'query_count' => count(DB::getQueryLog()),
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
    public static function collectCacheMetrics(): array
    {
        try {
            $store = Cache::getStore();
            $driver = config('cache.default');
            
            $metrics = [
                'driver' => $driver,
                'store_class' => get_class($store),
            ];

            // Test cache operations
            $testKey = 'metrics_test_' . time();
            $testValue = 'test_value';
            
            $start = microtime(true);
            Cache::put($testKey, $testValue, 60);
            $putTime = microtime(true) - $start;
            
            $start = microtime(true);
            $retrieved = Cache::get($testKey);
            $getTime = microtime(true) - $start;
            
            $start = microtime(true);
            Cache::forget($testKey);
            $deleteTime = microtime(true) - $start;
            
            $metrics['operations'] = [
                'put_time_ms' => round($putTime * 1000, 2),
                'get_time_ms' => round($getTime * 1000, 2),
                'delete_time_ms' => round($deleteTime * 1000, 2),
                'test_successful' => $retrieved === $testValue,
            ];

            return $metrics;
        } catch (\Exception $e) {
            return [
                'driver' => config('cache.default'),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect queue metrics
     */
    public static function collectQueueMetrics(): array
    {
        try {
            $queue = app('queue');
            $connection = $queue->connection();
            
            return [
                'driver' => config('queue.default'),
                'connection_class' => get_class($connection),
                'status' => 'available',
            ];
        } catch (\Exception $e) {
            return [
                'driver' => config('queue.default'),
                'status' => 'error',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Collect system metrics
     */
    public static function collectSystemMetrics(): array
    {
        return [
            'cpu_usage_percent' => self::getCpuUsage(),
            'memory_usage_percent' => self::getMemoryUsage(),
            'disk_usage_percent' => self::getDiskUsage(),
            'load_average' => sys_getloadavg(),
            'process_count' => self::getProcessCount(),
        ];
    }

    /**
     * Collect all metrics
     */
    public static function collectAllMetrics(): array
    {
        return [
            'application' => self::collectApplicationMetrics(),
            'database' => self::collectDatabaseMetrics(),
            'cache' => self::collectCacheMetrics(),
            'queue' => self::collectQueueMetrics(),
            'system' => self::collectSystemMetrics(),
        ];
    }

    /**
     * Get CPU usage percentage
     */
    protected static function getCpuUsage(): float
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
    protected static function getMemoryUsage(): float
    {
        $memoryLimit = ini_get('memory_limit');
        if ($memoryLimit === '-1') {
            return 0.0; // No limit
        }
        
        $memoryLimitBytes = self::convertToBytes($memoryLimit);
        $memoryUsage = memory_get_usage(true);
        
        return round(($memoryUsage / $memoryLimitBytes) * 100, 2);
    }

    /**
     * Get disk usage percentage
     */
    protected static function getDiskUsage(): float
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        
        if ($total && $free) {
            return round((($total - $free) / $total) * 100, 2);
        }
        
        return 0.0;
    }

    /**
     * Get process count
     */
    protected static function getProcessCount(): int
    {
        if (function_exists('exec')) {
            $output = [];
            exec('ps aux | wc -l', $output);
            return (int) ($output[0] ?? 0) - 1; // Subtract header line
        }
        return 0;
    }

    /**
     * Convert memory limit string to bytes
     */
    protected static function convertToBytes(string $value): int
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
     * Store metrics in cache for monitoring
     */
    public static function storeMetrics(array $metrics): void
    {
        $key = 'metrics_' . now()->format('Y-m-d-H-i');
        Cache::put($key, $metrics, 3600); // Store for 1 hour
    }

    /**
     * Get stored metrics
     */
    public static function getStoredMetrics(int $hours = 24): array
    {
        $metrics = [];
        $now = now();
        
        for ($i = 0; $i < $hours; $i++) {
            $time = $now->copy()->subHours($i);
            $key = 'metrics_' . $time->format('Y-m-d-H-i');
            $metric = Cache::get($key);
            
            if ($metric) {
                $metrics[] = $metric;
            }
        }
        
        return $metrics;
    }
}
