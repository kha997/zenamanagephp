<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RequestCorrelationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Generate or get correlation ID
        $correlationId = $request->header('X-Request-ID', Str::uuid());
        
        // Add correlation ID to request
        $request->headers->set('X-Request-ID', $correlationId);
        
        // Log request start with structured format
        $this->logRequestStart($request, $correlationId);
        
        // Process request
        $response = $next($request);
        
        // Add correlation ID to response headers
        $response->headers->set('X-Request-ID', $correlationId);
        
        // Log request completion
        $this->logRequestEnd($request, $response, $correlationId);
        
        return $response;
    }
    
    /**
     * Log request start with structured format
     *
     * @param Request $request
     * @param string $correlationId
     * @return void
     */
    private function logRequestStart(Request $request, string $correlationId): void
    {
        $logData = [
            'timestamp' => now()->toISOString(),
            'level' => 'INFO',
            'correlation_id' => $correlationId,
            'event' => 'request_start',
            'method' => $request->method(),
            'route' => $request->fullUrl(),
            'user_id' => Auth::id(),
            'tenant_id' => Auth::user()?->tenant_id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ];
        
        // Remove null values
        $logData = array_filter($logData, fn($value) => $value !== null);
        
        Log::info('API Request Started', $logData);
    }
    
    /**
     * Log request completion with structured format
     *
     * @param Request $request
     * @param Response $response
     * @param string $correlationId
     * @return void
     */
    private function logRequestEnd(Request $request, Response $response, string $correlationId): void
    {
        $logData = [
            'timestamp' => now()->toISOString(),
            'level' => 'INFO',
            'correlation_id' => $correlationId,
            'event' => 'request_end',
            'method' => $request->method(),
            'route' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'user_id' => Auth::id(),
            'tenant_id' => Auth::user()?->tenant_id,
            'latency_ms' => $this->calculateLatency($request),
            'result' => $response->getStatusCode() >= 400 ? 'error' : 'success',
        ];
        
        // Remove null values
        $logData = array_filter($logData, fn($value) => $value !== null);
        
        Log::info('API Request Completed', $logData);
    }
    
    /**
     * Calculate request latency
     *
     * @param Request $request
     * @return int|null
     */
    private function calculateLatency(Request $request): ?int
    {
        if ($request->has('_start_time')) {
            return (int) ((microtime(true) - $request->get('_start_time')) * 1000);
        }
        
        return null;
    }
}