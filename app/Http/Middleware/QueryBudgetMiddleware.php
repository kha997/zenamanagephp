<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Query Budget Middleware
 * 
 * Limits the number of database queries per endpoint to prevent N+1 queries
 * and ensure performance budgets are met.
 * 
 * Default limit: 12 queries per endpoint
 * Can be configured per route: query-budget:20
 */
class QueryBudgetMiddleware
{
    /**
     * Default query budget (queries per request)
     */
    private const DEFAULT_QUERY_BUDGET = 12;

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  int  $budget  Query budget for this route (default: 12)
     */
    public function handle(Request $request, Closure $next, int $budget = self::DEFAULT_QUERY_BUDGET): Response
    {
        // Enable query logging
        DB::enableQueryLog();
        
        // Process request
        $response = $next($request);
        
        // Get query count
        $queries = DB::getQueryLog();
        $queryCount = count($queries);
        
        // Check if budget exceeded
        if ($queryCount > $budget) {
            Log::warning('Query budget exceeded', [
                'route' => $request->route()?->getName() ?? $request->path(),
                'method' => $request->method(),
                'query_count' => $queryCount,
                'budget' => $budget,
                'queries' => array_map(function ($query) {
                    return [
                        'sql' => $query['query'],
                        'bindings' => $query['bindings'],
                        'time' => $query['time'] ?? null,
                    ];
                }, $queries),
                'traceId' => $request->header('X-Request-Id', uniqid('req_', true)),
            ]);
            
            // In production, you might want to return an error or alert
            // For now, we just log the warning
        } else {
            Log::debug('Query budget check passed', [
                'route' => $request->route()?->getName() ?? $request->path(),
                'query_count' => $queryCount,
                'budget' => $budget,
            ]);
        }
        
        // Add query count to response header for monitoring
        $response->headers->set('X-Query-Count', (string) $queryCount);
        $response->headers->set('X-Query-Budget', (string) $budget);
        
        // Disable query logging to avoid memory issues
        DB::disableQueryLog();
        
        return $response;
    }
}
