<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Api\BaseApiController as ApiBaseController;
use Illuminate\Http\JsonResponse;

/**
 * Base API Controller
 * 
 * Base controller cho tất cả API controllers
 */
abstract class BaseApiController extends ApiBaseController
{
    /**
     * Return success response
     */
    protected function success($data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        return $this->successResponse($data, $message, $status);
    }

    /**
     * Return error response using the legacy schema.
     */
    public function error(string $message = 'Error', int $statusCode = 400, $data = null): JsonResponse
    {
        $payload = [
            'success' => false,
            'message' => $message,
        ];

        if ($data !== null) {
            $payload['errors'] = is_array($data) && array_key_exists('errors', $data)
                ? $data['errors']
                : $data;
        }

        return response()->json($payload, $statusCode);
    }

    /**
     * Legacy helper for code that relied on the old error() signature.
     */
    public function errorWithErrors(string $message = 'Error', $errors = null, int $status = 400): JsonResponse
    {
        return $this->error($message, $status, ['errors' => $errors]);
    }

    /**
     * Alias for legacy error helpers.
     */
    public function errorLegacy(string $message = 'Error', $errors = null, int $status = 400): JsonResponse
    {
        return $this->errorWithErrors($message, $errors, $status);
    }

    /**
     * Return validation error response
     */
    protected function validationError($errors, string $message = 'Validation failed'): JsonResponse
    {
        return $this->errorResponse($message, $errors, 422);
    }

    /**
     * Return not found response
     */
    protected function notFound(string $message = 'Resource not found'): JsonResponse
    {
        return $this->errorResponse($message, null, 404);
    }

    /**
     * Return unauthorized response
     */
    protected function unauthorized(string $message = 'Unauthorized'): JsonResponse
    {
        return $this->errorResponse($message, null, 401);
    }

    /**
     * Return forbidden response
     */
    protected function forbidden(string $message = 'Forbidden'): JsonResponse
    {
        return $this->errorResponse($message, null, 403);
    }

}
