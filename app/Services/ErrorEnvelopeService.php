<?php

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

/**
 * Error Envelope Service
 * Standardized error response format for ZenaManage API
 * 
 * Format:
 * {
 *   "error": {
 *     "id": "req_xxx",
 *     "code": "E422.VALIDATION",
 *     "message": "Invalid input",
 *     "details": {}
 *   }
 * }
 */
class ErrorEnvelopeService
{
    /**
     * Generate error response with envelope format
     * 
     * @param string $code Error code (e.g., E422.VALIDATION)
     * @param string $message Error message (will be translated if key exists)
     * @param array $details Additional error details
     * @param int $statusCode HTTP status code
     * @param string|null $requestId Request ID for correlation
     * @return JsonResponse
     */
    public static function error(
        string $code,
        string $message,
        array $details = [],
        int $statusCode = 500,
        ?string $requestId = null
    ): JsonResponse {
        $errorId = $requestId ?? self::generateRequestId();
        
        // Try to translate message if it's a translation key
        $translatedMessage = self::translateMessage($message);
        
        $error = [
            'id' => $errorId,
            'code' => $code,
            'message' => $translatedMessage,
            'details' => $details
        ];
        
        $response = response()->json([
            'success' => false,
            'data' => null,
            'error' => $error,
        ], $statusCode);
        
        // Add Retry-After header for specific status codes
        if (in_array($statusCode, [429, 503])) {
            $response->header('Retry-After', '60');
        }
        
        return $response;
    }
    
    /**
     * Generate validation error response
     * 
     * @param array $validationErrors Validation errors
     * @param string|null $requestId Request ID for correlation
     * @return JsonResponse
     */
    public static function validationError(
        array $validationErrors,
        ?string $requestId = null
    ): JsonResponse {
        return self::error(
            'E422.VALIDATION',
            'Validation failed',
            ['validation' => $validationErrors],
            422,
            $requestId
        );
    }
    
    /**
     * Generate authentication error response
     * 
     * @param string $message Authentication error message
     * @param string|null $requestId Request ID for correlation
     * @return JsonResponse
     */
    public static function authenticationError(
        string $message = 'Authentication required',
        ?string $requestId = null
    ): JsonResponse {
        return self::error(
            'E401.AUTHENTICATION',
            $message,
            [],
            401,
            $requestId
        );
    }
    
    /**
     * Generate authorization error response
     * 
     * @param string $message Authorization error message
     * @param string|null $requestId Request ID for correlation
     * @return JsonResponse
     */
    public static function authorizationError(
        string $message = 'Insufficient permissions',
        ?string $requestId = null
    ): JsonResponse {
        return self::error(
            'E403.AUTHORIZATION',
            $message,
            [],
            403,
            $requestId
        );
    }
    
    /**
     * Generate not found error response
     * 
     * @param string $resource Resource that was not found
     * @param string|null $requestId Request ID for correlation
     * @return JsonResponse
     */
    public static function notFoundError(
        string $resource = 'Resource',
        ?string $requestId = null
    ): JsonResponse {
        return self::error(
            'E404.NOT_FOUND',
            "{$resource} not found",
            [],
            404,
            $requestId
        );
    }
    
    /**
     * Generate conflict error response
     * 
     * @param string $message Conflict error message
     * @param array $details Additional conflict details
     * @param string|null $requestId Request ID for correlation
     * @return JsonResponse
     */
    public static function conflictError(
        string $message = 'Resource conflict',
        array $details = [],
        ?string $requestId = null
    ): JsonResponse {
        return self::error(
            'E409.CONFLICT',
            $message,
            $details,
            409,
            $requestId
        );
    }
    
    /**
     * Generate rate limit error response
     * 
     * @param string $message Rate limit error message
     * @param int $retryAfter Seconds to wait before retry
     * @param string|null $requestId Request ID for correlation
     * @return JsonResponse
     */
    public static function rateLimitError(
        string $message = 'Rate limit exceeded',
        int $retryAfter = 60,
        ?string $requestId = null
    ): JsonResponse {
        return self::error(
            'E429.RATE_LIMIT',
            $message,
            ['retry_after' => $retryAfter],
            429,
            $requestId
        );
    }
    
    /**
     * Generate server error response
     * 
     * @param string $message Server error message
     * @param array $details Additional error details
     * @param string|null $requestId Request ID for correlation
     * @return JsonResponse
     */
    public static function serverError(
        string $message = 'Internal server error',
        array $details = [],
        ?string $requestId = null
    ): JsonResponse {
        return self::error(
            'E500.SERVER_ERROR',
            $message,
            $details,
            500,
            $requestId
        );
    }
    
    /**
     * Generate service unavailable error response
     * 
     * @param string $message Service unavailable message
     * @param int $retryAfter Seconds to wait before retry
     * @param string|null $requestId Request ID for correlation
     * @return JsonResponse
     */
    public static function serviceUnavailableError(
        string $message = 'Service temporarily unavailable',
        int $retryAfter = 60,
        ?string $requestId = null
    ): JsonResponse {
        return self::error(
            'E503.SERVICE_UNAVAILABLE',
            $message,
            ['retry_after' => $retryAfter],
            503,
            $requestId
        );
    }
    
    /**
     * Generate request ID for correlation
     * 
     * @return string
     */
    private static function generateRequestId(): string
    {
        return 'req_' . Str::random(8);
    }
    
    /**
     * Get request ID from current request
     * 
     * @return string|null
     */
    public static function getCurrentRequestId(): ?string
    {
        return request()->header('X-Request-Id') ?? 
               request()->header('X-Correlation-ID') ?? 
               null;
    }
    
    /**
     * Translate message if it's a translation key
     * 
     * @param string $message
     * @return string
     */
    private static function translateMessage(string $message): string
    {
        // Check if message is a translation key (contains dots)
        if (strpos($message, '.') !== false) {
            $translated = __("errors.{$message}");
            // If translation exists and is different from key, use it
            if ($translated !== "errors.{$message}") {
                return $translated;
            }
        }
        
        // Return original message if no translation found
        return $message;
    }
}
