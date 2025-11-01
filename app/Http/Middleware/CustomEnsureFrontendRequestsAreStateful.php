<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CustomEnsureFrontendRequestsAreStateful
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Simple implementation - just pass through without complex logic
        // This avoids the complex Pipeline and middleware conflicts
        
        // Configure basic session settings
        config([
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);
        
        // For now, just pass through without authentication
        // This allows us to test API routes without middleware conflicts
        return $next($request);
    }
}
