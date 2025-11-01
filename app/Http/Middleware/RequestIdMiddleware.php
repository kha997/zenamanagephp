<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestIdMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Get or generate request ID
        $requestId = $request->header('X-Request-Id') 
            ?? $request->header('X-Correlation-Id')
            ?? Str::uuid()->toString();

        // Set request ID in request
        $request->headers->set('X-Request-Id', $requestId);
        
        // Store in session for logging
        session(['request_id' => $requestId]);

        // Process request
        $response = $next($request);

        // Add request ID to response headers
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
