<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cache;

class ProductionConfigService
{
    /**
     * Validate production configuration
     */
    public function validateProductionConfig(): array
    {
        $errors = [];
        $warnings = [];

        // Check critical production settings
        if (app()->environment('production')) {
            // Debug mode should be disabled
            if (config('app.debug')) {
                $errors[] = 'APP_DEBUG must be false in production';
            }

            // App key should be set
            if (empty(config('app.key'))) {
                $errors[] = 'APP_KEY must be set in production';
            }

            // Database should not be SQLite in production
            if (config('database.default') === 'sqlite') {
                $warnings[] = 'Consider using MySQL/PostgreSQL instead of SQLite for production';
            }

            // Cache should not be file in production
            if (config('cache.default') === 'file') {
                $warnings[] = 'Consider using Redis/Memcached instead of file cache for production';
            }

            // Session should not be file in production
            if (config('session.driver') === 'file') {
                $warnings[] = 'Consider using Redis/database instead of file sessions for production';
            }

            // Log level should not be debug in production
            if (config('logging.level') === 'debug') {
                $warnings[] = 'Consider setting LOG_LEVEL to warning or error in production';
            }

            // Check security settings
            if (!config('session.encrypt')) {
                $warnings[] = 'Session encryption should be enabled in production';
            }

            if (!config('session.expire_on_close')) {
                $warnings[] = 'Sessions should expire on browser close in production';
            }

            // Check performance settings
            if (!config('app.opcache_enable', false)) {
                $warnings[] = 'OPcache should be enabled for better performance';
            }

            // Check monitoring
            if (empty(config('sentry.laravel_dsn'))) {
                $warnings[] = 'Consider setting up Sentry for error monitoring';
            }
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'is_valid' => empty($errors),
        ];
    }

    /**
     * Get production readiness checklist
     */
    public function getProductionReadinessChecklist(): array
    {
        return [
            'Environment' => [
                'APP_ENV=production' => app()->environment('production'),
                'APP_DEBUG=false' => !config('app.debug'),
                'APP_KEY set' => !empty(config('app.key')),
                'APP_URL set' => !empty(config('app.url')),
            ],
            'Database' => [
                'Database configured' => !empty(config('database.connections.mysql.host')),
                'Not using SQLite' => config('database.default') !== 'sqlite',
                'Connection pooling' => config('database.connections.mysql.options.persistent', false),
            ],
            'Cache' => [
                'Cache configured' => !empty(config('cache.stores.redis.host')),
                'Not using file cache' => config('cache.default') !== 'file',
                'Redis configured' => config('cache.default') === 'redis',
            ],
            'Session' => [
                'Session encryption' => config('session.encrypt'),
                'Secure cookies' => config('session.secure'),
                'HttpOnly cookies' => config('session.http_only'),
                'Not using file sessions' => config('session.driver') !== 'file',
            ],
            'Security' => [
                'CSRF protection' => class_exists('App\Http\Middleware\VerifyCsrfToken'),
                'Security headers' => class_exists('App\Http\Middleware\SecurityHeadersMiddleware'),
                'Rate limiting' => class_exists('App\Http\Middleware\RateLimitingMiddleware'),
                'Input validation' => class_exists('App\Http\Middleware\InputValidationMiddleware'),
                'Secure sessions' => class_exists('App\Http\Middleware\SecureSessionMiddleware'),
            ],
            'Performance' => [
                'OPcache enabled' => config('app.opcache_enable', false),
                'Asset optimization' => config('app.asset_optimization', false),
                'Database optimization' => config('app.db_optimization', false),
                'Cache optimization' => config('app.cache_optimization', false),
            ],
            'Monitoring' => [
                'Error monitoring' => !empty(config('sentry.laravel_dsn')),
                'Performance monitoring' => class_exists('App\Http\Middleware\PerformanceMonitoringMiddleware'),
                'Audit logging' => class_exists('App\Services\AuditLogService'),
                'Health checks' => class_exists('App\Http\Controllers\Api\V1\HealthController'),
            ],
            'Backup' => [
                'Backup configured' => !empty(config('backup.disk')),
                'Database backup' => config('backup.databases', false),
                'File backup' => config('backup.files', false),
            ],
        ];
    }

    /**
     * Optimize configuration for production
     */
    public function optimizeForProduction(): array
    {
        $optimizations = [];

        // Enable OPcache
        if (!config('app.opcache_enable')) {
            $optimizations[] = 'Enable OPcache for better performance';
        }

        // Enable asset optimization
        if (!config('app.asset_optimization')) {
            $optimizations[] = 'Enable asset minification and compression';
        }

        // Enable database query caching
        if (!config('database.query_cache')) {
            $optimizations[] = 'Enable database query caching';
        }

        // Enable cache compression
        if (!config('cache.compression')) {
            $optimizations[] = 'Enable cache compression';
        }

        // Enable response compression
        if (!config('app.response_compression')) {
            $optimizations[] = 'Enable response compression';
        }

        return $optimizations;
    }

    /**
     * Get production performance metrics
     */
    public function getProductionMetrics(): array
    {
        return [
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'execution_time' => microtime(true) - LARAVEL_START,
            'cache_hits' => Cache::getStore()->getStats()['hits'] ?? 0,
            'cache_misses' => Cache::getStore()->getStats()['misses'] ?? 0,
            'database_connections' => app('db')->getConnections(),
            'queue_jobs' => app('queue')->size(),
        ];
    }

    /**
     * Check production health
     */
    public function checkProductionHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'checks' => [],
        ];

        // Database connectivity
        try {
            app('db')->connection()->getPdo();
            $health['checks']['database'] = 'ok';
        } catch (\Exception $e) {
            $health['checks']['database'] = 'error';
            $health['status'] = 'unhealthy';
        }

        // Cache connectivity
        try {
            Cache::put('health_check', 'ok', 60);
            $health['checks']['cache'] = Cache::get('health_check') === 'ok' ? 'ok' : 'error';
        } catch (\Exception $e) {
            $health['checks']['cache'] = 'error';
            $health['status'] = 'unhealthy';
        }

        // Queue connectivity
        try {
            app('queue')->push('health_check');
            $health['checks']['queue'] = 'ok';
        } catch (\Exception $e) {
            $health['checks']['queue'] = 'error';
            $health['status'] = 'unhealthy';
        }

        // Disk space
        $diskFree = disk_free_space(storage_path());
        $diskTotal = disk_total_space(storage_path());
        $diskUsage = (($diskTotal - $diskFree) / $diskTotal) * 100;
        
        if ($diskUsage > 90) {
            $health['checks']['disk'] = 'warning';
            $health['status'] = 'degraded';
        } else {
            $health['checks']['disk'] = 'ok';
        }

        // Memory usage
        $memoryUsage = (memory_get_usage(true) / memory_get_peak_usage(true)) * 100;
        
        if ($memoryUsage > 80) {
            $health['checks']['memory'] = 'warning';
            $health['status'] = 'degraded';
        } else {
            $health['checks']['memory'] = 'ok';
        }

        return $health;
    }
}
