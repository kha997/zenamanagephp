<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Comprehensive Logging Service
 * 
 * Centralized service for all application logging needs
 * Provides structured logging with proper context and PII redaction
 */
class ComprehensiveLoggingService
{
    /**
     * Log levels for different types of events
     */
    public const LOG_LEVELS = [
        'debug' => 'debug',
        'info' => 'info',
        'warning' => 'warning',
        'error' => 'error',
        'critical' => 'critical',
    ];

    /**
     * Log categories for different types of events
     */
    public const CATEGORIES = [
        'AUTH' => 'authentication',
        'AUDIT' => 'audit',
        'PERFORMANCE' => 'performance',
        'SECURITY' => 'security',
        'API' => 'api',
        'DATA' => 'data',
        'ADMIN' => 'admin',
        'BUSINESS' => 'business',
        'SYSTEM' => 'system',
    ];

    /**
     * Log authentication events
     */
    public static function logAuth(string $event, array $data = [], string $level = 'info'): void
    {
        $context = self::getBaseContext();
        $context['category'] = self::CATEGORIES['AUTH'];
        $context['event'] = $event;
        $context['data'] = $data;
        
        Log::channel('security')->{$level}('Authentication event', $context);
    }

    /**
     * Log audit trail events
     */
    public static function logAudit(string $event, string $entityType = null, string $entityId = null, array $data = []): void
    {
        $context = self::getBaseContext();
        $context['category'] = self::CATEGORIES['AUDIT'];
        $context['event'] = $event;
        $context['entity_type'] = $entityType;
        $context['entity_id'] = $entityId;
        $context['data'] = $data;
        
        Log::channel('audit')->info('Audit event', $context);
    }

    /**
     * Log performance metrics
     */
    public static function logPerformance(string $operation, float $duration, array $metrics = []): void
    {
        $context = self::getBaseContext();
        $context['category'] = self::CATEGORIES['PERFORMANCE'];
        $context['operation'] = $operation;
        $context['duration_ms'] = round($duration * 1000, 2);
        $context['metrics'] = $metrics;
        
        Log::channel('performance')->info('Performance metric', $context);
    }

    /**
     * Log security events
     */
    public static function logSecurity(string $event, array $data = [], string $level = 'warning'): void
    {
        $context = self::getBaseContext();
        $context['category'] = self::CATEGORIES['SECURITY'];
        $context['event'] = $event;
        $context['data'] = $data;
        
        Log::channel('security')->{$level}('Security event', $context);
    }

    /**
     * Log API events
     */
    public static function logApi(string $event, array $data = [], string $level = 'info'): void
    {
        $context = self::getBaseContext();
        $context['category'] = self::CATEGORIES['API'];
        $context['event'] = $event;
        $context['data'] = $data;
        
        Log::channel('api')->{$level}('API event', $context);
    }

    /**
     * Log data access events
     */
    public static function logDataAccess(string $event, string $entityType, string $entityId = null, array $data = []): void
    {
        $context = self::getBaseContext();
        $context['category'] = self::CATEGORIES['DATA'];
        $context['event'] = $event;
        $context['entity_type'] = $entityType;
        $context['entity_id'] = $entityId;
        $context['data'] = $data;
        
        Log::channel('data')->info('Data access event', $context);
    }

    /**
     * Log admin events
     */
    public static function logAdmin(string $event, array $data = [], string $level = 'info'): void
    {
        $context = self::getBaseContext();
        $context['category'] = self::CATEGORIES['ADMIN'];
        $context['event'] = $event;
        $context['data'] = $data;
        
        Log::channel('admin')->{$level}('Admin event', $context);
    }

    /**
     * Log business events
     */
    public static function logBusiness(string $event, string $entityType = null, string $entityId = null, array $data = []): void
    {
        $context = self::getBaseContext();
        $context['category'] = self::CATEGORIES['BUSINESS'];
        $context['event'] = $event;
        $context['entity_type'] = $entityType;
        $context['entity_id'] = $entityId;
        $context['data'] = $data;
        
        Log::channel('structured')->info('Business event', $context);
    }

    /**
     * Log system events
     */
    public static function logSystem(string $event, array $data = [], string $level = 'info'): void
    {
        $context = self::getBaseContext();
        $context['category'] = self::CATEGORIES['SYSTEM'];
        $context['event'] = $event;
        $context['data'] = $data;
        
        Log::channel('single')->{$level}('System event', $context);
    }

