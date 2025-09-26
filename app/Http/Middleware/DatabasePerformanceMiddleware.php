<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\QueryLoggingService;

/**
 * Database Query Performance Monitoring Middleware
 * 
 * Monitors and logs slow database queries for optimization
 */
class DatabasePerformanceMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $queryLoggingService = app(QueryLoggingService::class);
        
        // Start query logging
        $queryLoggingService->startLogging();
        
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Stop query logging and save logs
        $queryLoggingService->stopLogging($request);
        
        // Log slow requests
        if ($executionTime > 2.0) {
            $this->logSlowRequest($request, $executionTime);
        }
        
        return $response;
    }

    /**
     * Log slow request details
     */
    private function logSlowRequest(Request $request, float $executionTime): void
    {
        Log::warning('Slow request detected', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'execution_time' => $executionTime,
            'user_id' => Auth::id(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
    }
}
