<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * CacheMiddleware - Middleware cho caching optimization
 */
class CacheMiddleware
{
    private array $cacheConfig;

    public function __construct()
    {
        $this->cacheConfig = [
            'enabled' => config('cache.enabled', true),
            'default_ttl' => config('cache.default_ttl', 3600),
            'short_ttl' => config('cache.short_ttl', 300),
            'long_ttl' => config('cache.long_ttl', 86400),
            'prefix' => 'cache_',
            'excluded_routes' => [
                'api/auth/*',
                'api/users/create',
                'api/users/*/update',
                'api/users/*/delete',
                'api/projects/create',
                'api/projects/*/update',
                'api/projects/*/delete',
                'api/tasks/create',
                'api/tasks/*/update',
                'api/tasks/*/delete'
            ],
            'excluded_methods' => ['POST', 'PUT', 'PATCH', 'DELETE'],
            'cache_headers' => [
                'Cache-Control' => 'public, max-age=3600',
                'ETag' => null,
                'Last-Modified' => null
            ]
        ];
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $ttl = null): Response
    {
        // Skip caching if disabled
        if (!$this->cacheConfig['enabled']) {
            return $next($request);
        }

        // Skip caching for excluded methods
        if (in_array($request->method(), $this->cacheConfig['excluded_methods'])) {
            return $next($request);
        }

        // Skip caching for excluded routes
        if ($this->isExcludedRoute($request)) {
            return $next($request);
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($request);
        
        // Check if response is cached
        $cachedResponse = Cache::get($cacheKey);
        if ($cachedResponse) {
            return response($cachedResponse['content'])
                ->withHeaders($cachedResponse['headers'])
                ->setStatusCode($cachedResponse['status_code']);
        }

        // Process request
        $response = $next($request);

        // Cache successful responses
        if ($response->getStatusCode() === 200) {
            $this->cacheResponse($cacheKey, $response, $ttl);
        }

        return $response;
    }

    /**
     * Check if route is excluded from caching
     */
    private function isExcludedRoute(Request $request): bool
    {
        $route = $request->route();
        if (!$route) {
            return false;
        }

        $routeName = $route->getName();
        $routeUri = $request->getRequestUri();

        foreach ($this->cacheConfig['excluded_routes'] as $excludedRoute) {
            if (fnmatch($excludedRoute, $routeUri) || fnmatch($excludedRoute, $routeName)) {
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
        $key = $this->cacheConfig['prefix'] . md5(
            $request->method() . 
            $request->getRequestUri() . 
            $request->getQueryString() .
            $request->header('Authorization', '')
        );

        return $key;
    }

    /**
     * Cache response
     */
    private function cacheResponse(string $cacheKey, Response $response, string $ttl = null): void
    {
        $ttl = $ttl ? (int) $ttl : $this->cacheConfig['default_ttl'];

        $cacheData = [
            'content' => $response->getContent(),
            'headers' => $response->headers->all(),
            'status_code' => $response->getStatusCode(),
            'cached_at' => now()->toISOString()
        ];

        Cache::put($cacheKey, $cacheData, $ttl);
    }
}