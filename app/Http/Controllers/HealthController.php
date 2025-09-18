<?php declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class HealthController extends Controller
{
    /**
     * Basic health check endpoint
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => Carbon::now()->toISOString(),
            'service' => 'ZenaManage Dashboard',
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
        ]);
    }

    /**
     * Comprehensive health check with all services
     */
    public function detailed(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
            'queue' => $this->checkQueue(),
            'websocket' => $this->checkWebSocket(),
            'external_services' => $this->checkExternalServices(),
        ];

        $overallStatus = $this->determineOverallStatus($checks);

        return response()->json([
            'status' => $overallStatus,
            'timestamp' => Carbon::now()->toISOString(),
            'service' => 'ZenaManage Dashboard',
            'version' => config('app.version', '1.0.0'),
            'environment' => app()->environment(),
            'checks' => $checks,
        ], $overallStatus === 'healthy' ? 200 : 503);
    }

    /**
     * Database health check
     */
    private function checkDatabase(): array
    {
        try {
            $startTime = microtime(true);
            
            // Test basic connection
            DB::connection()->getPdo();
            
            // Test query execution
            $result = DB::select('SELECT 1 as test');
            
            // Check database size
            $size = DB::select("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB'
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ")[0]->{'DB Size in MB'} ?? 0;

            // Check connection count
            $connections = DB::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? 0;

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'database_size_mb' => $size,
                'active_connections' => (int) $connections,
                'message' => 'Database connection successful',
            ];
        } catch (\Exception $e) {
            Log::error('Database health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Database connection failed',
            ];
        }
    }

    /**
     * Redis health check
     */
    private function checkRedis(): array
    {
        try {
            $startTime = microtime(true);
            
            // Test Redis connection
            Redis::ping();
            
            // Test Redis operations
            $testKey = 'health_check_' . time();
            Redis::set($testKey, 'test_value', 'EX', 60);
            $value = Redis::get($testKey);
            Redis::del($testKey);

            if ($value !== 'test_value') {
                throw new \Exception('Redis read/write test failed');
            }

            // Get Redis info
            $info = Redis::info();
            $memoryUsage = $info['used_memory_human'] ?? 'Unknown';
            $connectedClients = $info['connected_clients'] ?? 0;

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'memory_usage' => $memoryUsage,
                'connected_clients' => (int) $connectedClients,
                'message' => 'Redis connection successful',
            ];
        } catch (\Exception $e) {
            Log::error('Redis health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Redis connection failed',
            ];
        }
    }

    /**
     * Storage health check
     */
    private function checkStorage(): array
    {
        try {
            $startTime = microtime(true);
            
            // Test storage write
            $testFile = 'health_check_' . time() . '.txt';
            $testContent = 'Health check test content';
            
            Storage::put($testFile, $testContent);
            
            // Test storage read
            $readContent = Storage::get($testFile);
            
            if ($readContent !== $testContent) {
                throw new \Exception('Storage read/write test failed');
            }
            
            // Clean up test file
            Storage::delete($testFile);
            
            // Get storage info
            $disk = Storage::disk();
            $totalSpace = disk_total_space(storage_path());
            $freeSpace = disk_free_space(storage_path());
            $usedSpace = $totalSpace - $freeSpace;
            $usagePercentage = round(($usedSpace / $totalSpace) * 100, 2);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'total_space_gb' => round($totalSpace / 1024 / 1024 / 1024, 2),
                'free_space_gb' => round($freeSpace / 1024 / 1024 / 1024, 2),
                'usage_percentage' => $usagePercentage,
                'message' => 'Storage access successful',
            ];
        } catch (\Exception $e) {
            Log::error('Storage health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Storage access failed',
            ];
        }
    }

    /**
     * Queue health check
     */
    private function checkQueue(): array
    {
        try {
            $startTime = microtime(true);
            
            // Check queue connection
            $queue = app('queue');
            $connection = $queue->connection();
            
            // Get queue size
            $size = $connection->size();
            
            // Check failed jobs
            $failedJobs = $connection->size('failed');
            
            // Test queue push
            $testJob = 'health_check_' . time();
            $connection->push('App\\Jobs\\HealthCheckJob', ['test' => $testJob]);
            
            // Clean up test job
            $connection->pop('default');

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'queue_size' => $size,
                'failed_jobs' => $failedJobs,
                'message' => 'Queue system operational',
            ];
        } catch (\Exception $e) {
            Log::error('Queue health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Queue system failed',
            ];
        }
    }

    /**
     * WebSocket health check
     */
    private function checkWebSocket(): array
    {
        try {
            $startTime = microtime(true);
            
            // Check WebSocket server status
            $websocketHost = config('websockets.host', 'localhost');
            $websocketPort = config('websockets.port', 6001);
            
            $connection = @fsockopen($websocketHost, $websocketPort, $errno, $errstr, 5);
            
            if (!$connection) {
                throw new \Exception("WebSocket server not reachable: $errstr ($errno)");
            }
            
            fclose($connection);

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            return [
                'status' => 'healthy',
                'response_time_ms' => $responseTime,
                'host' => $websocketHost,
                'port' => $websocketPort,
                'message' => 'WebSocket server accessible',
            ];
        } catch (\Exception $e) {
            Log::error('WebSocket health check failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'WebSocket server not accessible',
            ];
        }
    }

    /**
     * External services health check
     */
    private function checkExternalServices(): array
    {
        $services = [];
        
        // Check mail service
        $services['mail'] = $this->checkMailService();
        
        // Check external APIs
        $services['external_apis'] = $this->checkExternalApis();
        
        return $services;
    }

    /**
     * Mail service health check
     */
    private function checkMailService(): array
    {
        try {
            // Check if mail configuration exists
            $mailConfig = config('mail.default');
            if (!$mailConfig) {
                return [
                    'status' => 'unhealthy',
                    'message' => 'Mail service not configured',
                ];
            }
            
            return [
                'status' => 'healthy',
                'message' => 'Mail service configured',
                'driver' => $mailConfig,
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
                'message' => 'Mail service not configured',
            ];
        }
    }

    /**
     * External APIs health check
     */
    private function checkExternalApis(): array
    {
        $apis = [];
        
        // Check if external APIs are configured
        $externalApis = config('services.external_apis', []);
        
        foreach ($externalApis as $name => $config) {
            try {
                $startTime = microtime(true);
                
                $client = new \GuzzleHttp\Client();
                $response = $client->get($config['health_endpoint'], [
                    'timeout' => 5,
                    'verify' => false,
                ]);
                
                $responseTime = round((microtime(true) - $startTime) * 1000, 2);
                
                $apis[$name] = [
                    'status' => $response->getStatusCode() === 200 ? 'healthy' : 'unhealthy',
                    'response_time_ms' => $responseTime,
                    'status_code' => $response->getStatusCode(),
                    'message' => 'External API accessible',
                ];
            } catch (\Exception $e) {
                $apis[$name] = [
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                    'message' => 'External API not accessible',
                ];
            }
        }
        
        return $apis;
    }

    /**
     * Determine overall health status
     */
    private function determineOverallStatus(array $checks): string
    {
        $criticalServices = ['database', 'redis', 'storage'];
        $unhealthyCritical = 0;
        
        foreach ($criticalServices as $service) {
            if (isset($checks[$service]) && $checks[$service]['status'] !== 'healthy') {
                $unhealthyCritical++;
            }
        }
        
        if ($unhealthyCritical > 0) {
            return 'unhealthy';
        }
        
        $unhealthyServices = 0;
        foreach ($checks as $check) {
            if (is_array($check) && isset($check['status']) && $check['status'] !== 'healthy') {
                $unhealthyServices++;
            }
        }
        
        if ($unhealthyServices > 0) {
            return 'degraded';
        }
        
        return 'healthy';
    }

    /**
     * Metrics endpoint for Prometheus
     */
    public function metrics(): string
    {
        $metrics = [];
        
        // Application metrics
        $metrics[] = '# HELP app_uptime_seconds Application uptime in seconds';
        $metrics[] = '# TYPE app_uptime_seconds counter';
        $metrics[] = 'app_uptime_seconds ' . (time() - filemtime(base_path('bootstrap/app.php')));
        
        // Database metrics
        try {
            $dbSize = DB::select("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'DB Size in MB'
                FROM information_schema.tables 
                WHERE table_schema = DATABASE()
            ")[0]->{'DB Size in MB'} ?? 0;
            
            $metrics[] = '# HELP app_database_size_mb Database size in MB';
            $metrics[] = '# TYPE app_database_size_mb gauge';
            $metrics[] = "app_database_size_mb $dbSize";
        } catch (\Exception $e) {
            // Ignore database errors for metrics
        }
        
        // Redis metrics
        try {
            $redisInfo = Redis::info();
            $metrics[] = '# HELP app_redis_memory_usage_bytes Redis memory usage in bytes';
            $metrics[] = '# TYPE app_redis_memory_usage_bytes gauge';
            $metrics[] = 'app_redis_memory_usage_bytes ' . ($redisInfo['used_memory'] ?? 0);
        } catch (\Exception $e) {
            // Ignore Redis errors for metrics
        }
        
        // Storage metrics
        try {
            $totalSpace = disk_total_space(storage_path());
            $freeSpace = disk_free_space(storage_path());
            $usedSpace = $totalSpace - $freeSpace;
            
            $metrics[] = '# HELP app_storage_total_bytes Total storage space in bytes';
            $metrics[] = '# TYPE app_storage_total_bytes gauge';
            $metrics[] = "app_storage_total_bytes $totalSpace";
            
            $metrics[] = '# HELP app_storage_free_bytes Free storage space in bytes';
            $metrics[] = '# TYPE app_storage_free_bytes gauge';
            $metrics[] = "app_storage_free_bytes $freeSpace";
            
            $metrics[] = '# HELP app_storage_used_bytes Used storage space in bytes';
            $metrics[] = '# TYPE app_storage_used_bytes gauge';
            $metrics[] = "app_storage_used_bytes $usedSpace";
        } catch (\Exception $e) {
            // Ignore storage errors for metrics
        }
        
        return implode("\n", $metrics) . "\n";
    }

    /**
     * Readiness probe for Kubernetes
     */
    public function readiness(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'redis' => $this->checkRedis(),
            'storage' => $this->checkStorage(),
        ];

        $ready = true;
        foreach ($checks as $check) {
            if ($check['status'] !== 'healthy') {
                $ready = false;
                break;
            }
        }

        return response()->json([
            'ready' => $ready,
            'timestamp' => Carbon::now()->toISOString(),
            'checks' => $checks,
        ], $ready ? 200 : 503);
    }

    /**
     * Liveness probe for Kubernetes
     */
    public function liveness(): JsonResponse
    {
        return response()->json([
            'alive' => true,
            'timestamp' => Carbon::now()->toISOString(),
            'uptime' => time() - filemtime(base_path('bootstrap/app.php')),
        ]);
    }
}