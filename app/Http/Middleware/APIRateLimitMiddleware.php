<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * API Rate Limiting Middleware
 * 
 * Middleware này sẽ:
 * - Implement rate limiting per user/tenant
 * - Support different limits for different endpoints
 * - Track API usage statistics
 * - Return proper headers for rate limit info
 */
class APIRateLimitMiddleware
{
    /**
     * Default rate limits per minute
     */
    private const DEFAULT_LIMITS = [
        'general' => 60,
        'auth' => 10,
        'upload' => 5,
        'export' => 3
    ];
    
    /**
     * Handle an incoming request
     * 
     * @param Request $request
     * @param Closure $next
     * @param string $limitType
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $limitType = 'general')
    {
        $userId = $request->get('auth_user_id');
        $tenantId = $request->get('auth_tenant_id');
        
        if (!$userId || !$tenantId) {
            // Skip rate limiting for unauthenticated requests
            return $next($request);
        }
        
        $limit = $this->getLimit($limitType, $tenantId);
        $key = $this->getRateLimitKey($userId, $tenantId, $limitType);
        
        $attempts = Cache::get($key, 0);
        
        if ($attempts >= $limit) {
            $this->logRateLimitExceeded($userId, $tenantId, $limitType, $request);
            return $this->rateLimitResponse($limit);
        }
        
        // Increment counter
        Cache::put($key, $attempts + 1, now()->addMinute());
        
        $response = $next($request);
        
        // Add rate limit headers
        $response->headers->set('X-RateLimit-Limit', $limit);
        $response->headers->set('X-RateLimit-Remaining', max(0, $limit - $attempts - 1));
        $response->headers->set('X-RateLimit-Reset', now()->addMinute()->timestamp);
        
        return $response;
    }
    
    /**
     * Get rate limit for specific type and tenant
     * 
     * @param string $limitType
     * @param string $tenantId
     * @return int
     */
    private function getLimit(string $limitType, string $tenantId): int
    {
        // Check for tenant-specific limits in config/database
        $tenantLimit = Cache::remember(
            "rate_limit_{$tenantId}_{$limitType}",
            3600, // 1 hour
            function () use ($tenantId, $limitType) {
                // In real implementation, fetch from database
                return null;
            }
        );
        
        return $tenantLimit ?? self::DEFAULT_LIMITS[$limitType] ?? self::DEFAULT_LIMITS['general'];
    }
    
    /**
     * Generate rate limit cache key
     * 
     * @param string $userId
     * @param string $tenantId
     * @param string $limitType
     * @return string
     */
    private function getRateLimitKey(string $userId, string $tenantId, string $limitType): string
    {
        $minute = now()->format('Y-m-d-H-i');
        return "rate_limit:{$tenantId}:{$userId}:{$limitType}:{$minute}";
    }
    
    /**
     * Log rate limit exceeded event
     * 
     * @param string $userId
     * @param string $tenantId
     * @param string $limitType
     * @param Request $request
     */
    private function logRateLimitExceeded(string $userId, string $tenantId, string $limitType, Request $request): void
    {
        Log::warning('API Rate Limit Exceeded', [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'limit_type' => $limitType,
            'endpoint' => $request->getPathInfo(),
            'method' => $request->getMethod(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
    }
    
    /**
     * Return rate limit exceeded response
     * 
     * @param int $limit
     * @return JsonResponse
     */
    private function rateLimitResponse(int $limit): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => 'Rate limit exceeded',
            'details' => [
                'limit' => $limit,
                'window' => '1 minute',
                'retry_after' => 60
            ]
        ], 429);
    }
}