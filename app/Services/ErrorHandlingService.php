<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

/**
 * Enhanced Error Handling Service
 * 
 * Provides comprehensive error handling with:
 * - Standardized error responses
 * - Request correlation tracking
 * - Detailed logging and monitoring
 * - User-friendly error messages
 * - Security-conscious error reporting
 */
class ErrorHandlingService
{
    private ComprehensiveLoggingService $loggingService;
    private RequestCorrelationService $correlationService;

    public function __construct(
        ComprehensiveLoggingService $loggingService,
        RequestCorrelationService $correlationService
    ) {
        $this->loggingService = $loggingService;
        $this->correlationService = $correlationService;
    }

    /**
     * Handle and format error response
     */
    public function handleError(Throwable $exception, Request $request): JsonResponse
    {
        $correlationId = $this->correlationService->getCorrelationId();
        
        // Log the error
        $this->logError($exception, $request, $correlationId);
        
        // Get error details
        $statusCode = $this->getStatusCode($exception);
        $errorCode = $this->getErrorCode($exception);
        $message = $this->getErrorMessage($exception);
        $details = $this->getErrorDetails($exception);
        
        // Build response
        $response = [
            'success' => false,
            'timestamp' => now()->toISOString(),
            'request_id' => $correlationId,
            'error' => [
                'id' => $correlationId,
                'code' => $errorCode,
                'message' => $message,
                'details' => $details,
            ],
        ];
        
        // Add retry-after for specific status codes
        $retryAfter = $this->getRetryAfter($statusCode);
        if ($retryAfter) {
            $response['retry_after'] = $retryAfter;
        }
        
        return response()->json($response, $statusCode, [
            'X-Request-ID' => $correlationId,
            'Retry-After' => $retryAfter,
        ]);
    }

    /**
     * Handle validation errors
     */
    public function handleValidationError(ValidationException $exception, Request $request): JsonResponse
    {
        $correlationId = $this->correlationService->getCorrelationId();
        
        $this->loggingService->logApi('validation_failed', [
            'errors' => $exception->errors(),
            'request_id' => $correlationId,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->check() ? auth()->id() : null,
        ]);
        
        return response()->json([
            'success' => false,
            'timestamp' => now()->toISOString(),
            'request_id' => $correlationId,
            'error' => [
                'id' => $correlationId,
                'code' => 'E422.VALIDATION_ERROR',
                'message' => 'Validation failed',
                'details' => [
                    'validation_errors' => $exception->errors(),
                ],
            ],
        ], 422, [
            'X-Request-ID' => $correlationId,
        ]);
    }

