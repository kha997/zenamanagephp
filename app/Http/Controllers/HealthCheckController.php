<?php

namespace App\Http\Controllers;

use App\Services\HealthCheckService;
use App\Services\StructuredLoggingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class HealthCheckController extends Controller
{
    /**
     * Basic health check endpoint
     */
    public function basic(): JsonResponse
    {
        try {
            $health = [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'version' => config('app.version', '1.0.0'),
                'environment' => app()->environment(),
                'uptime' => time() - ($_SERVER['REQUEST_TIME_FLOAT'] ?? time()),
            ];
            
            return response()->json($health);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Basic health check failed', $e);
            
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Comprehensive health check endpoint
     */
    public function comprehensive(): JsonResponse
    {
        try {
            $startTime = microtime(true);
            $health = HealthCheckService::performHealthChecks();
            $duration = microtime(true) - $startTime;
            
            // Add performance metrics
            $health['performance'] = [
                'check_duration_ms' => round($duration * 1000, 2),
                'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
                'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ];
            
            // Log health check
            StructuredLoggingService::logEvent('health_check_performed', [
                'status' => $health['status'],
                'duration_ms' => $health['performance']['check_duration_ms'],
                'checks_count' => count($health['checks']),
                'summary' => $health['summary'],
            ]);
            
            // Set appropriate HTTP status code
            $httpStatus = $health['status'] === 'healthy' ? 200 : 503;
            
            return response()->json($health, $httpStatus);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Comprehensive health check failed', $e);
            
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage(),
                'message' => 'Health check system failure',
            ], 500);
        }
    }

    /**
     * Database health check endpoint
     */
    public function database(): JsonResponse
    {
        try {
            $startTime = microtime(true);
            $dbHealth = HealthCheckService::performHealthChecks()['checks']['database'];
            $duration = microtime(true) - $startTime;
            
            $dbHealth['performance'] = [
                'check_duration_ms' => round($duration * 1000, 2),
            ];
            
            $httpStatus = $dbHealth['status'] === 'healthy' ? 200 : 503;
            
            return response()->json($dbHealth, $httpStatus);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Database health check failed', $e);
            
            return response()->json([
                'status' => 'unhealthy',
                'message' => 'Database health check failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cache health check endpoint
     */
    public function cache(): JsonResponse
    {
        try {
            $startTime = microtime(true);
            $cacheHealth = HealthCheckService::performHealthChecks()['checks']['cache'];
            $duration = microtime(true) - $startTime;
            
            $cacheHealth['performance'] = [
                'check_duration_ms' => round($duration * 1000, 2),
            ];
            
            $httpStatus = $cacheHealth['status'] === 'healthy' ? 200 : 503;
            
            return response()->json($cacheHealth, $httpStatus);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Cache health check failed', $e);
            
            return response()->json([
                'status' => 'unhealthy',
                'message' => 'Cache health check failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Storage health check endpoint
     */
    public function storage(): JsonResponse
    {
        try {
            $startTime = microtime(true);
            $storageHealth = HealthCheckService::performHealthChecks()['checks']['storage'];
            $duration = microtime(true) - $startTime;
            
            $storageHealth['performance'] = [
                'check_duration_ms' => round($duration * 1000, 2),
            ];
            
            $httpStatus = $storageHealth['status'] === 'healthy' ? 200 : 503;
            
            return response()->json($storageHealth, $httpStatus);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Storage health check failed', $e);
            
            return response()->json([
                'status' => 'unhealthy',
                'message' => 'Storage health check failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * System health check endpoint
     */
    public function system(): JsonResponse
    {
        try {
            $startTime = microtime(true);
            $health = HealthCheckService::performHealthChecks();
            $duration = microtime(true) - $startTime;
            
            // Extract system-related checks
            $systemChecks = [
                'memory' => $health['checks']['memory'],
                'disk_space' => $health['checks']['disk_space'],
                'filesystem' => $health['checks']['filesystem'],
            ];
            
            $systemHealth = [
                'status' => $health['status'],
                'timestamp' => $health['timestamp'],
                'checks' => $systemChecks,
                'performance' => [
                    'check_duration_ms' => round($duration * 1000, 2),
                ],
            ];
            
            $httpStatus = $systemHealth['status'] === 'healthy' ? 200 : 503;
            
            return response()->json($systemHealth, $httpStatus);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('System health check failed', $e);
            
            return response()->json([
                'status' => 'unhealthy',
                'message' => 'System health check failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Readiness probe endpoint (for Kubernetes)
     */
    public function readiness(): JsonResponse
    {
        try {
            $health = HealthCheckService::performHealthChecks();
            
            // For readiness, we only care about critical services
            $criticalChecks = ['database', 'cache', 'storage'];
            $criticalStatus = 'healthy';
            
            foreach ($criticalChecks as $check) {
                if (isset($health['checks'][$check]) && $health['checks'][$check]['status'] !== 'healthy') {
                    $criticalStatus = 'unhealthy';
                    break;
                }
            }
            
            $response = [
                'status' => $criticalStatus,
                'timestamp' => now()->toISOString(),
                'ready' => $criticalStatus === 'healthy',
                'checks' => array_intersect_key($health['checks'], array_flip($criticalChecks)),
            ];
            
            $httpStatus = $criticalStatus === 'healthy' ? 200 : 503;
            
            return response()->json($response, $httpStatus);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Readiness probe failed', $e);
            
            return response()->json([
                'status' => 'unhealthy',
                'ready' => false,
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage(),
            ], 503);
        }
    }

    /**
     * Liveness probe endpoint (for Kubernetes)
     */
    public function liveness(): JsonResponse
    {
        try {
            // Liveness probe only checks if the application is running
            $response = [
                'status' => 'healthy',
                'timestamp' => now()->toISOString(),
                'alive' => true,
                'uptime' => time() - ($_SERVER['REQUEST_TIME_FLOAT'] ?? time()),
            ];
            
            return response()->json($response);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Liveness probe failed', $e);
            
            return response()->json([
                'status' => 'unhealthy',
                'alive' => false,
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Health check status endpoint
     */
    public function status(): JsonResponse
    {
        try {
            $health = HealthCheckService::performHealthChecks();
            
            // Return simplified status for monitoring systems
            $status = [
                'status' => $health['status'],
                'timestamp' => $health['timestamp'],
                'version' => $health['version'],
                'environment' => $health['environment'],
                'summary' => $health['summary'],
            ];
            
            $httpStatus = $health['status'] === 'healthy' ? 200 : 503;
            
            return response()->json($status, $httpStatus);
        } catch (\Exception $e) {
            StructuredLoggingService::logError('Health status check failed', $e);
            
            return response()->json([
                'status' => 'unhealthy',
                'timestamp' => now()->toISOString(),
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
