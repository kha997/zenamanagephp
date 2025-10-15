<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Services\ApiResponseCacheService;
use Illuminate\Support\Facades\Log;

/**
 * API Response Cache Middleware
 * 
 * Automatically caches API responses for performance optimization
 */
class ApiResponseCacheMiddleware
{
    private ApiResponseCacheService $cacheService;

    public function __construct(ApiResponseCacheService $cacheService)
    {
        $this->cacheService = $cacheService;
    }

    /**
     * Handle an incoming request
     */
    public function handle(Request $request, Closure $next)
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Generate cache key
        $userId = $request->user()?->id;
        $cacheKey = $this->cacheService->generateCacheKey($request, $userId);

        // Try to get cached response
        $cachedResponse = $this->cacheService->getCachedResponse($cacheKey);
        
        if ($cachedResponse) {
            Log::debug('API response served from cache', [
                'path' => $request->path(),
                'user_id' => $userId
            ]);

            return response()->json($cachedResponse);
        }

        // Process request
        $response = $next($request);

        // Cache response if appropriate
        if ($this->cacheService->shouldCacheResponse($request, $response)) {
            $responseData = $response->getData(true);
            $endpoint = $this->extractEndpoint($request->path());
            $ttl = $this->cacheService->getTtlForEndpoint($endpoint);
            
            $this->cacheService->cacheResponse($cacheKey, $responseData, $ttl);

            Log::debug('API response cached', [
                'path' => $request->path(),
                'user_id' => $userId,
                'ttl' => $ttl
            ]);
        }

        return $response;
    }

    /**
     * Extract endpoint from path
     */
    private function extractEndpoint(string $path): string
    {
        $segments = explode('/', trim($path, '/'));
        
        // Remove 'api' prefix if present
        if (isset($segments[0]) && $segments[0] === 'api') {
            array_shift($segments);
        }

        // Remove version if present
        if (isset($segments[0]) && preg_match('/^v\d+$/', $segments[0])) {
            array_shift($segments);
        }

        // Return first segment as endpoint
        return $segments[0] ?? 'unknown';
    }
}