    /**
     * Handle authentication errors
     */
    public function handleAuthenticationError(AuthenticationException $exception, Request $request): JsonResponse
    {
        $correlationId = $this->correlationService->getCorrelationId();
        
        $this->loggingService->logAuth('authentication_failed', [
            'request_id' => $correlationId,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
        
        return response()->json([
            'success' => false,
            'timestamp' => now()->toISOString(),
            'request_id' => $correlationId,
            'error' => [
                'id' => $correlationId,
                'code' => 'E401.UNAUTHORIZED',
                'message' => 'Authentication required',
                'details' => [],
            ],
        ], 401, [
            'X-Request-ID' => $correlationId,
        ]);
    }

    /**
     * Handle model not found errors
     */
    public function handleModelNotFoundError(ModelNotFoundException $exception, Request $request): JsonResponse
    {
        $correlationId = $this->correlationService->getCorrelationId();
        
        $this->loggingService->logDataAccess('model_not_found', $exception->getModel(), null, [
            'ids' => $exception->getIds(),
            'request_id' => $correlationId,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'user_id' => auth()->check() ? auth()->id() : null,
        ]);
        
        return response()->json([
            'success' => false,
            'timestamp' => now()->toISOString(),
            'request_id' => $correlationId,
            'error' => [
                'id' => $correlationId,
                'code' => 'E404.NOT_FOUND',
                'message' => 'Resource not found',
                'details' => [
                    'model' => class_basename($exception->getModel()),
                    'ids' => $exception->getIds(),
                ],
            ],
        ], 404, [
            'X-Request-ID' => $correlationId,
        ]);
    }

    /**
     * Log error with comprehensive context
     */
    private function logError(Throwable $exception, Request $request, string $correlationId): void
    {
        $context = [
            'exception_class' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $this->sanitizeTrace($exception->getTraceAsString()),
            'request_id' => $correlationId,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
                'user_id' => auth()->check() ? auth()->id() : null,
            'tenant_id' => $this->getTenantId(),
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
        ];

        if ($exception instanceof ValidationException) {
            ComprehensiveLoggingService::logApi('validation_failed', $context);
        } elseif ($exception instanceof AuthenticationException) {
            ComprehensiveLoggingService::logAuth('authentication_failed', $context);
        } elseif ($exception instanceof ModelNotFoundException) {
            ComprehensiveLoggingService::logDataAccess('model_not_found', $exception->getModel(), null, $context);
        } else {
            ComprehensiveLoggingService::logError($exception, $context);
        }
    }

    /**
     * Get HTTP status code for exception
     */
    private function getStatusCode(Throwable $exception): int
    {
        if ($exception instanceof HttpException) {
            return $exception->getStatusCode();
        }
        
        if ($exception instanceof ValidationException) {
            return 422;
        }
        
        if ($exception instanceof AuthenticationException) {
            return 401;
        }
        
        if ($exception instanceof ModelNotFoundException) {
            return 404;
        }
        
        return 500;
    }

    /**
     * Get error code for exception
     */
    private function getErrorCode(Throwable $exception): string
    {
        $statusCode = $this->getStatusCode($exception);
        
        $errorCodes = [
            400 => 'E400.BAD_REQUEST',
            401 => 'E401.UNAUTHORIZED',
            403 => 'E403.FORBIDDEN',
            404 => 'E404.NOT_FOUND',
            405 => 'E405.METHOD_NOT_ALLOWED',
            409 => 'E409.CONFLICT',
            422 => 'E422.VALIDATION_ERROR',
            429 => 'E429.RATE_LIMITED',
            500 => 'E500.INTERNAL_ERROR',
            503 => 'E503.SERVICE_UNAVAILABLE',
        ];
        
        return $errorCodes[$statusCode] ?? 'E500.INTERNAL_ERROR';
    }

    /**
     * Get user-friendly error message
     */
    private function getErrorMessage(Throwable $exception): string
    {
        // For production, show generic messages
        if (app()->environment('production')) {
            $statusCode = $this->getStatusCode($exception);
            
            $messages = [
                400 => 'Invalid request. Please check your input and try again.',
                401 => 'Authentication required. Please log in to continue.',
                403 => 'Access denied. You do not have permission to perform this action.',
                404 => 'The requested resource was not found.',
                405 => 'The requested method is not allowed for this resource.',
                409 => 'A conflict occurred. The resource may have been modified by another user.',
                422 => 'Validation failed. Please check your input and try again.',
                429 => 'Too many requests. Please wait a moment before trying again.',
                500 => 'An internal error occurred. Please try again later.',
                503 => 'Service temporarily unavailable. Please try again later.',
            ];
            
            return $messages[$statusCode] ?? 'An unexpected error occurred.';
        }
        
        // For development, show detailed messages
        return $exception->getMessage();
    }

    /**
     * Get error details for API responses
     */
    private function getErrorDetails(Throwable $exception): array
    {
        $details = [];
        
        if ($exception instanceof ValidationException) {
            $details['validation_errors'] = $exception->errors();
        }
        
        if ($exception instanceof ModelNotFoundException) {
            $details['model'] = class_basename($exception->getModel());
            $details['ids'] = $exception->getIds();
        }
        
        // Add development details
        if (app()->environment('local', 'testing')) {
            $details['file'] = $exception->getFile();
            $details['line'] = $exception->getLine();
            $details['trace'] = $this->sanitizeTrace($exception->getTraceAsString());
        }
        
        return $details;
    }

    /**
     * Get retry-after value for specific status codes
     */
    private function getRetryAfter(int $statusCode): ?int
    {
        return match ($statusCode) {
            429, 503 => 60,
            default => null,
        };
    }

    /**
     * Sanitize stack trace to remove sensitive information
     */
    private function sanitizeTrace(string $trace): string
    {
        // Remove sensitive paths and data
        $sensitivePatterns = [
            '/password/i',
            '/token/i',
            '/secret/i',
            '/key/i',
            '/auth/i',
        ];
        
        foreach ($sensitivePatterns as $pattern) {
            $trace = preg_replace($pattern, '[REDACTED]', $trace);
        }
        
        return $trace;
    }

    /**
     * Get tenant ID safely, handling missing service gracefully
     */
    private function getTenantId(): ?string
    {
        try {
            if (class_exists(\App\Services\TenantScopeService::class)) {
                return app(\App\Services\TenantScopeService::class)->getTenantId();
            }
        } catch (\Throwable $e) {
            // Service not available, return null
        }
        
        return null;
    }
    public static function error(
        string $code,
        string $message,
        array $details = [],
        int $statusCode = 400,
        ?string $requestId = null
    ): JsonResponse {
        $errorId = $requestId ?? 'req_' . \Str::random(8);
        
        $response = [
            'success' => false,
            'timestamp' => now()->toISOString(),
            'request_id' => $errorId,
            'error' => [
                'id' => $errorId,
                'code' => $code,
                'message' => $message,
                'details' => $details,
            ],
        ];
        
        // Add retry-after for specific status codes
        $retryAfter = match ($statusCode) {
            429, 503 => 60,
            default => null,
        };
        
        if ($retryAfter) {
            $response['retry_after'] = $retryAfter;
        }
        
        return response()->json($response, $statusCode, [
            'X-Request-ID' => $errorId,
            'Retry-After' => $retryAfter,
        ]);
    }
}
