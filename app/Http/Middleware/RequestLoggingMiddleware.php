<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\ComprehensiveLoggingService;
use Illuminate\Support\Facades\DB;

/**
 * Request Logging Middleware
 * 
 * Automatically logs all HTTP requests and responses with performance metrics
 * Includes query performance monitoring and error tracking
 */
class RequestLoggingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // Enable query logging for performance monitoring
        DB::enableQueryLog();
        
        try {
            $response = $next($request);
            
            // Log successful request
            $this->logRequest($request, $response, $startTime, $startMemory);
            
            return $response;
            
        } catch (\Throwable $exception) {
            // Log error
            $this->logError($request, $exception, $startTime, $startMemory);
            
            throw $exception;
        }
    }

    /**
     * Log successful request
     */
    private function logRequest(Request $request, $response, float $startTime, int $startMemory): void
    {
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        $executionTime = $endTime - $startTime;
        $memoryUsed = $endMemory - $startMemory;
        
        // Get query performance data
        $queries = DB::getQueryLog();
        $slowQueries = array_filter($queries, fn($q) => $q['time'] > 1000);
        
        // Determine log level based on performance
        $logLevel = $this->determineLogLevel($executionTime, count($queries), count($slowQueries));
        
        // Log request details
        ComprehensiveLoggingService::logApi('request_completed', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'status_code' => $response->getStatusCode(),
            'execution_time_ms' => round($executionTime * 1000, 2),
            'memory_used_bytes' => $memoryUsed,
            'query_count' => count($queries),
            'slow_query_count' => count($slowQueries),
            'response_size_bytes' => strlen($response->getContent()),
            'user_agent' => $request->header('User-Agent'),
            'referer' => $request->header('Referer'),
        ], $logLevel);
        
        // Log query performance if there are queries
        if (!empty($queries)) {
            ComprehensiveLoggingService::logQueryPerformance($queries, $executionTime, count($slowQueries));
        }
        
        // Log slow requests separately
        if ($executionTime > 2.0) { // More than 2 seconds
            ComprehensiveLoggingService::logPerformance('slow_request', $executionTime, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'query_count' => count($queries),
                'memory_used' => $memoryUsed,
            ]);
        }
    }

    /**
     * Log error
     */
    private function logError(Request $request, \Throwable $exception, float $startTime, int $startMemory): void
    {
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Get query performance data
        $queries = DB::getQueryLog();
        
        ComprehensiveLoggingService::logError($exception, [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route' => $request->route()?->getName(),
            'execution_time_ms' => round($executionTime * 1000, 2),
            'query_count' => count($queries),
            'user_agent' => $request->header('User-Agent'),
            'referer' => $request->header('Referer'),
        ]);
    }

    /**
     * Determine log level based on performance metrics
     */
    private function determineLogLevel(float $executionTime, int $queryCount, int $slowQueryCount): string
    {
        // Critical level for very slow requests
        if ($executionTime > 5.0 || $slowQueryCount > 5) {
            return 'critical';
        }
        
        // Error level for slow requests
        if ($executionTime > 2.0 || $slowQueryCount > 0) {
            return 'error';
        }
        
        // Warning level for requests with many queries
        if ($queryCount > 20) {
            return 'warning';
        }
        
        // Info level for normal requests
        return 'info';
    }
}
