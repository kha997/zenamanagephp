<?php

namespace App\Exceptions;

use App\Services\ErrorEnvelopeService;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            
        });
    }

    /**
     * Convert an authentication exception into a response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return ErrorEnvelopeService::authenticationError('Authentication required');
        }

        return parent::unauthenticated($request, $exception);
    }

    public function render($request, Throwable $exception)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            if ($exception instanceof ThrottleRequestsException) {
                $message = $exception->getMessage() ?: 'Too many requests. Please try again later.';

                return response()->json([
                    'success' => false,
                    'error' => [
                        'message' => $message,
                        'code' => 'E429.RATE_LIMIT'
                    ]
                ], 429);
            }

            if ($exception instanceof NotFoundHttpException) {
                return ErrorEnvelopeService::notFoundError('Endpoint');
            }

            if ($exception instanceof HttpExceptionInterface) {
                $statusCode = $exception->getStatusCode();
                $message = $exception->getMessage() ?: $this->getDefaultMessageForStatus($statusCode);

                return ErrorEnvelopeService::error(
                    $this->getHttpErrorCode($statusCode),
                    $message,
                    [],
                    $statusCode
                );
            }
        }

        return parent::render($request, $exception);
    }

    private function getHttpErrorCode(int $statusCode): string
    {
        return match ($statusCode) {
            400 => 'E400.BAD_REQUEST',
            401 => 'E401.AUTHENTICATION',
            403 => 'E403.AUTHORIZATION',
            404 => 'E404.NOT_FOUND',
            409 => 'E409.CONFLICT',
            422 => 'E422.VALIDATION',
            429 => 'E429.RATE_LIMIT',
            500 => 'E500.SERVER_ERROR',
            503 => 'E503.SERVICE_UNAVAILABLE',
            default => 'E' . $statusCode . '.HTTP_ERROR',
        };
    }

    private function getDefaultMessageForStatus(int $statusCode): string
    {
        return SymfonyResponse::$statusTexts[$statusCode] ?? 'HTTP error occurred';
    }
}
