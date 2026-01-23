<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ErrorEnvelopeService;

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
            'allow_burst' => false,
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
        $resolvedType = $this->resolveRateLimitType($type, $request);
        $config = $this->enforceEndpointPolicies($this->getConfig($resolvedType), $resolvedType, $request);
        $identifier = $this->getIdentifier($request);
        
        // Check rate limit
        $rateLimitResult = $this->checkRateLimit($identifier, $config, $request, $resolvedType);
        
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

    private function resolveRateLimitType(string $requestedType, Request $request): string
    {
        if ($this->isAuthEndpoint($request)) {
            return 'auth';
        }

        return $requestedType !== '' ? $requestedType : 'default';
    }

    private function enforceEndpointPolicies(array $config, string $type, Request $request): array
    {
        if ($type === 'auth' || $this->isAuthEndpoint($request)) {
            $config['allow_burst'] = false;
            $config['burst_limit'] = $config['requests_per_minute'];
        }

        return $config;
    }

    private function isAuthEndpoint(Request $request): bool
    {
        return str_starts_with($request->path(), 'api/auth');
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
        $allowBurst = $config['allow_burst'] ?? true;
        $configuredBurstLimit = $config['burst_limit'] ?? $maxRequests;
        $burstLimit = $allowBurst ? $configuredBurstLimit : $maxRequests;
        
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
        $isBurstRequest = $allowBurst && $currentRequests >= $maxRequests && $currentRequests < $burstLimit;

        if ($allowed) {
            $currentWindow[] = $now;
            Cache::put($windowKey, $currentWindow, $windowSize + 10); // Extra 10 seconds buffer

            return [
                'allowed' => true,
                'current_requests' => $currentRequests + 1,
                'max_requests' => $maxRequests,
                'burst_limit' => $burstLimit,
                'window_size' => $windowSize,
                'reset_time' => $now + $windowSize,
                'remaining' => max(0, $maxRequests - ($currentRequests + 1)),
                'is_burst' => false,
                'allow_burst' => $allowBurst,
            ];
        }

        $resetTime = $currentWindow ? min($currentWindow) + $windowSize : $now + $windowSize;

        return [
            'allowed' => false,
            'current_requests' => $currentRequests,
            'max_requests' => $maxRequests,
            'burst_limit' => $burstLimit,
            'window_size' => $windowSize,
            'reset_time' => $resetTime,
            'remaining' => max(0, $maxRequests - $currentRequests),
            'is_burst' => $isBurstRequest,
            'allow_burst' => $allowBurst,
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
        
        $retryAfter = max(1, $rateLimitResult['reset_time'] - time());
        $rateLimitDetails = [
            'current_requests' => $rateLimitResult['current_requests'],
            'max_requests' => $rateLimitResult['max_requests'],
            'window_size' => $rateLimitResult['window_size'],
            'reset_time' => $rateLimitResult['reset_time'],
        ];

        $response = ErrorEnvelopeService::rateLimitError(
            'Too many requests. Please try again later.',
            $retryAfter,
            null,
            ['rate_limit' => $rateLimitDetails]
        );

        $response->headers->set('X-RateLimit-Limit', (string) $rateLimitResult['max_requests']);
        $response->headers->set('X-RateLimit-Remaining', (string) $rateLimitResult['remaining']);
        $response->headers->set('X-RateLimit-Reset', (string) $rateLimitResult['reset_time']);
        $response->headers->set('X-RateLimit-Window', (string) $rateLimitResult['window_size']);
        if ($rateLimitResult['is_burst']) {
            $response->headers->set('X-RateLimit-Burst', 'true');
        }

        return $response;
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
