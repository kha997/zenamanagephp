<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class HealthCheckService
{
    /**
     * Perform comprehensive health checks
     */
    public static function performHealthChecks(): array
    {
        $checks = [
            'database' => self::checkDatabase(),
            'cache' => self::checkCache(),
            'queue' => self::checkQueue(),
            'storage' => self::checkStorage(),
            'redis' => self::checkRedis(),
            'session' => self::checkSession(),
            'mail' => self::checkMail(),
            'filesystem' => self::checkFilesystem(),
            'memory' => self::checkMemory(),
            'disk_space' => self::checkDiskSpace(),
        ];

        $overallStatus = self::determineOverallStatus($checks);

        return [
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'checks' => $checks,
            'summary' => self::generateSummary($checks),
        ];
    }

    /**
     * Check database connectivity and performance
     */
    protected static function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            
            // Test basic connectivity
            $result = DB::select('SELECT 1 as test');
            $connectTime = microtime(true) - $start;
            
            // Test database info
            $dbInfo = DB::select('SELECT VERSION() as version');
            $version = $dbInfo[0]->version ?? 'Unknown';
            
            // Test table existence for key tables
            $tables = ['users', 'tenants', 'projects', 'tasks'];
            $existingTables = [];
            foreach ($tables as $table) {
                try {
                    DB::select("SELECT COUNT(*) as count FROM {$table} LIMIT 1");
                    $existingTables[] = $table;
                } catch (\Exception $e) {
                    // Table doesn't exist or accessible
                }
            }
            
            return [
                'status' => 'healthy',
                'message' => 'Database connection successful',
                'details' => [
                    'driver' => $connection->getDriverName(),
                    'database' => $connection->getDatabaseName(),
                    'version' => $version,
                    'connection_time_ms' => round($connectTime * 1000, 2),
                    'existing_tables' => $existingTables,
                    'total_tables' => count($existingTables),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
                'details' => [
                    'driver' => config('database.default'),
                    'host' => config('database.connections.' . config('database.default') . '.host'),
                ],
            ];
        }
    }

    /**
     * Check cache system
     */
    protected static function checkCache(): array
    {
        try {
            $driver = config('cache.default');
            $store = Cache::getStore();
            
            // Test cache operations
            $testKey = 'health_check_' . time();
            $testValue = 'test_value_' . rand(1000, 9999);
            
            $start = microtime(true);
            Cache::put($testKey, $testValue, 60);
            $putTime = microtime(true) - $start;
            
            $start = microtime(true);
            $retrieved = Cache::get($testKey);
            $getTime = microtime(true) - $start;
            
            $start = microtime(true);
            Cache::forget($testKey);
            $deleteTime = microtime(true) - $start;
            
            $success = $retrieved === $testValue;
            
            return [
                'status' => $success ? 'healthy' : 'unhealthy',
                'message' => $success ? 'Cache operations successful' : 'Cache operations failed',
                'details' => [
                    'driver' => $driver,
                    'store_class' => get_class($store),
                    'put_time_ms' => round($putTime * 1000, 2),
                    'get_time_ms' => round($getTime * 1000, 2),
                    'delete_time_ms' => round($deleteTime * 1000, 2),
                    'test_successful' => $success,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Cache check failed',
                'error' => $e->getMessage(),
                'details' => [
                    'driver' => config('cache.default'),
                ],
            ];
        }
    }

    /**
     * Check queue system
     */
    protected static function checkQueue(): array
    {
        try {
            $queue = app('queue');
            $connection = $queue->connection();
            $driver = config('queue.default');
            
            // Test queue connection
            $connection->size(); // This will test the connection
            
            return [
                'status' => 'healthy',
                'message' => 'Queue connection successful',
                'details' => [
                    'driver' => $driver,
                    'connection_class' => get_class($connection),
                    'queue_size' => $connection->size(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Queue connection failed',
                'error' => $e->getMessage(),
                'details' => [
                    'driver' => config('queue.default'),
                ],
            ];
        }
    }

    /**
     * Check storage system
     */
    protected static function checkStorage(): array
    {
        try {
            $disk = Storage::disk();
            $driver = config('filesystems.default');
            
            // Test storage operations
            $testFile = 'health_check_' . time() . '.txt';
            $testContent = 'test_content_' . rand(1000, 9999);
            
            $start = microtime(true);
            Storage::put($testFile, $testContent);
            $putTime = microtime(true) - $start;
            
            $start = microtime(true);
            $retrieved = Storage::get($testFile);
            $getTime = microtime(true) - $start;
            
            $start = microtime(true);
            Storage::delete($testFile);
            $deleteTime = microtime(true) - $start;
            
            $success = $retrieved === $testContent;
            
            return [
                'status' => $success ? 'healthy' : 'unhealthy',
                'message' => $success ? 'Storage operations successful' : 'Storage operations failed',
                'details' => [
                    'driver' => $driver,
                    'disk_class' => get_class($disk),
                    'put_time_ms' => round($putTime * 1000, 2),
                    'get_time_ms' => round($getTime * 1000, 2),
                    'delete_time_ms' => round($deleteTime * 1000, 2),
                    'test_successful' => $success,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Storage check failed',
                'error' => $e->getMessage(),
                'details' => [
                    'driver' => config('filesystems.default'),
                ],
            ];
        }
    }

    /**
     * Check Redis connectivity
     */
    protected static function checkRedis(): array
    {
        try {
            if (config('cache.default') !== 'redis' && config('queue.default') !== 'redis') {
                return [
                    'status' => 'skipped',
                    'message' => 'Redis not configured as primary cache or queue driver',
                ];
            }
            
            $redis = Redis::connection();
            $start = microtime(true);
            
            // Test Redis operations
            $testKey = 'health_check_redis_' . time();
            $testValue = 'test_value_' . rand(1000, 9999);
            
            $redis->set($testKey, $testValue, 'EX', 60);
            $retrieved = $redis->get($testKey);
            $redis->del($testKey);
            
            $responseTime = microtime(true) - $start;
            $success = $retrieved === $testValue;
            
            return [
                'status' => $success ? 'healthy' : 'unhealthy',
                'message' => $success ? 'Redis operations successful' : 'Redis operations failed',
                'details' => [
                    'response_time_ms' => round($responseTime * 1000, 2),
                    'test_successful' => $success,
                    'info' => $redis->info(),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Redis check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check session system
     */
    protected static function checkSession(): array
    {
        try {
            $driver = config('session.driver');
            
            if ($driver === 'file') {
                $sessionPath = config('session.files');
                $writable = is_writable($sessionPath);
                
                return [
                    'status' => $writable ? 'healthy' : 'unhealthy',
                    'message' => $writable ? 'Session directory writable' : 'Session directory not writable',
                    'details' => [
                        'driver' => $driver,
                        'path' => $sessionPath,
                        'writable' => $writable,
                    ],
                ];
            }
            
            return [
                'status' => 'healthy',
                'message' => 'Session driver configured',
                'details' => [
                    'driver' => $driver,
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Session check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check mail system
     */
    protected static function checkMail(): array
    {
        try {
            $driver = config('mail.default');
            $host = config('mail.mailers.' . $driver . '.host');
            
            return [
                'status' => 'healthy',
                'message' => 'Mail configuration valid',
                'details' => [
                    'driver' => $driver,
                    'host' => $host,
                    'port' => config('mail.mailers.' . $driver . '.port'),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Mail check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check filesystem permissions
     */
    protected static function checkFilesystem(): array
    {
        try {
            $paths = [
                'storage' => storage_path(),
                'cache' => storage_path('framework/cache'),
                'logs' => storage_path('logs'),
                'sessions' => storage_path('framework/sessions'),
            ];
            
            $results = [];
            $allWritable = true;
            
            foreach ($paths as $name => $path) {
                $writable = is_writable($path);
                $results[$name] = [
                    'path' => $path,
                    'writable' => $writable,
                ];
                if (!$writable) {
                    $allWritable = false;
                }
            }
            
            return [
                'status' => $allWritable ? 'healthy' : 'unhealthy',
                'message' => $allWritable ? 'All filesystem paths writable' : 'Some filesystem paths not writable',
                'details' => $results,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Filesystem check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check memory usage
     */
    protected static function checkMemory(): array
    {
        try {
            $memoryLimit = ini_get('memory_limit');
            $memoryUsage = memory_get_usage(true);
            $peakMemory = memory_get_peak_usage(true);
            
            $memoryLimitBytes = self::convertToBytes($memoryLimit);
            $usagePercent = ($memoryUsage / $memoryLimitBytes) * 100;
            
            $status = 'healthy';
            if ($usagePercent > 90) {
                $status = 'unhealthy';
            } elseif ($usagePercent > 80) {
                $status = 'warning';
            }
            
            return [
                'status' => $status,
                'message' => "Memory usage: {$usagePercent}%",
                'details' => [
                    'limit' => $memoryLimit,
                    'usage_mb' => round($memoryUsage / 1024 / 1024, 2),
                    'peak_mb' => round($peakMemory / 1024 / 1024, 2),
                    'usage_percent' => round($usagePercent, 2),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Memory check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check disk space
     */
    protected static function checkDiskSpace(): array
    {
        try {
            $total = disk_total_space('/');
            $free = disk_free_space('/');
            $used = $total - $free;
            $usagePercent = ($used / $total) * 100;
            
            $status = 'healthy';
            if ($usagePercent > 95) {
                $status = 'unhealthy';
            } elseif ($usagePercent > 90) {
                $status = 'warning';
            }
            
            return [
                'status' => $status,
                'message' => "Disk usage: {$usagePercent}%",
                'details' => [
                    'total_gb' => round($total / 1024 / 1024 / 1024, 2),
                    'free_gb' => round($free / 1024 / 1024 / 1024, 2),
                    'used_gb' => round($used / 1024 / 1024 / 1024, 2),
                    'usage_percent' => round($usagePercent, 2),
                ],
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'message' => 'Disk space check failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Determine overall health status
     */
    protected static function determineOverallStatus(array $checks): string
    {
        $statuses = array_column($checks, 'status');
        
        if (in_array('unhealthy', $statuses)) {
            return 'unhealthy';
        }
        
        if (in_array('warning', $statuses)) {
            return 'degraded';
        }
        
        return 'healthy';
    }

    /**
     * Generate health check summary
     */
    protected static function generateSummary(array $checks): array
    {
        $total = count($checks);
        $healthy = count(array_filter($checks, fn($check) => $check['status'] === 'healthy'));
        $unhealthy = count(array_filter($checks, fn($check) => $check['status'] === 'unhealthy'));
        $warning = count(array_filter($checks, fn($check) => $check['status'] === 'warning'));
        $skipped = count(array_filter($checks, fn($check) => $check['status'] === 'skipped'));
        
        return [
            'total_checks' => $total,
            'healthy' => $healthy,
            'unhealthy' => $unhealthy,
            'warning' => $warning,
            'skipped' => $skipped,
            'health_percentage' => round(($healthy / $total) * 100, 2),
        ];
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
}
