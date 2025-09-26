<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AdvancedRateLimitingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $type = 'default'): Response
    {
        $key = $this->resolveRequestSignature($request, $type);
        
        // Different rate limits for different types
        $limits = $this->getRateLimits($type);
        
        if (RateLimiter::tooManyAttempts($key, $limits['max_attempts'])) {
            $seconds = RateLimiter::availableIn($key);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Too many requests. Please try again in ' . $seconds . ' seconds.',
                'retry_after' => $seconds
            ], 429);
        }
        
        RateLimiter::hit($key, $limits['decay_minutes'] * 60);
        
        $response = $next($request);
        
        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', $limits['max_attempts']);
        $response->headers->set('X-RateLimit-Remaining', RateLimiter::remaining($key, $limits['max_attempts']));
        $response->headers->set('X-RateLimit-Reset', now()->addMinutes($limits['decay_minutes'])->timestamp);
        
        return $response;
    }
    
    /**
     * Resolve request signature
     */
    protected function resolveRequestSignature(Request $request, string $type): string
    {
        $identifier = $request->ip();
        
        // For authenticated users, use user ID
        if ($request->user()) {
            $identifier = $request->user()->id;
        }
        
        return $type . ':' . $identifier;
    }
    
    /**
     * Get rate limits for different types
     */
    protected function getRateLimits(string $type): array
    {
        $limits = [
            'default' => [
                'max_attempts' => 60,
                'decay_minutes' => 1
            ],
            'api' => [
                'max_attempts' => 100,
                'decay_minutes' => 1
            ],
            'public' => [
                'max_attempts' => 30,
                'decay_minutes' => 1
            ],
            'admin' => [
                'max_attempts' => 200,
                'decay_minutes' => 1
            ],
            'login' => [
                'max_attempts' => 5,
                'decay_minutes' => 15
            ],
            'secrets' => [
                'max_attempts' => 3,
                'decay_minutes' => 60
            ]
        ];
        
        return $limits[$type] ?? $limits['default'];
    }
}
