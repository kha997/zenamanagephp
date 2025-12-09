<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\Api\V1\App\TemplateStoreRequest;
use App\Http\Requests\Api\V1\App\TemplateUpdateRequest;
use App\Services\TemplateManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Template API Controller (V1)
 * 
 * Pure API controller for template operations.
 * Only returns JSON responses - no view rendering.
 * 
 * Follows the same pattern as ProjectsController
 */
class TemplateController extends BaseApiV1Controller
{
    public function __construct(
        private TemplateManagementService $templateService
    ) {}

    /**
     * Get templates list (API)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $filters = $request->only([
                'type',
                'is_active',
                'search'
            ]);
            
            $sortBy = $request->get('sort_by', 'updated_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $perPage = (int) $request->get('per_page', 15);
            
            $templates = $this->templateService->listTemplatesForTenant(
                $tenantId,
                $filters,
                $perPage,
                $sortBy,
                $sortDirection
            );

            if (method_exists($templates, 'items')) {
                return $this->paginatedResponse(
                    $templates->items(),
                    [
                        'current_page' => $templates->currentPage(),
                        'per_page' => $templates->perPage(),
                        'total' => $templates->total(),
                        'last_page' => $templates->lastPage(),
                        'from' => $templates->firstItem(),
                        'to' => $templates->lastItem(),
                    ],
                    'Templates retrieved successfully',
                    [
                        'first' => $templates->url(1),
                        'last' => $templates->url($templates->lastPage()),
                        'prev' => $templates->previousPageUrl(),
                        'next' => $templates->nextPageUrl(),
                    ]
                );
            }

            return $this->successResponse($templates, 'Templates retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index']);
            return $this->errorResponse('Failed to retrieve templates', 500);
        }
    }

    /**
     * Get template by ID (API)
     * 
     * @param string $tpl Template ID
     * @return JsonResponse
     */
    public function show(string $tpl): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $template = $this->templateService->getTemplateById($tenantId, $tpl);
            
            if (!$template) {
                return $this->errorResponse('Template not found', 404, null, 'TEMPLATE_NOT_FOUND');
            }

            return $this->successResponse($template, 'Template retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'show', 'template_id' => $tpl]);
            return $this->errorResponse('Failed to retrieve template', 500);
        }
    }

    /**
     * Create template (API)
     * 
     * @param TemplateStoreRequest $request
     * @return JsonResponse
     */
    public function store(TemplateStoreRequest $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $template = $this->templateService->createTemplateForTenant($tenantId, $request->validated());
            
            return $this->successResponse(
                $template,
                'Template created successfully',
                201
            );
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'store']);
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Update template (API)
     * 
     * @param TemplateUpdateRequest $request
     * @param string $tpl Template ID
     * @return JsonResponse
     */
    public function update(TemplateUpdateRequest $request, string $tpl): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // updateTemplateForTenant already handles the lookup and returns 404 if not found
            $template = $this->templateService->updateTemplateForTenant($tenantId, $tpl, $request->validated());
            
            return $this->successResponse($template, 'Template updated successfully');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Re-throw HTTP exceptions (like 404 from abort())
            throw $e;
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'update', 'template_id' => $tpl]);
            return $this->errorResponse(
                $e->getMessage() ?: 'An error occurred while updating the template',
                $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Delete template (API)
     * 
     * @param string $tpl Template ID
     * @return JsonResponse
     */
    public function destroy(string $tpl): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // deleteTemplateForTenant already handles the lookup and returns 404 if not found
            $this->templateService->deleteTemplateForTenant($tenantId, $tpl);
            
            return $this->successResponse(null, 'Template deleted successfully', 200);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Re-throw HTTP exceptions (like 404 from abort())
            throw $e;
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'destroy', 'template_id' => $tpl]);
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }
}

