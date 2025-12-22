<?php declare(strict_types=1);

namespace App\Logging;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\JsonFormatter;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\WebProcessor;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Performance Logger
 * 
 * Custom logger for performance metrics with structured JSON output
 * Includes timing, memory usage, and query performance data
 */
class PerformanceLogger
{
    /**
     * Create a custom Monolog instance for performance logging.
     */
    public function __invoke(array $config): Logger
    {
        $logger = new Logger('performance');
        
        // Create handler with JSON formatting
        $handler = new StreamHandler($config['path'], $config['level']);
        $handler->setFormatter(new JsonFormatter());
        
        $logger->pushHandler($handler);
        
        // Add processors for context enrichment
        $logger->pushProcessor(new UidProcessor());
        $logger->pushProcessor(new WebProcessor());
        $logger->pushProcessor([$this, 'addPerformanceContext']);
        
        return $logger;
    }

    /**
     * Add performance-specific context to log records.
     */
    public function addPerformanceContext(array $record): array
    {
        try {
            $request = request();
            $user = Auth::user();
            
            $record['extra']['performance'] = [
                'timestamp' => now()->toISOString(),
                'user_id' => $user?->id,
                'tenant_id' => $user?->tenant_id ?? session('user')['tenant_id'] ?? null,
                'request_id' => $request?->header('X-Request-Id'),
                'ip_address' => $request?->ip(),
                'route' => $request?->route()?->getName(),
                'method' => $request?->method(),
                'url' => $request?->fullUrl(),
                'memory_usage' => memory_get_usage(true),
                'memory_peak' => memory_get_peak_usage(true),
                'execution_time' => microtime(true) - (defined('LARAVEL_START') ? LARAVEL_START : $_SERVER['REQUEST_TIME_FLOAT']),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
            ];
            
            // Add performance metrics if available
            if (isset($record['context']['operation'])) {
                $record['extra']['performance']['operation'] = $record['context']['operation'];
            }
            
            if (isset($record['context']['duration_ms'])) {
                $record['extra']['performance']['duration_ms'] = $record['context']['duration_ms'];
            }
            
            if (isset($record['context']['metrics'])) {
                $record['extra']['performance']['metrics'] = $record['context']['metrics'];
            }
            
            // Add database metrics if available
            if (isset($record['context']['query_count'])) {
                $record['extra']['performance']['query_count'] = $record['context']['query_count'];
            }
            
            if (isset($record['context']['slow_queries'])) {
                $record['extra']['performance']['slow_queries'] = $record['context']['slow_queries'];
            }
            
        } catch (\Exception $e) {
            // Don't let logging errors break the application
            $record['extra']['performance_error'] = $e->getMessage();
        }
        
        return $record;
    }
}
