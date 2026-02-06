<?php

namespace App\Http\Middleware;

use App\Services\ErrorEnvelopeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
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
        if (isset($data['error']) && isset($data['error']['id'])) {
            return $response;
        }
        
        // Convert to error envelope format
        $errorCode = $this->getErrorCode($response->getStatusCode());
        $errorMessage = $this->getErrorMessage($data, $response->getStatusCode());
        $errorDetails = $this->getErrorDetails($data);
        
        $envelope = ErrorEnvelopeService::error(
            $errorCode,
            $errorMessage,
            $errorDetails,
            $response->getStatusCode(),
            $requestId
        );

        $this->preserveRateLimitHeaders($response, $envelope);

        return $envelope;
    }

    /**
     * Copy rate limit headers from the original response to the envelope so tests
     * and clients can still read the throttle metadata after error wrapping.
     */
    private function preserveRateLimitHeaders(BaseResponse $original, JsonResponse $target): void
    {
        $headersToCopy = [
            'Retry-After',
            'X-RateLimit-Limit',
            'X-RateLimit-Remaining',
            'X-RateLimit-Reset',
            'X-RateLimit-Window',
            'X-RateLimit-Burst',
        ];

        foreach ($headersToCopy as $header) {
            if (!$original->headers->has($header)) {
                continue;
            }

            $values = $original->headers->get($header);
            if (is_array($values)) {
                foreach ($values as $value) {
                    $target->headers->set($header, $value);
                }
                continue;
            }

            $target->headers->set($header, $values);
        }
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
}
