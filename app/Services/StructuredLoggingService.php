<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;

class StructuredLoggingService
{
    /**
     * Log application events with structured data
     */
    public static function logEvent(string $event, array $data = []): void
    {
        $context = self::getBaseContext();
        $context['event'] = $event;
        $context['data'] = $data;
        
        Log::info('Application event', $context);
    }

    /**
     * Log user actions with structured data
     */
    public static function logUserAction(string $action, array $data = []): void
    {
        $context = self::getBaseContext();
        $context['action'] = $action;
        $context['data'] = $data;
        
        Log::info('User action', $context);
    }

    /**
     * Log business events with structured data
     */
    public static function logBusinessEvent(string $event, string $entityType, string $entityId, array $data = []): void
    {
        $context = self::getBaseContext();
        $context['event'] = $event;
        $context['entity_type'] = $entityType;
        $context['entity_id'] = $entityId;
        $context['data'] = $data;
        
        Log::info('Business event', $context);
    }

    /**
     * Log performance metrics
     */
    public static function logPerformance(string $operation, float $duration, array $metrics = []): void
    {
        $context = self::getBaseContext();
        $context['operation'] = $operation;
        $context['duration_ms'] = round($duration * 1000, 2);
        $context['metrics'] = $metrics;
        
        Log::info('Performance metric', $context);
    }

    /**
     * Log security events
     */
    public static function logSecurityEvent(string $event, array $data = []): void
    {
        $context = self::getBaseContext();
        $context['event'] = $event;
        $context['data'] = $data;
        
        Log::warning('Security event', $context);
    }

    /**
     * Log error with structured data
     */
    public static function logError(string $message, \Throwable $exception = null, array $data = []): void
    {
        $context = self::getBaseContext();
        $context['message'] = $message;
        $context['data'] = $data;
        
        if ($exception) {
            $context['exception'] = [
                'class' => get_class($exception),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
        }
        
        Log::error('Application error', $context);
    }

    /**
     * Log API requests with structured data
     */
    public static function logApiRequest(Request $request, int $statusCode, float $duration, array $data = []): void
    {
        $context = self::getBaseContext();
        $context['method'] = $request->method();
        $context['url'] = $request->fullUrl();
        $context['status_code'] = $statusCode;
        $context['duration_ms'] = round($duration * 1000, 2);
        $context['ip'] = $request->ip();
        $context['user_agent'] = $request->userAgent();
        $context['data'] = $data;
        
        Log::info('API request', $context);
    }

    /**
     * Log database operations
     */
    public static function logDatabaseOperation(string $operation, string $table, array $data = []): void
    {
        $context = self::getBaseContext();
        $context['operation'] = $operation;
        $context['table'] = $table;
        $context['data'] = $data;
        
        Log::debug('Database operation', $context);
    }

    /**
     * Log cache operations
     */
    public static function logCacheOperation(string $operation, string $key, bool $hit = null, array $data = []): void
    {
        $context = self::getBaseContext();
        $context['operation'] = $operation;
        $context['key'] = $key;
        $context['hit'] = $hit;
        $context['data'] = $data;
        
        Log::debug('Cache operation', $context);
    }

    /**
     * Log external API calls
     */
    public static function logExternalApiCall(string $service, string $endpoint, int $statusCode, float $duration, array $data = []): void
    {
        $context = self::getBaseContext();
        $context['service'] = $service;
        $context['endpoint'] = $endpoint;
        $context['status_code'] = $statusCode;
        $context['duration_ms'] = round($duration * 1000, 2);
        $context['data'] = $data;
        
        Log::info('External API call', $context);
    }

    /**
     * Get base context for all logs
     */
    protected static function getBaseContext(): array
    {
        $context = [
            'timestamp' => now()->toISOString(),
            'environment' => app()->environment(),
            'app_version' => config('app.version', '1.0.0'),
        ];

        // Add correlation ID if available
        $correlationId = CorrelationIdService::getCurrent();
        if ($correlationId) {
            $context['correlation_id'] = $correlationId;
        }

        // Add user context if authenticated (only in HTTP context)
        if (!app()->runningInConsole()) {
            try {
                if (Auth::check()) {
                    $user = Auth::user();
                    $context['user'] = [
                        'id' => $user->id,
                        'email' => $user->email,
                        'tenant_id' => $user->tenant_id ?? null,
                    ];
                }
            } catch (\Exception $e) {
                // Auth not available in this context
            }
        }

        // Add request context if available
        try {
            $request = app('request');
            if ($request) {
                $context['request'] = [
                    'method' => $request->method(),
                    'url' => $request->fullUrl(),
                    'ip' => $request->ip(),
                ];
            }
        } catch (\Exception $e) {
            // Request not available in this context (e.g., CLI)
        }

        return $context;
    }

    /**
     * Log system metrics
     */
    public static function logSystemMetrics(): void
    {
        $context = self::getBaseContext();
        $context['metrics'] = [
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'peak_memory_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            'cpu_usage_percent' => self::getCpuUsage(),
            'disk_usage_percent' => self::getDiskUsage(),
            'load_average' => sys_getloadavg(),
        ];
        
        Log::info('System metrics', $context);
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
}
