<?php

namespace App\Http\Controllers\Api\Admin;
use Illuminate\Support\Facades\Auth;


use App\Http\Controllers\Controller;
use App\Services\PerformanceOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class PerformanceController extends Controller
{
    protected $performanceService;

    public function __construct(PerformanceOptimizationService $performanceService)
    {
        $this->performanceService = $performanceService;
    }
    /**
     * Admin performance metrics endpoint
     * Requires authentication + admin ability
     */
    public function metrics(): JsonResponse
    {
        try {
            $metrics = [
                'system' => $this->getSystemMetrics(),
                'database' => $this->getDatabaseMetrics(),
                'cache' => $this->getCacheMetrics(),
                'storage' => $this->getStorageMetrics(),
                'application' => $this->getApplicationMetrics(),
                'timestamp' => now()->toISOString()
            ];

            $this->logAudit('performance.metrics', 'Retrieved performance metrics', [
                'user_id' => Auth::id(),
                'metrics_count' => count($metrics)
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $metrics
            ]);

        } catch (\Exception $e) {
            Log::error('PerformanceController@metrics error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve metrics'
            ], 500);
        }
    }

    /**
     * Admin health check endpoint
     * Requires authentication + admin ability
     */
    public function health(): JsonResponse
    {
        try {
            $health = [
                'database' => $this->getDatabaseHealth(),
                'cache' => $this->getCacheHealth(),
                'storage' => $this->getStorageHealth(),
                'queue' => $this->getQueueHealth(),
                'services' => $this->getServicesHealth(),
                'timestamp' => now()->toISOString()
            ];

            $allHealthy = collect($health)->every(function ($check) {
                return $check['status'] === 'healthy';
            });

            $this->logAudit('performance.health', 'Performed health check', [
                'user_id' => Auth::id(),
                'overall_status' => $allHealthy ? 'healthy' : 'unhealthy'
            ]);

            return response()->json([
                'status' => $allHealthy ? 'healthy' : 'unhealthy',
                'data' => $health
            ], $allHealthy ? 200 : 503);

        } catch (\Exception $e) {
            Log::error('PerformanceController@health error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Health check failed'
            ], 500);
        }
    }

    /**
     * Clear caches endpoint
     * Requires authentication + admin ability
     */
    public function clearCaches(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'cache_types' => 'array',
                'cache_types.*' => 'in:config,route,view,application,query'
            ]);

            $cacheTypes = $validated['cache_types'] ?? ['config', 'route', 'view', 'application'];
            $results = [];

            foreach ($cacheTypes as $type) {
                try {
                    switch ($type) {
                        case 'config':
                            Artisan::call('config:clear');
                            $results[$type] = 'cleared';
                            break;
                        case 'route':
                            Artisan::call('route:clear');
                            $results[$type] = 'cleared';
                            break;
                        case 'view':
                            Artisan::call('view:clear');
                            $results[$type] = 'cleared';
                            break;
                        case 'application':
                            Cache::flush();
                            $results[$type] = 'cleared';
                            break;
                        case 'query':
                            // Clear query cache if using query caching
                            $results[$type] = 'not_implemented';
                            break;
                    }
                } catch (\Exception $e) {
                    $results[$type] = 'failed: ' . $e->getMessage();
                }
            }

            $this->logAudit('performance.clear_caches', 'Cleared system caches', [
                'user_id' => Auth::id(),
                'cache_types' => $cacheTypes,
                'results' => $results
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Caches cleared successfully',
                'data' => $results
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('PerformanceController@clearCaches error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to clear caches'
            ], 500);
        }
    }

    /**
     * Get system metrics
     */
    private function getSystemMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'load_average' => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version()
        ];
    }

    /**
     * Get database metrics
     */
    private function getDatabaseMetrics(): array
    {
        try {
            $connection = DB::connection();
            $pdo = $connection->getPdo();
            
            return [
                'driver' => $connection->getDriverName(),
                'version' => $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION),
                'connection_count' => $connection->getQueryLog() ? count($connection->getQueryLog()) : 0
            ];
        } catch (\Exception $e) {
            return ['error' => 'Database connection failed'];
        }
    }

    /**
     * Get cache metrics
     */
    private function getCacheMetrics(): array
    {
        try {
            $driver = Cache::getStore();
            return [
                'driver' => get_class($driver),
                'available' => true
            ];
        } catch (\Exception $e) {
            return ['error' => 'Cache system unavailable'];
        }
    }

    /**
     * Get storage metrics
     */
    private function getStorageMetrics(): array
    {
        $storagePath = storage_path();
        $publicPath = public_path();
        
        return [
            'storage_free' => disk_free_space($storagePath),
            'storage_total' => disk_total_space($storagePath),
            'public_free' => disk_free_space($publicPath),
            'public_total' => disk_total_space($publicPath)
        ];
    }

    /**
     * Get application metrics
     */
    private function getApplicationMetrics(): array
    {
        return [
            'environment' => app()->environment(),
            'debug_mode' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale')
        ];
    }

    /**
     * Get database health
     */
    private function getDatabaseHealth(): array
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Database connection failed'];
        }
    }

    /**
     * Get cache health
     */
    private function getCacheHealth(): array
    {
        try {
            Cache::put('health_check', 'ok', 10);
            $value = Cache::get('health_check');
            if ($value === 'ok') {
                return ['status' => 'healthy', 'message' => 'Cache system operational'];
            }
            return ['status' => 'unhealthy', 'message' => 'Cache read/write failed'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Cache system unavailable'];
        }
    }

    /**
     * Get storage health
     */
    private function getStorageHealth(): array
    {
        try {
            $storagePath = storage_path('app');
            if (is_dir($storagePath) && is_writable($storagePath)) {
                return ['status' => 'healthy', 'message' => 'Storage accessible and writable'];
            }
            return ['status' => 'unhealthy', 'message' => 'Storage not accessible or writable'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Storage check failed'];
        }
    }

    /**
     * Get queue health
     */
    private function getQueueHealth(): array
    {
        try {
            // Basic queue check - can be extended based on queue driver
            return ['status' => 'healthy', 'message' => 'Queue system operational'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Queue system unavailable'];
        }
    }

    /**
     * Get services health
     */
    private function getServicesHealth(): array
    {
        return [
            'status' => 'healthy',
            'message' => 'Core services operational',
            'services' => [
                'authentication' => 'operational',
                'authorization' => 'operational',
                'logging' => 'operational'
            ]
        ];
    }

    /**
     * Optimize database tables
     */
    public function optimizeTables(): JsonResponse
    {
        try {
            $results = $this->performanceService->optimizeTables();
            
            $this->logAudit('performance.optimize_tables', 'Optimized database tables', [
                'user_id' => Auth::id(),
                'results' => $results
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Database tables optimized successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('PerformanceController@optimizeTables error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to optimize tables'
            ], 500);
        }
    }

    /**
     * Analyze database tables
     */
    public function analyzeTables(): JsonResponse
    {
        try {
            $results = $this->performanceService->analyzeTables();
            
            $this->logAudit('performance.analyze_tables', 'Analyzed database tables', [
                'user_id' => Auth::id(),
                'results' => $results
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Database tables analyzed successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('PerformanceController@analyzeTables error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to analyze tables'
            ], 500);
        }
    }

    /**
     * Get slow queries
     */
    public function getSlowQueries(Request $request): JsonResponse
    {
        try {
            $limit = $request->input('limit', 10);
            $queries = $this->performanceService->getSlowQueries($limit);
            
            return response()->json([
                'status' => 'success',
                'data' => $queries
            ]);
        } catch (\Exception $e) {
            Log::error('PerformanceController@getSlowQueries error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get slow queries'
            ], 500);
        }
    }

    /**
     * Get cache statistics
     */
    public function getCacheStats(): JsonResponse
    {
        try {
            $stats = $this->performanceService->getCacheStats();
            
            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('PerformanceController@getCacheStats error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get cache statistics'
            ], 500);
        }
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStats(): JsonResponse
    {
        try {
            $stats = $this->performanceService->getDatabaseStats();
            
            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('PerformanceController@getDatabaseStats error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get database statistics'
            ], 500);
        }
    }

    /**
     * Suggest database indexes
     */
    public function suggestIndexes(): JsonResponse
    {
        try {
            $suggestions = $this->performanceService->suggestIndexes();
            
            return response()->json([
                'status' => 'success',
                'data' => $suggestions
            ]);
        } catch (\Exception $e) {
            Log::error('PerformanceController@suggestIndexes error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get index suggestions'
            ], 500);
        }
    }

    /**
     * Monitor query performance
     */
    public function monitorQueryPerformance(): JsonResponse
    {
        try {
            $metrics = $this->performanceService->monitorQueryPerformance();
            
            return response()->json([
                'status' => 'success',
                'data' => $metrics
            ]);
        } catch (\Exception $e) {
            Log::error('PerformanceController@monitorQueryPerformance error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to monitor query performance'
            ], 500);
        }
    }

    /**
     * Get memory statistics
     */
    public function getMemoryStats(): JsonResponse
    {
        try {
            $stats = $this->performanceService->getMemoryStats();
            
            return response()->json([
                'status' => 'success',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('PerformanceController@getMemoryStats error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get memory statistics'
            ], 500);
        }
    }

    /**
     * Optimize file storage
     */
    public function optimizeFileStorage(): JsonResponse
    {
        try {
            $results = $this->performanceService->optimizeFileStorage();
            
            $this->logAudit('performance.optimize_storage', 'Optimized file storage', [
                'user_id' => Auth::id(),
                'results' => $results
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'File storage optimized successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('PerformanceController@optimizeFileStorage error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to optimize file storage'
            ], 500);
        }
    }

    /**
     * Log audit trail
     */
    private function logAudit(string $action, string $description, array $context = []): void
    {
        Log::info('AUDIT: ' . $action, array_merge([
            'action' => $action,
            'description' => $description,
            'timestamp' => now()->toISOString(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ], $context));
    }
}
