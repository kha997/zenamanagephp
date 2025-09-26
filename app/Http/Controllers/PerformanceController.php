<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;

class PerformanceController extends Controller
{
    /**
     * Get system performance metrics
     */
    public function metrics()
    {
        $metrics = [
            'timestamp' => now()->toISOString(),
            'system' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'environment' => app()->environment(),
                'debug_mode' => config('app.debug'),
            ],
            'database' => [
                'connection_count' => $this->getDatabaseConnectionCount(),
                'query_time' => $this->getAverageQueryTime(),
                'slow_queries' => $this->getSlowQueries(),
            ],
            'cache' => [
                'driver' => config('cache.default'),
                'hit_rate' => $this->getCacheHitRate(),
                'memory_usage' => $this->getCacheMemoryUsage(),
            ],
            'routes' => [
                'total_routes' => $this->getTotalRoutes(),
                'admin_routes' => $this->getAdminRoutes(),
                'app_routes' => $this->getAppRoutes(),
                'legacy_routes' => $this->getLegacyRoutes(),
            ],
            'users' => [
                'total_users' => $this->getTotalUsers(),
                'active_users' => $this->getActiveUsers(),
                'super_admins' => $this->getSuperAdmins(),
                'tenant_users' => $this->getTenantUsers(),
            ],
            'performance' => [
                'memory_usage' => memory_get_usage(true),
                'peak_memory' => memory_get_peak_usage(true),
                'execution_time' => microtime(true) - LARAVEL_START,
                'load_time' => $this->getAverageLoadTime(),
            ]
        ];

        return response()->json($metrics);
    }

    /**
     * Get database connection count
     */
    private function getDatabaseConnectionCount()
    {
        try {
            return DB::select('SELECT COUNT(*) as count FROM information_schema.processlist')[0]->count ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get average query time
     */
    private function getAverageQueryTime()
    {
        try {
            $result = DB::select('SHOW STATUS LIKE "Slow_queries"');
            return $result[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get slow queries
     */
    private function getSlowQueries()
    {
        try {
            $result = DB::select('SHOW STATUS LIKE "Slow_queries"');
            return $result[0]->Value ?? 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get cache hit rate
     */
    private function getCacheHitRate()
    {
        // This would need to be implemented based on your cache driver
        return 'N/A';
    }

    /**
     * Get cache memory usage
     */
    private function getCacheMemoryUsage()
    {
        if (config('cache.default') === 'redis') {
            try {
                $redis = app('redis');
                $info = $redis->info('memory');
                return $info['used_memory_human'] ?? 'N/A';
            } catch (\Exception $e) {
                return 'N/A';
            }
        }
        return 'N/A';
    }

    /**
     * Get total routes
     */
    private function getTotalRoutes()
    {
        return count(app('router')->getRoutes());
    }

    /**
     * Get admin routes count
     */
    private function getAdminRoutes()
    {
        $routes = app('router')->getRoutes();
        $adminRoutes = 0;
        
        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'admin')) {
                $adminRoutes++;
            }
        }
        
        return $adminRoutes;
    }

    /**
     * Get app routes count
     */
    private function getAppRoutes()
    {
        $routes = app('router')->getRoutes();
        $appRoutes = 0;
        
        foreach ($routes as $route) {
            if (str_starts_with($route->uri(), 'app')) {
                $appRoutes++;
            }
        }
        
        return $appRoutes;
    }

    /**
     * Get legacy routes count
     */
    private function getLegacyRoutes()
    {
        $routes = app('router')->getRoutes();
        $legacyRoutes = 0;
        
        foreach ($routes as $route) {
            if (str_starts_with($route->getName(), 'legacy.')) {
                $legacyRoutes++;
            }
        }
        
        return $legacyRoutes;
    }

    /**
     * Get total users
     */
    private function getTotalUsers()
    {
        return \App\Models\User::count();
    }

    /**
     * Get active users
     */
    private function getActiveUsers()
    {
        return \App\Models\User::where('is_active', true)->count();
    }

    /**
     * Get super admins
     */
    private function getSuperAdmins()
    {
        return \App\Models\User::whereHas('roles', function($query) {
            $query->where('name', 'super_admin');
        })->count();
    }

    /**
     * Get tenant users
     */
    private function getTenantUsers()
    {
        return \App\Models\User::whereNotNull('tenant_id')->count();
    }

    /**
     * Get average load time
     */
    private function getAverageLoadTime()
    {
        // This would need to be implemented with actual load time tracking
        return 'N/A';
    }

    /**
     * Clear system caches
     */
    public function clearCaches()
    {
        try {
            Artisan::call('config:clear');
            Artisan::call('route:clear');
            Artisan::call('view:clear');
            Artisan::call('cache:clear');
            
            return response()->json([
                'success' => true,
                'message' => 'All caches cleared successfully',
                'timestamp' => now()->toISOString()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear caches: ' . $e->getMessage(),
                'timestamp' => now()->toISOString()
            ], 500);
        }
    }

    /**
     * Get system health status
     */
    public function health()
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => now()->toISOString(),
            'checks' => [
                'database' => $this->checkDatabase(),
                'cache' => $this->checkCache(),
                'routes' => $this->checkRoutes(),
                'permissions' => $this->checkPermissions(),
            ]
        ];

        $allHealthy = collect($health['checks'])->every(fn($check) => $check['status'] === 'healthy');
        
        if (!$allHealthy) {
            $health['status'] = 'degraded';
        }

        return response()->json($health);
    }

    /**
     * Check database connectivity
     */
    private function checkDatabase()
    {
        try {
            DB::connection()->getPdo();
            return ['status' => 'healthy', 'message' => 'Database connection successful'];
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Database connection failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check cache functionality
     */
    private function checkCache()
    {
        try {
            Cache::put('health_check', 'test', 60);
            $value = Cache::get('health_check');
            Cache::forget('health_check');
            
            if ($value === 'test') {
                return ['status' => 'healthy', 'message' => 'Cache functionality working'];
            } else {
                return ['status' => 'unhealthy', 'message' => 'Cache read/write failed'];
            }
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Cache check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check routes functionality
     */
    private function checkRoutes()
    {
        try {
            $routes = app('router')->getRoutes();
            if (count($routes) > 0) {
                return ['status' => 'healthy', 'message' => 'Routes loaded successfully'];
            } else {
                return ['status' => 'unhealthy', 'message' => 'No routes found'];
            }
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Route check failed: ' . $e->getMessage()];
        }
    }

    /**
     * Check permissions system
     */
    private function checkPermissions()
    {
        try {
            $user = \App\Models\User::first();
            if ($user && method_exists($user, 'hasRole')) {
                return ['status' => 'healthy', 'message' => 'Permissions system working'];
            } else {
                return ['status' => 'unhealthy', 'message' => 'Permissions system not working'];
            }
        } catch (\Exception $e) {
            return ['status' => 'unhealthy', 'message' => 'Permission check failed: ' . $e->getMessage()];
        }
    }
}
