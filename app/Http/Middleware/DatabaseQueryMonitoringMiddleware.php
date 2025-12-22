<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DatabaseQueryMonitoringMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id', uniqid('req_', true));
        $route = $request->route()?->getName() ?? $request->path();
        
        // Enable query logging
        DB::enableQueryLog();
        
        $startTime = microtime(true);
        
        // Process the request
        $response = $next($request);
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        // Get query log
        $queries = DB::getQueryLog();
        
        // Analyze queries
        $queryAnalysis = $this->analyzeQueries($queries, $executionTime);
        
        // Log query metrics
        $this->logQueryMetrics([
            'request_id' => $requestId,
            'route' => $route,
            'total_queries' => count($queries),
            'total_execution_time_ms' => $executionTime,
            'query_analysis' => $queryAnalysis,
            'timestamp' => now()->toISOString(),
        ]);
        
        // Add query metrics to response headers
        $response->headers->set('X-Total-Queries', count($queries));
        $response->headers->set('X-Query-Time', $queryAnalysis['total_query_time'] . 'ms');
        $response->headers->set('X-Slow-Queries', $queryAnalysis['slow_queries_count']);
        
        return $response;
    }
    
    /**
     * Analyze database queries
     */
    private function analyzeQueries(array $queries, float $totalExecutionTime): array
    {
        $totalQueryTime = 0;
        $slowQueries = [];
        $duplicateQueries = [];
        $queryCounts = [];
        $nPlusOneQueries = [];
        
        foreach ($queries as $query) {
            $queryTime = $query['time'];
            $totalQueryTime += $queryTime;
            
            // Identify slow queries (> 100ms)
            if ($queryTime > 100) {
                $slowQueries[] = [
                    'sql' => $query['query'],
                    'time' => $queryTime,
                    'bindings' => $query['bindings'],
                ];
            }
            
            // Count duplicate queries
            $queryHash = md5($query['query']);
            if (!isset($queryCounts[$queryHash])) {
                $queryCounts[$queryHash] = 0;
            }
            $queryCounts[$queryHash]++;
            
            // Detect N+1 queries (same query executed multiple times)
            if ($queryCounts[$queryHash] > 5) {
                $nPlusOneQueries[] = [
                    'sql' => $query['query'],
                    'count' => $queryCounts[$queryHash],
                    'total_time' => $queryTime * $queryCounts[$queryHash],
                ];
            }
        }
        
        return [
            'total_query_time' => round($totalQueryTime, 2),
            'slow_queries_count' => count($slowQueries),
            'slow_queries' => $slowQueries,
            'duplicate_queries_count' => count(array_filter($queryCounts, fn($count) => $count > 1)),
            'n_plus_one_queries_count' => count($nPlusOneQueries),
            'n_plus_one_queries' => $nPlusOneQueries,
            'avg_query_time' => count($queries) > 0 ? round($totalQueryTime / count($queries), 2) : 0,
            'query_efficiency_score' => $this->calculateQueryEfficiencyScore($queries, $totalExecutionTime),
        ];
    }
    
    /**
     * Calculate query efficiency score
     */
    private function calculateQueryEfficiencyScore(array $queries, float $totalExecutionTime): float
    {
        if (empty($queries)) {
            return 100.0;
        }
        
        $score = 100.0;
        
        // Deduct points for slow queries
        $slowQueries = array_filter($queries, fn($q) => $q['time'] > 100);
        $score -= count($slowQueries) * 5;
        
        // Deduct points for too many queries
        if (count($queries) > 20) {
            $score -= (count($queries) - 20) * 2;
        }
        
        // Deduct points for N+1 queries
        $queryCounts = [];
        foreach ($queries as $query) {
            $queryHash = md5($query['query']);
            $queryCounts[$queryHash] = ($queryCounts[$queryHash] ?? 0) + 1;
        }
        
        $nPlusOneCount = count(array_filter($queryCounts, fn($count) => $count > 5));
        $score -= $nPlusOneCount * 10;
        
        return max(0, $score);
    }
    
    /**
     * Log query metrics
     */
    private function logQueryMetrics(array $metrics): void
    {
        $logLevel = $this->getLogLevel($metrics['query_analysis']);
        
        Log::channel('database')->log($logLevel, 'Database query metrics', [
            'type' => 'query_metrics',
            'metrics' => $metrics,
        ]);
        
        // Log slow queries separately
        if ($metrics['query_analysis']['slow_queries_count'] > 0) {
            Log::channel('slow_queries')->warning('Slow queries detected', [
                'type' => 'slow_queries',
                'metrics' => $metrics,
            ]);
        }
        
        // Log N+1 queries
        if ($metrics['query_analysis']['n_plus_one_queries_count'] > 0) {
            Log::channel('n_plus_one')->warning('N+1 queries detected', [
                'type' => 'n_plus_one_queries',
                'metrics' => $metrics,
            ]);
        }
    }
    
    /**
     * Get log level based on query analysis
     */
    private function getLogLevel(array $queryAnalysis): string
    {
        if ($queryAnalysis['slow_queries_count'] > 0 || $queryAnalysis['n_plus_one_queries_count'] > 0) {
            return 'warning';
        }
        
        if ($queryAnalysis['query_efficiency_score'] < 70) {
            return 'warning';
        }
        
        return 'info';
    }
}
