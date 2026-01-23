<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Services\AdvancedCacheService;
use Symfony\Component\HttpFoundation\Response;

/**
 * API Response Caching Middleware
 * 
 * Automatically caches API responses based on:
 * - Request method and URL
 * - User authentication status
 * - Tenant context
 * - Cache headers
 */
class ApiResponseCacheMiddleware
{
    private AdvancedCacheService $cacheService;

    public function __construct(AdvancedCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $ttl = '300'): Response
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Check if caching is disabled for this request
        if ($this->shouldSkipCache($request)) {
            return $next($request);
        }

        $cacheKey = $this->generateCacheKey($request);
        $ttlSeconds = (int) $ttl;

        // Try to get cached response
        $cachedResponse = $this->cacheService->get($cacheKey);
        
        if ($cachedResponse !== null) {
            return $this->createResponseFromCache($cachedResponse);
        }

        // Execute the request
        $response = $next($request);

        // Cache successful responses
        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $this->cacheResponse($cacheKey, $response, $ttlSeconds);
        }

        return $response;
    }

    /**
     * Check if caching should be skipped
     */
    private function shouldSkipCache(Request $request): bool
    {
        // Skip if cache is disabled via header
        if ($request->header('X-Cache-Control') === 'no-cache') {
            return true;
        }

        // Skip if user is not authenticated (for sensitive data)
        if (!$request->user() && $this->requiresAuthentication($request)) {
            return true;
        }

        // Skip for certain endpoints
        $skipEndpoints = [
            'auth/login',
            'auth/logout',
            'auth/refresh',
            'csrf-token',
        ];

        foreach ($skipEndpoints as $endpoint) {
            if (str_contains($request->path(), $endpoint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if endpoint requires authentication
     */
    private function requiresAuthentication(Request $request): bool
    {
        $authRequiredEndpoints = [
            'auth/me',
            'auth/permissions',
            'dashboard',
            'projects',
            'tasks',
            'users',
        ];

        foreach ($authRequiredEndpoints as $endpoint) {
            if (str_contains($request->path(), $endpoint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate cache key for request
     */
    private function generateCacheKey(Request $request): string
    {
        $keyParts = [
            'api',
            $request->method(),
            $request->path(),
        ];

        // Add query parameters
        $queryString = $request->getQueryString();
        if ($queryString) {
            $keyParts[] = md5($queryString);
        }

        // Add user context if authenticated
        if ($request->user()) {
            $keyParts[] = 'user:' . $request->user()->id;
        }

        // Add tenant context
        $tenantId = $request->header('X-Tenant-ID', 'default');
        $keyParts[] = 'tenant:' . $tenantId;

        return implode(':', $keyParts);
    }

    /**
     * Cache the response
     */
    private function cacheResponse(string $cacheKey, Response $response, int $ttl): void
    {
        try {
            // Add cache headers to response
            $response->headers->set('X-Cache', 'MISS');
            $response->headers->set('X-Cache-Key', $cacheKey);
            $response->headers->set('X-Cache-TTL', (string) $ttl);
            $response->headers->set('X-Cache-Status', 'MISS');

            $cacheData = [
                'status_code' => $response->getStatusCode(),
                'headers' => $response->headers->all(),
                'content' => $response->getContent(),
                'cached_at' => time(),
            ];

            $this->cacheService->set($cacheKey, $cacheData, [
                'ttl' => $ttl,
                'tags' => ['api_response'],
            ]);

        } catch (\Exception $e) {
            Log::warning('Failed to cache API response', [
                'key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Create response from cached data
     */
    private function createResponseFromCache(array $cachedData): Response
    {
        $response = new Response(
            $cachedData['content'],
            $cachedData['status_code']
        );

        // Restore headers
        foreach ($cachedData['headers'] as $name => $values) {
            $response->headers->set($name, $values);
        }

        // Add cache headers
        $response->headers->set('X-Cache', 'HIT');
        $response->headers->set('X-Cache-Date', date('r', $cachedData['cached_at']));
        $response->headers->set('X-Cache-Status', 'HIT');

        return $response;
    }
}
