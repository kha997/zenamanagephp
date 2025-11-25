<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Services\CorrelationIdService;
use App\Services\ObservabilityService;

/**
 * Unified Observability Middleware
 * 
 * PR: Observability 3-in-1
 * 
 * Ensures request_id and tenant_id are attached to:
 * - All log entries (via Log context)
 * - All metrics (via labels)
 * - All trace spans (via attributes)
 */
class UnifiedObservabilityMiddleware
{
    protected ObservabilityService $observabilityService;

    public function __construct(ObservabilityService $observabilityService)
    {
        $this->observabilityService = $observabilityService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Get or generate correlation ID (request_id)
        $correlationId = CorrelationIdService::getOrGenerate($request);
        
        // Get tenant_id from authenticated user
        $user = Auth::user();
        $tenantId = $user?->tenant_id ? (string) $user->tenant_id : null;
        $userId = $user?->id ? (string) $user->id : null;

        // Set correlation ID in request attributes
        $request->attributes->set('correlation_id', $correlationId);
        $request->attributes->set('trace_id', $correlationId);
        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set('user_id', $userId);

        // Bind to container for access throughout request lifecycle
        app()->instance('correlation_id', $correlationId);
        app()->instance('trace_id', $correlationId);
        app()->instance('tenant_id', $tenantId);
        app()->instance('user_id', $userId);

        // Add to Log context (unified logging)
        Log::withContext([
            'request_id' => $correlationId,
            'trace_id' => $correlationId,
            'correlation_id' => $correlationId,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'path' => $request->path(),
        ]);

        // Set response header
        $response = $next($request);
        $response->headers->set('X-Request-Id', $correlationId);
        $response->headers->set('X-Correlation-Id', $correlationId);
        $response->headers->set('X-Trace-Id', $correlationId);

        // Record HTTP request metrics with labels (including request_id)
        if ($response->getStatusCode() >= 200) {
            $latency = $this->calculateLatency();
            $this->observabilityService->recordHttpRequest(
                $request->method(),
                $request->path(),
                $response->getStatusCode(),
                $latency,
                $tenantId,
                $userId,
                $correlationId // Pass request_id
            );
        }

        return $response;
    }

    /**
     * Calculate request latency in milliseconds
     */
    protected function calculateLatency(): float
    {
        $startTime = defined('LARAVEL_START') ? LARAVEL_START : null;
        
        if ($startTime) {
            return (microtime(true) - $startTime) * 1000;
        }

        return 0.0;
    }
}

