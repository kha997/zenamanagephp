<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class HealthController extends Controller
{
    /**
     * Comprehensive health check endpoint for production monitoring
     */
    public function health(Request $request): JsonResponse
    {
        $startTime = microtime(true);
        $checks = [];
        $overallStatus = 'healthy';

        // Database connectivity check
        try {
            $dbStart = microtime(true);
            DB::connection()->getPdo();
            $dbTime = round((microtime(true) - $dbStart) * 1000, 2);
            
            $checks['database'] = [
                'status' => 'healthy',
                'response_time_ms' => $dbTime,
                'connection' => DB::connection()->getDatabaseName(),
            ];
        } catch (\Exception $e) {
            $checks['database'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $overallStatus = 'unhealthy';
        }

        // Redis connectivity check
        try {
            $redisStart = microtime(true);
            Redis::ping();
            $redisTime = round((microtime(true) - $redisStart) * 1000, 2);
            
            $checks['redis'] = [
                'status' => 'healthy',
                'response_time_ms' => $redisTime,
            ];
        } catch (\Exception $e) {
            $checks['redis'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $overallStatus = 'unhealthy';
        }

        // Cache functionality check
        try {
            $cacheKey = 'health_check_' . time();
            Cache::put($cacheKey, 'test', 60);
            $cacheValue = Cache::get($cacheKey);
            Cache::forget($cacheKey);
            
            $checks['cache'] = [
                'status' => $cacheValue === 'test' ? 'healthy' : 'unhealthy',
                'driver' => config('cache.default'),
            ];
            
            if ($cacheValue !== 'test') {
                $overallStatus = 'unhealthy';
            }
        } catch (\Exception $e) {
            $checks['cache'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $overallStatus = 'unhealthy';
        }

        // File system check
        try {
            $testFile = 'health_check_' . time() . '.txt';
            Storage::put($testFile, 'test');
            $content = Storage::get($testFile);
            Storage::delete($testFile);
            
            $checks['filesystem'] = [
                'status' => $content === 'test' ? 'healthy' : 'unhealthy',
                'driver' => config('filesystems.default'),
            ];
            
            if ($content !== 'test') {
                $overallStatus = 'unhealthy';
            }
        } catch (\Exception $e) {
            $checks['filesystem'] = [
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
            $overallStatus = 'unhealthy';
        }

        // Application metrics
        $checks['application'] = [
            'status' => 'healthy',
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
        ];

        // System metrics
        $checks['system'] = [
            'status' => 'healthy',
            'php_version' => PHP_VERSION,
            'memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'memory_limit' => ini_get('memory_limit'),
            'disk_free_space' => round(disk_free_space('/') / 1024 / 1024 / 1024, 2) . ' GB',
        ];

        // Security check
        $checks['security'] = [
            'status' => 'healthy',
            'https_enabled' => $request->isSecure(),
            'csrf_protection' => config('session.csrf_protection', true),
            'session_secure' => config('session.secure', false),
        ];

        // Performance metrics
        $totalTime = round((microtime(true) - $startTime) * 1000, 2);
        
        $response = [
            'status' => $overallStatus,
            'timestamp' => now()->toISOString(),
            'response_time_ms' => $totalTime,
            'checks' => $checks,
            'version' => config('app.version', '1.0.0'),
            'environment' => config('app.env'),
        ];

        // Add request correlation ID if available
        if ($request->hasHeader('X-Request-ID')) {
            $response['request_id'] = $request->header('X-Request-ID');
        }

        $statusCode = $overallStatus === 'healthy' ? 200 : 503;
        
        return response()->json($response, $statusCode);
    }

    /**
     * Simple health check for load balancers
     */
    public function ping(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Detailed system information (for debugging)
     */
    public function info(Request $request): JsonResponse
    {
        // Only allow in non-production environments or with proper authentication
        if (config('app.env') === 'production' && !$request->hasHeader('X-Debug-Token')) {
            return response()->json(['error' => 'Not available in production'], 403);
        }

        return response()->json([
            'application' => [
                'name' => config('app.name'),
                'version' => config('app.version', '1.0.0'),
                'environment' => config('app.env'),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
            ],
            'database' => [
                'connection' => config('database.default'),
                'host' => config('database.connections.mysql.host'),
                'database' => config('database.connections.mysql.database'),
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'prefix' => config('cache.prefix'),
            ],
            'session' => [
                'driver' => config('session.driver'),
                'lifetime' => config('session.lifetime'),
                'secure' => config('session.secure'),
            ],
            'mail' => [
                'driver' => config('mail.default'),
                'host' => config('mail.mailers.smtp.host'),
            ],
            'php' => [
                'version' => PHP_VERSION,
                'extensions' => get_loaded_extensions(),
            ],
            'server' => [
                'software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                'protocol' => $_SERVER['SERVER_PROTOCOL'] ?? 'Unknown',
            ],
        ]);
    }
}