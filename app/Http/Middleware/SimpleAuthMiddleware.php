<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SimpleAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Simple authentication check - for debugging purposes
        // In production, this should be replaced with proper Sanctum authentication
        
        // For now, just pass through without authentication
        // This allows us to test API routes without authentication issues
        return $next($request);
    }
}