<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * Base API Controller implementing JSend specification
 * 
 * JSend is a specification for a simple, no-frills, JSON based format 
 * for application-level communication.
 */
abstract class BaseApiController extends Controller
{
    /**
     * Default pagination limit
     */
    protected int $defaultLimit = 15;
    
    /**
     * Maximum pagination limit
     */
    protected int $maxLimit = 100;

    /**
     * Return a success response
     *
     * @param mixed $data The data to return
     * @param string|null $message Optional success message
     * @param int $statusCode HTTP status code
     * @return JsonResponse
     */
    protected function successResponse($data = null, ?string $message = null, int $statusCode = 200): JsonResponse
    {
        $response = ['status' => 'success'];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        return response()->json($response, $statusCode);
    }

    /**
     * Return an error response
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param mixed $data Optional error data
     * @return JsonResponse
     */
    protected function errorResponse(string $message, int $statusCode = 400, $data = null): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return response()->json($response, $statusCode);
    }

    /**
     * Return a fail response (client error)
     *
     * @param mixed $data Error data (usually validation errors)
     * @param string|null $message Optional fail message
     * @param int $statusCode HTTP status code
     * @return JsonResponse
     */
    protected function failResponse($data, ?string $message = null, int $statusCode = 422): JsonResponse
    {
        $response = [
            'status' => 'fail',
            'data' => $data
        ];
        
        if ($message !== null) {
            $response['message'] = $message;
        }
        
        return response()->json($response, $statusCode);
    }

    /**
     * Return a paginated response
     *
     * @param LengthAwarePaginator $paginator
     * @param string|null $message
     * @return JsonResponse
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, ?string $message = null): JsonResponse
    {
        return $this->successResponse([
            'items' => $paginator->items(),
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages()
            ]
        ], $message);
    }

    /**
     * Get pagination parameters from request
     *
     * @param Request $request
     * @return array
     */
    protected function getPaginationParams(Request $request): array
    {
        $limit = (int) $request->get('limit', $this->defaultLimit);
        $page = (int) $request->get('page', 1);
        
        // Ensure limit is within bounds
        $limit = min(max($limit, 1), $this->maxLimit);
        $page = max($page, 1);
        
        return [
            'limit' => $limit,
            'page' => $page,
            'offset' => ($page - 1) * $limit
        ];
    }

    /**
     * Get sorting parameters from request
     *
     * @param Request $request
     * @param array $allowedFields
     * @param string $defaultField
     * @param string $defaultDirection
     * @return array
     */
    protected function getSortingParams(
        Request $request, 
        array $allowedFields = ['id'], 
        string $defaultField = 'id', 
        string $defaultDirection = 'desc'
    ): array {
        $sortBy = $request->get('sort_by', $defaultField);
        $sortDirection = $request->get('sort_direction', $defaultDirection);
        
        // Validate sort field
        if (!in_array($sortBy, $allowedFields)) {
            $sortBy = $defaultField;
        }
        
        // Validate sort direction
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = $defaultDirection;
        }
        
        return [
            'sort_by' => $sortBy,
            'sort_direction' => strtolower($sortDirection)
        ];
    }

    /**
     * Get filtering parameters from request
     *
     * @param Request $request
     * @param array $allowedFilters
     * @return array
     */
    protected function getFilterParams(Request $request, array $allowedFilters = []): array
    {
        $filters = [];
        
        foreach ($allowedFilters as $filter) {
            if ($request->has($filter) && $request->get($filter) !== null) {
                $filters[$filter] = $request->get($filter);
            }
        }
        
        return $filters;
    }

    /**
     * Validate resource ownership for tenant isolation
     *
     * @param mixed $resource
     * @param Request $request
     * @return bool
     */
    protected function validateTenantAccess($resource, Request $request): bool
    {
        $user = $request->user('api');
        
        if (!$user || !$user->tenant_id) {
            return false;
        }
        
        // Check if resource has tenant_id property
        if (property_exists($resource, 'tenant_id')) {
            return $resource->tenant_id === $user->tenant_id;
        }
        
        // For resources that belong to a project, check project's tenant
        if (property_exists($resource, 'project') && $resource->project) {
            return $resource->project->tenant_id === $user->tenant_id;
        }
        
        return true; // Default to allow if no tenant check needed
    }
}