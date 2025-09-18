<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Enhanced Rate Limiting Middleware
 * 
 * Advanced rate limiting with IP and user-based limits
 */
class EnhancedRateLimitMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, string $type = 'default')
    {
        $limits = $this->getLimits($type);
        $identifier = $this->getIdentifier($request);
        
        // Check IP-based rate limit
        if (!$this->checkRateLimit($identifier['ip'], $limits['ip'])) {
            return $this->rateLimitResponse('IP rate limit exceeded', $limits['ip']);
        }

        // Check user-based rate limit (if authenticated)
        if ($request->user() && !$this->checkRateLimit($identifier['user'], $limits['user'])) {
            return $this->rateLimitResponse('User rate limit exceeded', $limits['user']);
        }

        // Check endpoint-specific rate limit
        if (!$this->checkRateLimit($identifier['endpoint'], $limits['endpoint'])) {
            return $this->rateLimitResponse('Endpoint rate limit exceeded', $limits['endpoint']);
        }

        return $next($request);
    }

    /**
     * Get rate limits for different types
     */
    private function getLimits(string $type): array
    {
        $limits = [
            'default' => [
                'ip' => ['requests' => 100, 'minutes' => 15],
                'user' => ['requests' => 200, 'minutes' => 15],
                'endpoint' => ['requests' => 50, 'minutes' => 15]
            ],
            'auth' => [
                'ip' => ['requests' => 10, 'minutes' => 15],
                'user' => ['requests' => 5, 'minutes' => 15],
                'endpoint' => ['requests' => 5, 'minutes' => 15]
            ],
            'api' => [
                'ip' => ['requests' => 1000, 'minutes' => 60],
                'user' => ['requests' => 2000, 'minutes' => 60],
                'endpoint' => ['requests' => 500, 'minutes' => 60]
            ],
            'upload' => [
                'ip' => ['requests' => 20, 'minutes' => 60],
                'user' => ['requests' => 50, 'minutes' => 60],
                'endpoint' => ['requests' => 20, 'minutes' => 60]
            ],
            'email' => [
                'ip' => ['requests' => 5, 'minutes' => 60],
                'user' => ['requests' => 3, 'minutes' => 60],
                'endpoint' => ['requests' => 3, 'minutes' => 60]
            ]
        ];

        return $limits[$type] ?? $limits['default'];
    }

    /**
     * Get identifier for rate limiting
     */
    private function getIdentifier(Request $request): array
    {
        $ip = $request->ip();
        $user = $request->user() ? $request->user()->id : null;
        $endpoint = $request->method() . ':' . $request->path();

        return [
            'ip' => "rate_limit:ip:{$ip}",
            'user' => $user ? "rate_limit:user:{$user}" : null,
            'endpoint' => "rate_limit:endpoint:{$endpoint}"
        ];
    }

    /**
     * Check rate limit for identifier
     */
    private function checkRateLimit(string $key, array $limit): bool
    {
        $current = Cache::get($key, 0);
        
        if ($current >= $limit['requests']) {
            // Log rate limit exceeded
            Log::warning('Rate limit exceeded', [
                'key' => $key,
                'current' => $current,
                'limit' => $limit['requests'],
                'minutes' => $limit['minutes']
            ]);
            
            return false;
        }

        // Increment counter
        Cache::put($key, $current + 1, $limit['minutes'] * 60);
        
        return true;
    }

    /**
     * Return rate limit exceeded response
     */
    private function rateLimitResponse(string $message, array $limit): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => $limit['minutes'] * 60
        ], 429);
    }
}
