<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enhanced Rate Limiting Middleware
 * 
 * Provides sophisticated rate limiting with:
 * - Multiple rate limit tiers (per endpoint, per user, per IP)
 * - Sliding window algorithm
 * - Different limits for different user roles
 * - Configurable burst allowances
 * - Detailed logging and monitoring
 */
class EnhancedRateLimitMiddleware
{
    /**
     * Rate limit configurations
     */
    private array $config = [
        'default' => [
            'requests_per_minute' => 60,
            'burst_limit' => 100,
            'window_size' => 60, // seconds
        ],
        'auth' => [
            'requests_per_minute' => 10,
            'burst_limit' => 20,
            'window_size' => 60,
        ],
        'upload' => [
            'requests_per_minute' => 5,
            'burst_limit' => 10,
            'window_size' => 60,
        ],
        'api' => [
            'requests_per_minute' => 100,
            'burst_limit' => 200,
            'window_size' => 60,
        ],
        'admin' => [
            'requests_per_minute' => 500,
            'burst_limit' => 1000,
            'window_size' => 60,
        ],
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $type = 'default'): Response
    {
        $config = $this->getConfig($type);
        $identifier = $this->getIdentifier($request);
        
        // Check rate limit
        $rateLimitResult = $this->checkRateLimit($identifier, $config, $request, $type);
        
        if (!$rateLimitResult['allowed']) {
            return $this->rateLimitExceededResponse($rateLimitResult, $request);
        }
        
        // Log successful request
        $this->logRequest($request, $rateLimitResult);
        
        // Add rate limit headers to response
        $response = $next($request);
        $this->addRateLimitHeaders($response, $rateLimitResult);
        
        return $response;
    }
    
    /**
     * Get configuration for rate limit type
     */
    private function getConfig(string $type): array
    {
        return $this->config[$type] ?? $this->config['default'];
    }
    
    /**
     * Get unique identifier for rate limiting
     */
    private function getIdentifier(Request $request): string
    {
        // Try to get user ID first (for authenticated users)
        if ($request->user()) {
            return 'user:' . $request->user()->id;
        }
        
        // Fall back to IP address
        return 'ip:' . $request->ip();
    }
    
    /**
     * Check rate limit using sliding window algorithm
     */
    private function checkRateLimit(string $identifier, array $config, Request $request, string $type): array
    {
        $now = time();
        $windowSize = $config['window_size'];
        $maxRequests = $config['requests_per_minute'];
        $burstLimit = $config['burst_limit'];
        
        // Get current window data
        $windowKey = "rate_limit:{$identifier}:{$type}";
        $currentWindow = Cache::get($windowKey, []);
        
        // Clean old entries (older than window size)
        $currentWindow = array_filter($currentWindow, function($timestamp) use ($now, $windowSize) {
            return ($now - $timestamp) < $windowSize;
        });
        
        // Count current requests in window
        $currentRequests = count($currentWindow);
        
        // Check if we're within limits
        $allowed = $currentRequests < $maxRequests;
        
        // Check burst limit (allows temporary spikes)
        $burstAllowed = $currentRequests < $burstLimit;
        
        if ($allowed || $burstAllowed) {
            // Add current request timestamp
            $currentWindow[] = $now;
            
            // Store updated window
            Cache::put($windowKey, $currentWindow, $windowSize + 10); // Extra 10 seconds buffer
            
            return [
                'allowed' => true,
                'current_requests' => $currentRequests + 1,
                'max_requests' => $maxRequests,
                'burst_limit' => $burstLimit,
                'window_size' => $windowSize,
                'reset_time' => $now + $windowSize,
                'remaining' => max(0, $maxRequests - ($currentRequests + 1)),
                'is_burst' => !$allowed && $burstAllowed,
            ];
        }
        
        return [
            'allowed' => false,
            'current_requests' => $currentRequests,
            'max_requests' => $maxRequests,
            'burst_limit' => $burstLimit,
            'window_size' => $windowSize,
            'reset_time' => min($currentWindow) + $windowSize,
            'remaining' => 0,
            'is_burst' => false,
        ];
    }
    
    /**
     * Return rate limit exceeded response
     */
    private function rateLimitExceededResponse(array $rateLimitResult, Request $request): Response
    {
        // Log rate limit violation
        Log::warning('Rate limit exceeded', [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'user_id' => $request->user()?->id,
            'rate_limit_result' => $rateLimitResult,
        ]);
        
        $retryAfter = $rateLimitResult['reset_time'] - time();
        
        return response()->json([
            'success' => false,
            'error' => 'Rate limit exceeded',
            'message' => 'Too many requests. Please try again later.',
            'code' => 'RATE_LIMIT_EXCEEDED',
            'retry_after' => $retryAfter,
            'rate_limit' => [
                'current_requests' => $rateLimitResult['current_requests'],
                'max_requests' => $rateLimitResult['max_requests'],
                'window_size' => $rateLimitResult['window_size'],
                'reset_time' => $rateLimitResult['reset_time'],
            ]
        ], 429)->header('Retry-After', $retryAfter);
    }
    
    /**
     * Add rate limit headers to response
     */
    private function addRateLimitHeaders(Response $response, array $rateLimitResult): void
    {
        $response->headers->set('X-RateLimit-Limit', (string) $rateLimitResult['max_requests']);
        $response->headers->set('X-RateLimit-Remaining', (string) $rateLimitResult['remaining']);
        $response->headers->set('X-RateLimit-Reset', (string) $rateLimitResult['reset_time']);
        $response->headers->set('X-RateLimit-Window', (string) $rateLimitResult['window_size']);
        
        if ($rateLimitResult['is_burst']) {
            $response->headers->set('X-RateLimit-Burst', 'true');
        }
    }
    
    /**
     * Log request for monitoring
     */
    private function logRequest(Request $request, array $rateLimitResult): void
    {
        // Only log if approaching limits (for performance)
        if ($rateLimitResult['remaining'] < 10) {
            Log::info('Rate limit status', [
                'ip' => $request->ip(),
                'endpoint' => $request->path(),
                'method' => $request->method(),
                'user_id' => $request->user()?->id,
                'remaining' => $rateLimitResult['remaining'],
                'is_burst' => $rateLimitResult['is_burst'],
            ]);
        }
    }
}