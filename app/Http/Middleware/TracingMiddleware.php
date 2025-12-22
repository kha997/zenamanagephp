<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\W3CTraceContextService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tracing Middleware
 * 
 * Adds distributed tracing support with W3C traceparent and correlation IDs.
 * Propagates trace context across service boundaries.
 * 
 * Supports:
 * - W3C traceparent header (W3C Trace Context standard)
 * - X-Request-Id correlation ID (backward compatible)
 * - OpenTelemetry integration (when enabled)
 */
class TracingMiddleware
{
    private W3CTraceContextService $traceContextService;

    public function __construct(W3CTraceContextService $traceContextService)
    {
        $this->traceContextService = $traceContextService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Parse W3C traceparent header
        $traceparent = $request->header('traceparent');
        $traceContext = $this->traceContextService->parseTraceparent($traceparent);
        
        // Extract trace ID from traceparent or use correlation ID
        $traceId = $traceContext['trace_id'] ?? null;
        $parentSpanId = $traceContext['parent_id'] ?? null;
        
        // Fallback to X-Request-Id for backward compatibility
        $correlationId = $traceId 
            ?? $request->header('X-Request-Id') 
            ?? $request->header('X-Correlation-Id')
            ?? uniqid('req_', true);

        // Generate new traceparent for this request
        $newTraceparent = $this->traceContextService->generateTraceparent($traceId, $parentSpanId);
        $currentSpanId = $this->traceContextService->extractParentSpanId($newTraceparent);

        // Store in request attributes for access throughout request lifecycle
        $request->attributes->set('correlation_id', $correlationId);
        $request->attributes->set('trace_id', $traceId ?? $correlationId);
        $request->attributes->set('span_id', $currentSpanId);
        $request->attributes->set('traceparent', $newTraceparent);

        // Bind to app container for service access
        app()->instance('correlation_id', $correlationId);
        app()->instance('trace_id', $traceId ?? $correlationId);
        app()->instance('span_id', $currentSpanId);
        app()->instance('traceparent', $newTraceparent);

        // Log request start with structured logging
        // PR: Observability 3-in-1 - Ensure request_id + tenant_id in all logs
        $startTime = microtime(true);
        $user = auth()->user();
        $tenantId = $user?->tenant_id ? (string) $user->tenant_id : null;
        
        // Add to Log context for unified logging
        Log::withContext([
            'request_id' => $correlationId,
            'trace_id' => $traceId ?? $correlationId,
            'correlation_id' => $correlationId,
            'tenant_id' => $tenantId,
            'user_id' => $user?->id ? (string) $user->id : null,
        ]);
        
        Log::info('Request started', [
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'traceId' => $traceId ?? $correlationId,
            'correlation_id' => $correlationId,
            'user_id' => $user?->id,
            'tenant_id' => $tenantId,
            'timestamp' => now()->toISOString(),
        ]);

        // Process request
        $response = $next($request);

        // Calculate latency
        $latency = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Add trace context to response headers
        $response->headers->set('traceparent', $newTraceparent);
        $response->headers->set('X-Request-Id', $correlationId);
        $response->headers->set('X-Correlation-Id', $correlationId);
        $response->headers->set('X-Response-Time', round($latency, 2) . 'ms');
        $response->headers->set('X-Span-Id', $currentSpanId);

        // Record metrics with request_id (PR: Observability 3-in-1)
        try {
            $observabilityService = app(\App\Services\ObservabilityService::class);
            $user = auth()->user();
            $tenantId = $user?->tenant_id ? (string) $user->tenant_id : null;
            $userId = auth()->id() ? (string) auth()->id() : null;
            
            $observabilityService->recordHttpRequest(
                $request->method(),
                $request->path(),
                $response->getStatusCode(),
                $latency,
                $tenantId,
                $userId,
                $correlationId // Pass request_id for metrics labels
            );
        } catch (\Exception $e) {
            // Silently fail if observability service not available
            Log::debug('Observability service not available', ['error' => $e->getMessage()]);
        }

        // Log request completion with structured logging
        // PR: Observability 3-in-1 - Context already set above
        $user = auth()->user();
        $tenantId = $user?->tenant_id ? (string) $user->tenant_id : null;
        
        Log::info('Request completed', [
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'status_code' => $response->getStatusCode(),
            'traceId' => $traceId ?? $correlationId,
            'correlation_id' => $correlationId,
            'trace_id' => $traceId ?? $correlationId,
            'span_id' => $currentSpanId,
            'latency_ms' => round($latency, 2),
            'user_id' => $user?->id,
            'tenant_id' => $tenantId,
            'result' => $response->getStatusCode() >= 400 ? 'error' : 'success',
            'timestamp' => now()->toISOString(),
        ]);

        // Send trace to OpenTelemetry if enabled
        if (config('opentelemetry.enabled', false)) {
            try {
                $tracingService = app(\App\Services\TracingService::class);
                // PR: Observability 3-in-1 - Include request_id in trace attributes
                $user = auth()->user();
                $tenantId = $user?->tenant_id ? (string) $user->tenant_id : null;
                
                $span = $tracingService->startSpan('http.request', [
                    'http.method' => $request->method(),
                    'http.path' => $request->path(),
                    'http.route' => $request->route()?->getName(),
                    'http.status_code' => $response->getStatusCode(),
                    'user.id' => auth()->id() ? (string) auth()->id() : null,
                    'tenant.id' => $tenantId,
                    'request.id' => $correlationId, // Add request_id to trace
                    'trace_id' => $traceId ?? $correlationId,
                    'span_id' => $currentSpanId,
                ]);
                
                $tracingService->endSpan($span, [
                    'http.latency_ms' => round($latency, 2),
                ]);
            } catch (\Exception $e) {
                // Silently fail if tracing service not available
                Log::debug('Tracing service not available', ['error' => $e->getMessage()]);
            }
        }

        return $response;
    }
}

