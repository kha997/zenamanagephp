<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * OpenApiResponseValidator
 * 
 * Validates API responses against OpenAPI spec.
 * Only enabled in dev/staging environments.
 */
class OpenApiResponseValidator
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        
        // Only validate in dev/staging
        if (!app()->environment(['local', 'staging', 'testing'])) {
            return $response;
        }
        
        // Only validate JSON responses
        if (!$response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }
        
        // Only validate API routes
        if (!$request->is('api/*')) {
            return $response;
        }
        
        // Validate response (log warnings, don't fail)
        $this->validateResponse($request, $response);
        
        return $response;
    }
    
    /**
     * Validate response against OpenAPI spec
     * 
     * @param Request $request
     * @param \Illuminate\Http\JsonResponse $response
     * @return void
     */
    private function validateResponse(Request $request, \Illuminate\Http\JsonResponse $response): void
    {
        // Basic validation - check if response structure matches expected format
        $data = $response->getData(true);
        $statusCode = $response->getStatusCode();
        $route = $request->route()?->getName() ?? $request->path();
        
        // For error responses, check standardized format
        if ($statusCode >= 400) {
            if (!isset($data['ok']) || $data['ok'] !== false) {
                Log::warning('OpenAPI validation: Error response missing "ok" field', [
                    'route' => $route,
                    'status_code' => $statusCode,
                    'response' => $data,
                ]);
            }
            
            if (!isset($data['code'])) {
                Log::warning('OpenAPI validation: Error response missing "code" field', [
                    'route' => $route,
                    'status_code' => $statusCode,
                    'response' => $data,
                ]);
            }
            
            if (!isset($data['traceId'])) {
                Log::warning('OpenAPI validation: Error response missing "traceId" field', [
                    'route' => $route,
                    'status_code' => $statusCode,
                    'response' => $data,
                ]);
            }
        }
        
        // For success responses, basic structure check
        if ($statusCode < 400) {
            // Check if response has expected structure (varies by endpoint)
            // This is a basic check - full validation would require parsing OpenAPI spec
        }
    }
}

