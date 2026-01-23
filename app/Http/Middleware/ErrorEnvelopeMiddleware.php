<?php

namespace App\Http\Middleware;

use App\Services\ErrorEnvelopeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as BaseResponse;

/**
 * Error Envelope Middleware
 * Automatically wraps error responses in standardized envelope format
 */
class ErrorEnvelopeMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);
        
        // Only process JSON responses
        if (!$response instanceof \Illuminate\Http\JsonResponse) {
            return $response;
        }
        
        // Only process error responses (4xx, 5xx)
        if ($response->getStatusCode() < 400) {
            return $response;
        }
        
        // Get current request ID
        $requestId = ErrorEnvelopeService::getCurrentRequestId();
        
        // Get response data
        $data = $response->getData(true);

        // If already in error envelope format, return as is
        if (isset($data['status'])) {
            return $response;
        }

        if (isset($data['error']) && isset($data['error']['id'])) {
            return $response;
        }
        
        // Convert to error envelope format
        $errorCode = $this->getErrorCode($response->getStatusCode());
        $errorMessage = $this->getErrorMessage($data, $response->getStatusCode());
        $errorDetails = $this->getErrorDetails($data);
        
        $errorResponse = ErrorEnvelopeService::error(
            $errorCode,
            $errorMessage,
            $errorDetails,
            $response->getStatusCode(),
            $requestId
        );

        if (!empty($data['errors'])) {
            $payload = $errorResponse->getData(true);
            $payload['errors'] = $data['errors'];
            $errorResponse->setData($payload);
        }

        $this->preserveRateLimitHeaders($response, $errorResponse);

        return $errorResponse;
    }
    
    /**
     * Get error code based on HTTP status code
     * 
     * @param int $statusCode
     * @return string
     */
    private function getErrorCode(int $statusCode): string
    {
        $errorCodes = [
            400 => 'E400.BAD_REQUEST',
            401 => 'E401.AUTHENTICATION',
            403 => 'E403.AUTHORIZATION',
            404 => 'E404.NOT_FOUND',
            409 => 'E409.CONFLICT',
            422 => 'E422.VALIDATION',
            429 => 'E429.RATE_LIMIT',
            500 => 'E500.SERVER_ERROR',
            503 => 'E503.SERVICE_UNAVAILABLE',
        ];
        
        return $errorCodes[$statusCode] ?? 'E500.SERVER_ERROR';
    }
    
    /**
     * Get error message from response data
     * 
     * @param array $data
     * @param int $statusCode
     * @return string
     */
    private function getErrorMessage(array $data, int $statusCode): string
    {
        // Try to get message from various possible locations
        if (isset($data['message'])) {
            return $data['message'];
        }
        
        if (isset($data['error'])) {
            return is_string($data['error']) ? $data['error'] : 'Error occurred';
        }
        
        if (isset($data['errors'])) {
            return 'Validation failed';
        }
        
        // Default messages based on status code
        $defaultMessages = [
            400 => 'Bad request',
            401 => 'Authentication required',
            403 => 'Insufficient permissions',
            404 => 'Resource not found',
            409 => 'Resource conflict',
            422 => 'Validation failed',
            429 => 'Rate limit exceeded',
            500 => 'Internal server error',
            503 => 'Service temporarily unavailable',
        ];
        
        return $defaultMessages[$statusCode] ?? 'An error occurred';
    }
    
    /**
     * Get error details from response data
     * 
     * @param array $data
     * @return array
     */
    private function getErrorDetails(array $data): array
    {
        $details = [];
        
        // Extract validation errors
        if (isset($data['errors'])) {
            $details['validation'] = $data['errors'];
        }
        
        // Extract additional data
        if (isset($data['data'])) {
            $details['data'] = $data['data'];
        }
        
        // Extract debug info in non-production
        if (!app()->environment('production') && isset($data['debug'])) {
            $details['debug'] = $data['debug'];
        }
        
        return $details;
    }

    private function preserveRateLimitHeaders(BaseResponse $original, BaseResponse $target): void
    {
        foreach ($original->headers->all() as $name => $values) {
            $lowerName = strtolower($name);
            if (Str::startsWith($lowerName, 'x-ratelimit') || $lowerName === 'retry-after') {
                foreach ($values as $value) {
                    $target->headers->set($name, $value);
                }
            }
        }
    }
}
