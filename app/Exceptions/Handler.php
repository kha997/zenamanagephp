<?php

namespace App\Exceptions;

use App\Services\ErrorEnvelopeService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpKernel\Exception\HttpException;
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

        $this->renderable(function (AuthenticationException $exception, Request $request) {
            if (!$this->isZenaRequest($request)) {
                return null;
            }

            $existingResponse = $this->resolveExceptionResponse($exception);
            if ($this->isEnvelopeResponse($existingResponse)) {
                return $existingResponse;
            }

            return ErrorEnvelopeService::authenticationError(
                'Unauthorized',
                ErrorEnvelopeService::getCurrentRequestId()
            );
        });

        $this->renderable(function (AuthorizationException $exception, Request $request) {
            if (!$this->isZenaRequest($request)) {
                return null;
            }

            $existingResponse = $this->resolveExceptionResponse($exception);
            if ($this->isEnvelopeResponse($existingResponse)) {
                return $existingResponse;
            }

            return ErrorEnvelopeService::authorizationError(
                $exception->getMessage() ?: 'Insufficient permissions',
                ErrorEnvelopeService::getCurrentRequestId()
            );
        });

        $this->renderable(function (ModelNotFoundException $exception, Request $request) {
            if (!$this->isZenaRequest($request)) {
                return null;
            }

            $existingResponse = $this->resolveExceptionResponse($exception);
            if ($this->isEnvelopeResponse($existingResponse)) {
                return $existingResponse;
            }

            $model = class_basename($exception->getModel() ?? 'Resource');

            return ErrorEnvelopeService::notFoundError(
                $model,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        });

        $this->renderable(function (NotFoundHttpException $exception, Request $request) {
            if (!$this->isZenaRequest($request)) {
                return null;
            }

            $existingResponse = $this->resolveExceptionResponse($exception);
            if ($this->isEnvelopeResponse($existingResponse)) {
                return $existingResponse;
            }

            return ErrorEnvelopeService::notFoundError(
                'Resource',
                ErrorEnvelopeService::getCurrentRequestId()
            );
        });

        $this->renderable(function (HttpException $exception, Request $request) {
            if (!$this->isZenaRequest($request) || $exception->getStatusCode() !== 400) {
                return null;
            }

            $existingResponse = $this->resolveExceptionResponse($exception);
            if ($this->isEnvelopeResponse($existingResponse)) {
                return $existingResponse;
            }

            return ErrorEnvelopeService::error(
                'E400.BAD_REQUEST',
                $exception->getMessage() ?: 'Bad request',
                [],
                400,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        });

        $this->renderable(function (TokenMismatchException $exception, Request $request) {
            if (!app()->environment('testing')) {
                return null;
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'CSRF token mismatch.'
                ], 419);
            }

            return response('CSRF token mismatch.', 419);
        });

        $this->renderable(function (HttpExceptionInterface $exception, Request $request) {
            if (!app()->environment('testing') ||
                $exception->getStatusCode() !== 419 ||
                $request->expectsJson()) {
                return null;
            }

            return response('CSRF token mismatch.', 419);
        });
    }

    /**
     * Determine whether the current request belongs to the ZENA API surface.
     */
    private function isZenaRequest(Request $request): bool
    {
        $path = ltrim($request->path(), '/');

        if (str_starts_with($path, 'api/zena')) {
            return true;
        }

        $routeName = $request->route()?->getName();
        if ($routeName && str_starts_with($routeName, 'zena.')) {
            return true;
        }

        return false;
    }

    /**
     * Check whether the response has already been wrapped by the error envelope.
     */
    private function isEnvelopeResponse(?JsonResponse $response): bool
    {
        if (!$response) {
            return false;
        }

        $data = $response->getData(true);

        return is_array($data)
            && isset($data['error'])
            && is_array($data['error'])
            && isset($data['error']['id']);
    }

    private function resolveExceptionResponse(Throwable $exception): ?JsonResponse
    {
        if (method_exists($exception, 'getResponse')) {
            $response = $exception->getResponse();
            if ($response instanceof JsonResponse) {
                return $response;
            }
        }

        return null;
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
        if ($this->isZenaRequest($request)) {
            $existingResponse = $this->resolveExceptionResponse($exception);
            if ($this->isEnvelopeResponse($existingResponse)) {
                return $existingResponse;
            }

            return ErrorEnvelopeService::authenticationError(
                'Unauthorized',
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        return parent::unauthenticated($request, $exception);
    }
}
