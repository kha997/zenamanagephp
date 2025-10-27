<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\PerformanceMonitoringService;
use Illuminate\Support\Facades\Log;

class PerformanceLoggingMiddleware
{
    protected $performanceService;

    public function __construct(PerformanceMonitoringService $performanceService)
    {
        $this->performanceService = $performanceService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        // Process the request
        $response = $next($request);

        $endTime = microtime(true);
        $endMemory = memory_get_usage(true);

        // Calculate metrics
        $responseTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB

        // Get tenant from request
        $tenant = $request->user()?->tenant;

        // Log performance metrics
        $this->logPerformanceMetrics($request, $response, $responseTime, $memoryUsed, $tenant);

        return $response;
    }

    /**
     * Log performance metrics for the request.
     */
    private function logPerformanceMetrics(Request $request, $response, float $responseTime, float $memoryUsed, $tenant): void
    {
        $route = $request->route()?->getName() ?? $request->path();
        $method = $request->method();
        $statusCode = $response->getStatusCode();

        // Log API response time
        if ($request->is('api/*')) {
            $this->performanceService->recordApiResponseTime($route, $responseTime);
        } else {
            // Log page load time for web routes
            $this->performanceService->recordPageLoadTime($route, $responseTime);
        }

        // Log memory usage
        $this->performanceService->recordMemoryUsage(memory_get_usage(true));

        // Log to performance channel
        Log::channel('performance')->info('Request performance metrics', [
            'method' => $method,
            'route' => $route,
            'path' => $request->path(),
            'response_time_ms' => round($responseTime, 2),
            'memory_used_mb' => round($memoryUsed, 2),
            'status_code' => $statusCode,
            'tenant_id' => $tenant?->id,
            'user_id' => $request->user()?->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'timestamp' => now()->toISOString(),
        ]);

        // Check for performance warnings
        $this->checkPerformanceWarnings($request, $responseTime, $memoryUsed, $tenant);
    }

    /**
     * Check for performance warnings and log them.
     */
    private function checkPerformanceWarnings(Request $request, float $responseTime, float $memoryUsed, $tenant): void
    {
        $warnings = [];

        // Check response time
        if ($request->is('api/*') && $responseTime > 300) {
            $warnings[] = "API response time ({$responseTime}ms) exceeds threshold (300ms)";
        } elseif (!$request->is('api/*') && $responseTime > 500) {
            $warnings[] = "Page load time ({$responseTime}ms) exceeds threshold (500ms)";
        }

        // Check memory usage
        if ($memoryUsed > 10) { // 10MB threshold
            $warnings[] = "Memory usage ({$memoryUsed}MB) exceeds threshold (10MB)";
        }

        // Log warnings
        if (!empty($warnings)) {
            Log::channel('performance')->warning('Performance warnings detected', [
                'warnings' => $warnings,
                'route' => $request->route()?->getName() ?? $request->path(),
                'response_time_ms' => $responseTime,
                'memory_used_mb' => $memoryUsed,
                'tenant_id' => $tenant?->id,
                'timestamp' => now()->toISOString(),
            ]);
        }
    }
}
