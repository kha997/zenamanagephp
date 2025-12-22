<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Response;

/**
 * Custom rate limiter for Projects API
 */
class ProjectsRateLimiter
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $limit = '60', string $decay = '1'): Response
    {
        $key = $this->resolveRequestSignature($request);
        
        $maxAttempts = (int) $limit;
        $decayMinutes = (int) $decay;
        
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $retryAfter = RateLimiter::availableIn($key);
            
            return response()->json([
                'error' => 'Too Many Requests',
                'message' => 'Rate limit exceeded. Please try again later.',
                'retry_after' => $retryAfter,
                'limit' => $maxAttempts,
                'remaining' => 0
            ], 429)->header('Retry-After', $retryAfter);
        }
        
        RateLimiter::hit($key, $decayMinutes * 60);
        
        $response = $next($request);
        
        $remaining = RateLimiter::remaining($key, $maxAttempts);
        
        return $response->header('X-RateLimit-Limit', $maxAttempts)
                       ->header('X-RateLimit-Remaining', $remaining)
                       ->header('X-RateLimit-Reset', now()->addMinutes($decayMinutes)->timestamp);
    }
    
    /**
     * Resolve request signature for rate limiting
     */
    protected function resolveRequestSignature(Request $request): string
    {
        $user = $request->user();
        $tenantId = $user ? $user->tenant_id : 'guest';
        $ip = $request->ip();
        $route = $request->route() ? $request->route()->getName() : $request->path();
        
        return "projects_api:{$tenantId}:{$ip}:{$route}";
    }
}
