<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Base API V1 Controller
 * 
 * Base controller for all API V1 controllers with standardized response format.
 * All API V1 controllers should extend this class.
 */
abstract class BaseApiV1Controller extends Controller
{
    use AuthorizesRequests;
    /**
     * Return standardized success response
     * 
     * @param mixed $data Response data
     * @param string $message Success message
     * @param int $status HTTP status code
     * @return JsonResponse
     */
    protected function successResponse($data = null, string $message = 'Success', int $status = 200): JsonResponse
    {
        $response = ApiResponse::success($data, $message, $status);
        $payload = $response->getData(true);
        $payload['ok'] = true;
        $payload['traceId'] = $payload['traceId'] ?? $this->resolveTraceId();
        $response->setData($payload);

        return $response;
    }

    /**
     * Return standardized error response
     * 
     * @param string $message Error message
     * @param int $status HTTP status code
     * @param array|null $errors Validation errors or additional error details
     * @param string|null $code Error code
     * @return JsonResponse
     */
    protected function errorResponse(string $message = 'Error', int $status = 400, $errors = null, ?string $code = null): JsonResponse
    {
        $details = $this->resolveErrorDetails($errors, $status);
        $payload = [
            'status' => 'error',
            'success' => false,
            'ok' => false,
            'code' => $code ?? $this->defaultErrorCode($status),
            'message' => $message,
            'traceId' => $this->resolveTraceId(),
            'details' => $details,
            'timestamp' => now()->toISOString(),
            'error' => [
                'id' => $code ?? $this->defaultErrorCode($status),
            ],
        ];

        return response()->json($payload, $status);
    }

    private function resolveTraceId(): string
    {
        return request()->header('X-Request-Id') ??
            request()->header('X-Correlation-Id') ??
            'req_' . uniqid();
    }

    private function resolveErrorDetails($errors, int $status): array
    {
        if ($errors === null) {
            return [];
        }

        if ($status === 422 && is_array($errors)) {
            return ['validation' => $errors];
        }

        if (is_array($errors)) {
            return $errors;
        }

        return ['data' => $errors];
    }

    private function defaultErrorCode(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_FAILED',
            429 => 'RATE_LIMIT_EXCEEDED',
            500 => 'SERVER_ERROR',
            503 => 'SERVICE_UNAVAILABLE',
            default => 'UNKNOWN_ERROR',
        };
    }

    /**
     * Return paginated response
     * 
     * @param array $data Paginated items
     * @param array $meta Pagination metadata
     * @param string $message Success message
     * @param array|null $links Pagination links
     * @return JsonResponse
     */
    protected function paginatedResponse(array $data, array $meta, string $message = 'Data retrieved successfully', ?array $links = null): JsonResponse
    {
        return ApiResponse::paginated($data, $meta, $message, $links);
    }

    /**
     * Get current tenant ID from authenticated user
     * 
     * Uses TenancyService to resolve active tenant (from session, default, or fallback).
     * This ensures consistency with /api/v1/me endpoint and supports multi-tenant scenarios.
     * 
     * Priority:
     * 1. Request attribute 'active_tenant_id' (set by EnsureTenantPermission middleware)
     * 2. TenancyService::resolveActiveTenantId() (session + default + fallback)
     * 3. Legacy user->tenant_id (backward compatibility)
     * 
     * @return string
     * @throws \RuntimeException If user is not authenticated or tenant_id is missing
     */
    protected function getTenantId(): string
    {
        $user = auth()->user();
        
        if (!$user) {
            throw new \RuntimeException('User not authenticated');
        }
        
        $request = request();
        
        // Priority 1: Check request attribute (set by EnsureTenantPermission middleware)
        $activeTenantId = $request->attributes->get('active_tenant_id');
        if ($activeTenantId) {
            return (string) $activeTenantId;
        }
        
        // Priority 2: Use TenancyService to resolve active tenant
        $tenancyService = app(\App\Services\TenancyService::class);
        $resolvedTenantId = $tenancyService->resolveActiveTenantId($user, $request);
        if ($resolvedTenantId) {
            return (string) $resolvedTenantId;
        }
        
        // Priority 3: Fallback to legacy user->tenant_id (backward compatibility)
        if ($user->tenant_id) {
            return (string) $user->tenant_id;
        }
        
        throw new \RuntimeException('Tenant ID not found for user');
    }

    /**
     * Log error with context
     * 
     * @param \Exception $e Exception to log
     * @param array $context Additional context
     * @return void
     */
    protected function logError(\Exception $e, array $context = []): void
    {
        Log::error($e->getMessage(), array_merge([
            'exception' => get_class($e),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], $context));
    }
}
