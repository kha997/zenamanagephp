<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class PerformanceMonitoringMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);
        
        // Enable query logging for this request
        DB::enableQueryLog();
        
        // Process the request
        $response = $next($request);
        
        // Calculate performance metrics
        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);
        
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = $endMemory - $startMemory;
        $queries = DB::getQueryLog();
        
        // Store metrics
        $this->storeMetrics($request, $executionTime, $memoryUsed, $queries);
        
        // Add performance headers
        $response->headers->set('X-Response-Time', round($executionTime, 2) . 'ms');
        $response->headers->set('X-Memory-Usage', round($memoryUsed / 1024 / 1024, 2) . 'MB');
        $response->headers->set('X-Query-Count', count($queries));
        
        return $response;
    }
    
    /**
     * Store performance metrics
     */
    private function storeMetrics(Request $request, float $executionTime, int $memoryUsed, array $queries): void
    {
        $route = $request->route()?->getName() ?? $request->path();
        $method = $request->method();
        $timestamp = now()->toDateString();
        
        // Store request count
        $requestCountKey = "performance:requests:{$timestamp}";
        Cache::increment($requestCountKey);
        
        // Store response time
        $responseTimeKey = "performance:response_time:{$timestamp}";
        $this->updateAverage($responseTimeKey, $executionTime);
        
        // Store memory usage
        $memoryKey = "performance:memory:{$timestamp}";
        $this->updateAverage($memoryKey, $memoryUsed);
        
        // Store query count
        $queryCountKey = "performance:queries:{$timestamp}";
        $this->updateAverage($queryCountKey, count($queries));
        
        // Store route-specific metrics
        $routeKey = "performance:route:{$method}:{$route}:{$timestamp}";
        Cache::increment($routeKey);
        
        // Log slow requests
        if ($executionTime > 1000) { // 1 second
            Log::warning('Slow Request Detected', [
                'route' => $route,
                'method' => $method,
                'execution_time' => $executionTime,
                'memory_used' => $memoryUsed,
                'query_count' => count($queries),
                'queries' => $queries,
            ]);
        }
        
        // Log high memory usage
        if ($memoryUsed > 50 * 1024 * 1024) { // 50MB
            Log::warning('High Memory Usage Detected', [
                'route' => $route,
                'method' => $method,
                'memory_used' => $memoryUsed,
                'execution_time' => $executionTime,
            ]);
        }
    }
    
    /**
     * Update running average for a metric
     */
    private function updateAverage(string $key, float $value): void
    {
        $current = Cache::get($key, ['sum' => 0, 'count' => 0]);
        $current['sum'] += $value;
        $current['count']++;
        Cache::put($key, $current, 86400); // 24 hours
    }
}