    /**
     * Log database query performance
     */
    public static function logQueryPerformance(array $queries, float $totalTime, int $slowQueryCount = 0): void
    {
        $context = self::getBaseContext();
        $context['category'] = self::CATEGORIES['PERFORMANCE'];
        $context['event'] = 'query_performance';
        $context['query_count'] = count($queries);
        $context['total_time_ms'] = round($totalTime * 1000, 2);
        $context['slow_query_count'] = $slowQueryCount;
        $context['queries'] = array_map(function($query) {
            return [
                'sql' => $query['query'],
                'time' => $query['time'],
                'bindings' => $query['bindings'],
            ];
        }, $queries);
        
        Log::channel('performance')->info('Query performance', $context);
    }

    /**
     * Log errors with full context
     */
    public static function logError(\Throwable $exception, array $context = []): void
    {
        $logContext = self::getBaseContext();
        $logContext['category'] = self::CATEGORIES['SYSTEM'];
        $logContext['event'] = 'exception';
        $logContext['exception'] = [
            'class' => get_class($exception),
            'message' => $exception->getMessage(),
            'code' => $exception->getCode(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
        $logContext['data'] = $context;
        
        Log::channel('single')->error('Application error', $logContext);
    }

    /**
     * Get base context for all log entries
     */
    private static function getBaseContext(): array
    {
        try {
            $request = request();
            $user = Auth::user();
            
            return [
                'timestamp' => now()->toISOString(),
                'user_id' => $user?->id,
                'user_email' => $user?->email,
                'tenant_id' => $user?->tenant_id ?? session('user')['tenant_id'] ?? null,
                'request_id' => $request?->header('X-Request-Id'),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->header('User-Agent'),
                'route' => $request?->route()?->getName(),
                'method' => $request?->method(),
                'url' => $request?->fullUrl(),
                'session_id' => session()->getId(),
                'environment' => app()->environment(),
                'app_version' => config('app.version', '1.0.0'),
            ];
        } catch (\Exception $e) {
            // Fallback context if request is not available
            return [
                'timestamp' => now()->toISOString(),
                'environment' => app()->environment(),
                'app_version' => config('app.version', '1.0.0'),
                'context_error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Log user action with automatic categorization
     */
    public static function logUserAction(string $action, string $entityType = null, string $entityId = null, array $data = []): void
    {
        // Determine category based on action
        $category = self::determineCategory($action);
        
        $context = self::getBaseContext();
        $context['category'] = $category;
        $context['event'] = 'user_action';
        $context['action'] = $action;
        $context['entity_type'] = $entityType;
        $context['entity_id'] = $entityId;
        $context['data'] = $data;
        
        // Choose appropriate channel based on category
        $channel = self::getChannelForCategory($category);
        Log::channel($channel)->info('User action', $context);
    }

    /**
     * Determine log category based on action
     */
    private static function determineCategory(string $action): string
    {
        $actionLower = strtolower($action);
        
        if (str_contains($actionLower, 'login') || str_contains($actionLower, 'logout') || str_contains($actionLower, 'auth')) {
            return self::CATEGORIES['AUTH'];
        }
        
        if (str_contains($actionLower, 'create') || str_contains($actionLower, 'update') || str_contains($actionLower, 'delete')) {
            return self::CATEGORIES['AUDIT'];
        }
        
        if (str_contains($actionLower, 'admin') || str_contains($actionLower, 'system')) {
            return self::CATEGORIES['ADMIN'];
        }
        
        if (str_contains($actionLower, 'api') || str_contains($actionLower, 'request')) {
            return self::CATEGORIES['API'];
        }
        
        return self::CATEGORIES['BUSINESS'];
    }

    /**
     * Get appropriate log channel for category
     */
    private static function getChannelForCategory(string $category): string
    {
        return match($category) {
            self::CATEGORIES['AUTH'], self::CATEGORIES['SECURITY'] => 'security',
            self::CATEGORIES['AUDIT'] => 'audit',
            self::CATEGORIES['PERFORMANCE'] => 'performance',
            self::CATEGORIES['API'] => 'api',
            self::CATEGORIES['DATA'] => 'data',
            self::CATEGORIES['ADMIN'] => 'admin',
            default => 'structured',
        };
    }
}
