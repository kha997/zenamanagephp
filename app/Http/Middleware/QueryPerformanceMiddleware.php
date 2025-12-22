<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\QueryOptimizationService;

/**
 * Query Performance Monitoring Middleware
 * 
 * Monitors database query performance and logs slow queries
 * Helps identify N+1 query issues and performance bottlenecks
 */
class QueryPerformanceMiddleware
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
        // Enable query logging
        DB::enableQueryLog();
        
        $startTime = microtime(true);
        
        $response = $next($request);
        
        $endTime = microtime(true);
        $executionTime = $endTime - $startTime;
        
        // Get query log
        $queries = DB::getQueryLog();
        
        // Log performance metrics
        $this->logPerformanceMetrics($request, $queries, $executionTime);
        
        // Disable query logging
        DB::disableQueryLog();
        
        return $response;
    }

    /**
     * Log performance metrics
     */
    private function logPerformanceMetrics(Request $request, array $queries, float $executionTime): void
    {
        $queryCount = count($queries);
        $slowQueries = [];
        
        // Identify slow queries
        foreach ($queries as $query) {
            if ($query['time'] > 1000) { // Queries taking more than 1 second
                $slowQueries[] = [
                    'sql' => $query['query'],
                    'bindings' => $query['bindings'],
                    'time' => $query['time'],
                ];
            }
        }
        
        // Log performance summary
        Log::info('Request Performance Summary', [
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'execution_time' => round($executionTime * 1000, 2) . 'ms',
            'query_count' => $queryCount,
            'slow_queries' => count($slowQueries),
            'memory_usage' => memory_get_usage(true),
            'peak_memory' => memory_get_peak_usage(true),
        ]);
        
        // Log slow queries
        if (!empty($slowQueries)) {
            Log::warning('Slow Queries Detected', [
                'url' => $request->fullUrl(),
                'queries' => $slowQueries,
            ]);
        }
        
        // Log potential N+1 queries
        if ($queryCount > 20) { // Arbitrary threshold
            Log::warning('Potential N+1 Query Issue', [
                'url' => $request->fullUrl(),
                'query_count' => $queryCount,
                'queries' => array_map(function($q) {
                    return [
                        'sql' => $q['query'],
                        'time' => $q['time'],
                    ];
                }, $queries),
            ]);
        }
    }
}
