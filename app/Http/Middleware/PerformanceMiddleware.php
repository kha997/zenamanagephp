<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class PerformanceMiddleware
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
        $startMemory = memory_get_usage();
        
        // Process the request
        $response = $next($request);
        
        // Calculate performance metrics
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        
        $duration = round(($endTime - $startTime) * 1000, 2); // Convert to milliseconds
        $memoryUsed = round(($endMemory - $startMemory) / 1024 / 1024, 2); // Convert to MB
        
        // Log performance metrics
        $this->logPerformanceMetrics($request, $response, $duration, $memoryUsed);
        
        // Add performance headers
        $response->headers->set('X-Response-Time', $duration . 'ms');
        $response->headers->set('X-Memory-Usage', $memoryUsed . 'MB');
        
        // Check for slow requests
        if ($duration > 1000) { // More than 1 second
            Log::warning('Slow request detected', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'duration_ms' => $duration,
                'memory_mb' => $memoryUsed,
                'user_id' => Auth::id(),
                'tenant_id' => Auth::user()?->tenant_id
            ]);
        }
        
        return $response;
    }

    /**
     * Log performance metrics.
     */
    protected function logPerformanceMetrics(Request $request, $response, float $duration, float $memoryUsed): void
    {
        $user = Auth::user();
        
        Log::channel('performance')->info('Performance Metrics', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'memory_mb' => $memoryUsed,
            'user_id' => $user?->id,
            'tenant_id' => $user?->tenant_id,
            'timestamp' => now()->toISOString(),
            'peak_memory_mb' => round(memory_get_peak_usage() / 1024 / 1024, 2)
        ]);
        
        // Store metrics in cache for monitoring
        $this->storePerformanceMetrics($request, $duration, $memoryUsed);
    }

    /**
     * Store performance metrics in cache for monitoring.
     */
    protected function storePerformanceMetrics(Request $request, float $duration, float $memoryUsed): void
    {
        $key = 'performance:' . date('Y-m-d-H') . ':' . $request->path();
        
        $metrics = Cache::get($key, [
            'count' => 0,
            'total_duration' => 0,
            'total_memory' => 0,
            'max_duration' => 0,
            'max_memory' => 0,
            'min_duration' => PHP_FLOAT_MAX,
            'min_memory' => PHP_FLOAT_MAX
        ]);
        
        $metrics['count']++;
        $metrics['total_duration'] += $duration;
        $metrics['total_memory'] += $memoryUsed;
        $metrics['max_duration'] = max($metrics['max_duration'], $duration);
        $metrics['max_memory'] = max($metrics['max_memory'], $memoryUsed);
        $metrics['min_duration'] = min($metrics['min_duration'], $duration);
        $metrics['min_memory'] = min($metrics['min_memory'], $memoryUsed);
        
        Cache::put($key, $metrics, 3600); // Store for 1 hour
    }
}
