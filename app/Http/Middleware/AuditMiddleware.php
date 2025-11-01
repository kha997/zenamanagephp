<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class AuditMiddleware
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
        
        // Log request details
        $this->logRequest($request);
        
        // Process the request
        $response = $next($request);
        
        // Log response details
        $this->logResponse($request, $response, $startTime);
        
        return $response;
    }

    /**
     * Log request details.
     */
    protected function logRequest(Request $request): void
    {
        $user = Auth::user();
        
        Log::channel('audit')->info('Request', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'user_id' => $user ? $user->id : null,
            'tenant_id' => $user ? $user->tenant_id : null,
            'timestamp' => now()->toISOString(),
            'headers' => $this->getSafeHeaders($request),
            'input' => $this->getSafeInput($request)
        ]);
    }

    /**
     * Log response details.
     */
    protected function logResponse(Request $request, $response, float $startTime): void
    {
        $user = Auth::user();
        $duration = round((microtime(true) - $startTime) * 1000, 2); // Convert to milliseconds
        
        Log::channel('audit')->info('Response', [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'user_id' => $user ? $user->id : null,
            'tenant_id' => $user ? $user->tenant_id : null,
            'timestamp' => now()->toISOString(),
            'response_size' => strlen($response->getContent())
        ]);
    }

    /**
     * Get safe headers (excluding sensitive information).
     */
    protected function getSafeHeaders(Request $request): array
    {
        $headers = $request->headers->all();
        
        // Remove sensitive headers
        $sensitiveHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token'];
        
        foreach ($sensitiveHeaders as $header) {
            unset($headers[$header]);
        }
        
        return $headers;
    }

    /**
     * Get safe input (excluding sensitive fields).
     */
    protected function getSafeInput(Request $request): array
    {
        $input = $request->all();
        
        // Remove sensitive fields
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'secret', 'api_key'];
        
        foreach ($sensitiveFields as $field) {
            unset($input[$field]);
        }
        
        return $input;
    }
}
