<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * PerformanceMonitoringMiddleware - Middleware cho performance monitoring
 */
class PerformanceMonitoringMiddleware
{
    private PerformanceMonitoringService $monitoringService;

    public function __construct(PerformanceMonitoringService $monitoringService)
    {
        $this->monitoringService = $monitoringService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip monitoring if disabled
        if (!config('monitoring.enabled', true)) {
            return $next($request);
        }

        // Monitor request performance
        $metrics = $this->monitoringService->monitorRequest($request, function () use ($next, $request) {
            return $next($request);
        });

        // Add performance headers
        $response = $metrics['result'] ?? $next($request);
        
        if ($response instanceof Response) {
            $response->headers->set('X-Response-Time', $metrics['execution_time'] . 'ms');
            $response->headers->set('X-Memory-Usage', $metrics['memory_used']);
            $response->headers->set('X-Request-ID', $metrics['request_id']);
        }

        return $response;
    }
}
