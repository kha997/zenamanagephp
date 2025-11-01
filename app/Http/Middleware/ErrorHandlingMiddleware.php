<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response as BaseResponse;
use App\Services\ErrorHandlingService;
use App\Services\ComprehensiveLoggingService;

/**
 * Error Handling Middleware
 * 
 * Provides comprehensive error handling for API requests with:
 * - Standardized error responses
 * - Request correlation tracking
 * - Detailed logging
 * - Security-conscious error reporting
 */
class ErrorHandlingMiddleware
{
    private ErrorHandlingService $errorHandlingService;
    private ComprehensiveLoggingService $loggingService;

    public function __construct(
        ErrorHandlingService $errorHandlingService,
        ComprehensiveLoggingService $loggingService
    ) {
        $this->errorHandlingService = $errorHandlingService;
        $this->loggingService = $loggingService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): BaseResponse
    {
        try {
            $response = $next($request);
            
            // Log successful requests for monitoring
            $this->logSuccessfulRequest($request, $response);
            
            return $response;
        } catch (ValidationException $e) {
            return $this->errorHandlingService->handleValidationError($e, $request);
        } catch (AuthenticationException $e) {
            return $this->errorHandlingService->handleAuthenticationError($e, $request);
        } catch (ModelNotFoundException $e) {
            return $this->errorHandlingService->handleModelNotFoundError($e, $request);
        } catch (\Throwable $e) {
            return $this->errorHandlingService->handleError($e, $request);
        }
    }

    /**
     * Log successful requests for monitoring
     */
    private function logSuccessfulRequest(Request $request, BaseResponse $response): void
    {
        // Only log API requests
        if (!$request->is('api/*')) {
            return;
        }

        // Only log if response time is slow or status is not 2xx
        $responseTime = microtime(true) - LARAVEL_START;
        $isSlow = $responseTime > 1.0; // Log requests slower than 1 second
        $isError = $response->getStatusCode() >= 400;

        if ($isSlow || $isError) {
            $this->loggingService->logApi('request_completed', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'status_code' => $response->getStatusCode(),
                'response_time' => $responseTime,
                'user_id' => auth()->check() ? auth()->id() : null,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'is_slow' => $isSlow,
                'is_error' => $isError,
            ]);
        }
    }
}
