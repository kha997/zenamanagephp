<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * AdvancedRateLimitMiddleware - Advanced rate limiting middleware
 */
class AdvancedRateLimitMiddleware
{
    private array $rateLimitConfig;

    public function __construct() { return $this->__constructImpl(); } private function __constructImpl() { 
        $this->rateLimitConfig = [
            'enabled' => config('rate_limit.enabled', true),
            'default_limit' => config('rate_limit.default_limit', 100),
            'default_window' => config('rate_limit.default_window', 60), // seconds
            'limits' => [
                'auth' => [
                    'limit' => 10,
                    'window' => 60,
                    'message' => 'Too many authentication attempts. Please try again later.'
                ],
                'api' => [
                    'limit' => 100,
                    'window' => 60,
                    'message' => 'Too many API requests. Please slow down.'
                ],
                'upload' => [
                    'limit' => 20,
                    'window' => 60,
                    'message' => 'Too many file uploads. Please try again later.'
                ],
                'search' => [
                    'limit' => 50,
                    'window' => 60,
                    'message' => 'Too many search requests. Please slow down.'
                ],
                'integration' => [
                    'limit' => 30,
                    'window' => 60,
                    'message' => 'Too many integration requests. Please slow down.'
                ]
            ],
            'whitelist' => [
                '127.0.0.1',
                '::1',
                'localhost'
            ],
            'blacklist' => [],
            'headers' => [
                'X-RateLimit-Limit',
                'X-RateLimit-Remaining',
                'X-RateLimit-Reset',
                'X-RateLimit-Window'
            ]
        ];
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $type = 'api'): Response
    {
        // Skip rate limiting if disabled
        if (!$this->rateLimitConfig['enabled']) {
            return $next($request);
        }

        // Skip rate limiting for whitelisted IPs
        if ($this->isWhitelisted($request)) {
            return $next($request);
        }

        // Block blacklisted IPs
        if ($this->isBlacklisted($request)) {
            return response()->json([
                'error' => 'Access denied',
                'message' => 'Your IP address is blocked'
            ], 403);
        }

        // Get rate limit configuration
        $config = $this->getRateLimitConfig($type);
        
        // Generate rate limit key
        $key = $this->generateRateLimitKey($request, $type);
        
        // Check rate limit
        $rateLimitResult = $this->checkRateLimit($key, $config);
        
        if (!$rateLimitResult['allowed']) {
            return $this->rateLimitExceededResponse($rateLimitResult, $config);
        }

        // Process request
        $response = $next($request);

        // Add rate limit headers
        $this->addRateLimitHeaders($response, $rateLimitResult, $config);

        return $response;
    }

    /**
     * Check if IP is whitelisted
     */
    private function isWhitelisted(Request $request): bool
    {
        $ip = $request->ip();
        return in_array($ip, $this->rateLimitConfig['whitelist']);
    }

    /**
     * Check if IP is blacklisted
     */
    private function isBlacklisted(Request $request): bool
    {
        $ip = $request->ip();
        return in_array($ip, $this->rateLimitConfig['blacklist']);
    }

    /**
     * Get rate limit configuration
     */
    private function getRateLimitConfig(string $type): array
    {
        return $this->rateLimitConfig['limits'][$type] ?? $this->rateLimitConfig['limits']['api'];
    }

    /**
     * Generate rate limit key
     */
    private function generateRateLimitKey(Request $request, string $type): string
    {
        $ip = $request->ip();
        $user = $request->user();
        $userId = $user ? $user->id : 'guest';
        
        return "rate_limit:{$type}:{$ip}:{$userId}";
    }

    /**
     * Check rate limit
     */
    private function checkRateLimit(string $key, array $config): array
    {
        $limit = $config['limit'];
        $window = $config['window'];
        
        $current = Cache::get($key, 0);
        
        if ($current >= $limit) {
            return [
                'allowed' => false,
                'current' => $current,
                'limit' => $limit,
                'remaining' => 0,
                'reset_time' => now()->addSeconds($window)->timestamp
            ];
        }
        
        // Increment counter
        Cache::put($key, $current + 1, $window);
        
        return [
            'allowed' => true,
            'current' => $current + 1,
            'limit' => $limit,
            'remaining' => $limit - ($current + 1),
            'reset_time' => now()->addSeconds($window)->timestamp
        ];
    }

    /**
     * Rate limit exceeded response
     */
    private function rateLimitExceededResponse(array $rateLimitResult, array $config): Response
    {
        $response = response()->json([
            'error' => 'Rate limit exceeded',
            'message' => $config['message'],
            'retry_after' => $rateLimitResult['reset_time'] - now()->timestamp
        ], 429);

        $this->addRateLimitHeaders($response, $rateLimitResult, $config);
        
        return $response;
    }

    /**
     * Add rate limit headers
     */
    private function addRateLimitHeaders(Response $response, array $rateLimitResult, array $config): void
    {
        $response->headers->set('X-RateLimit-Limit', $rateLimitResult['limit']);
        $response->headers->set('X-RateLimit-Remaining', $rateLimitResult['remaining']);
        $response->headers->set('X-RateLimit-Reset', $rateLimitResult['reset_time']);
        $response->headers->set('X-RateLimit-Window', $config['window']);
    }

    /**
     * Get rate limit status
     */
    public function getRateLimitStatus(Request $request, string $type = 'api'): array
    {
        $config = $this->getRateLimitConfig($type);
        $key = $this->generateRateLimitKey($request, $type);
        $current = Cache::get($key, 0);
        
        return [
            'type' => $type,
            'current' => $current,
            'limit' => $config['limit'],
            'remaining' => max(0, $config['limit'] - $current),
            'window' => $config['window'],
            'reset_time' => now()->addSeconds($config['window'])->timestamp
        ];
    }

    /**
     * Reset rate limit for IP
     */
    public function resetRateLimit(Request $request, string $type = 'api'): bool
    {
        $key = $this->generateRateLimitKey($request, $type);
        return Cache::forget($key);
    }

    /**
     * Get rate limit statistics
     */
    public function getRateLimitStatistics(): array
    {
        $stats = [];
        
        foreach ($this->rateLimitConfig['limits'] as $type => $config) {
            $stats[$type] = [
                'limit' => $config['limit'],
                'window' => $config['window'],
                'message' => $config['message']
            ];
        }
        
        return $stats;
    }
}