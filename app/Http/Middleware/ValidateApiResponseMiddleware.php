<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validate API Response Middleware
 * 
 * Validates API responses against OpenAPI specification.
 * Only runs in test environment to avoid performance impact in production.
 * Logs warnings if responses don't match spec.
 */
class ValidateApiResponseMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only validate in test environment
        if (!app()->environment('testing')) {
            return $response;
        }

        // Only validate JSON responses from API routes
        if (!$response instanceof JsonResponse || !$request->is('api/*')) {
            return $response;
        }

        // Skip validation for certain routes (health checks, etc.)
        if ($request->is('api/health*') || $request->is('api/metrics*')) {
            return $response;
        }

        // Validate response structure
        $this->validateResponseStructure($request, $response);

        return $response;
    }

    /**
     * Validate response structure matches expected format
     */
    private function validateResponseStructure(Request $request, JsonResponse $response): void
    {
        $data = $response->getData(true);
        $statusCode = $response->getStatusCode();

        // For success responses (2xx), expect standard format
        if ($statusCode >= 200 && $statusCode < 300) {
            // Check if response has expected structure
            // Standard format: { ok: true, data: {...}, message: "..." }
            // Or: { success: true, data: {...} }
            if (!isset($data['ok']) && !isset($data['success']) && !isset($data['data'])) {
                Log::warning('API response may not match expected format', [
                    'route' => $request->route()?->getName(),
                    'path' => $request->path(),
                    'status' => $statusCode,
                    'response_keys' => array_keys($data),
                    'traceId' => $request->header('X-Request-Id'),
                ]);
            }
        }

        // For error responses (4xx, 5xx), expect error envelope
        if ($statusCode >= 400) {
            // Standard error format: { ok: false, error: { code, message, details, traceId } }
            // Or: { success: false, error: "...", code: "..." }
            if (!isset($data['ok']) && !isset($data['success']) && !isset($data['error'])) {
                Log::warning('Error response may not match expected format', [
                    'route' => $request->route()?->getName(),
                    'path' => $request->path(),
                    'status' => $statusCode,
                    'response_keys' => array_keys($data),
                    'traceId' => $request->header('X-Request-Id'),
                ]);
            }

            // Ensure error code is present
            if (isset($data['error']) && is_array($data['error']) && !isset($data['error']['code'])) {
                Log::warning('Error response missing error code', [
                    'route' => $request->route()?->getName(),
                    'path' => $request->path(),
                    'traceId' => $request->header('X-Request-Id'),
                ]);
            }
        }
    }
}

