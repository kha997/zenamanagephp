<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\Api\V1\App\TaskTemplateStoreRequest;
use App\Http\Requests\Api\V1\App\TaskTemplateUpdateRequest;
use App\Services\TaskTemplateManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * TaskTemplate API Controller (V1)
 * 
 * API controller for task template operations.
 * Task templates belong to a Template (project template) and define checklist items.
 * 
 * Routes: /api/v1/app/templates/{tpl}/task-templates
 */
class TaskTemplateController extends BaseApiV1Controller
{
    public function __construct(
        private TaskTemplateManagementService $taskTemplateService
    ) {}

    /**
     * Get task templates list for a template (API)
     * 
     * @param Request $request
     * @param string $tpl Template ID
     * @return JsonResponse
     */
    public function index(Request $request, string $tpl): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $filters = $request->only([
                'is_required',
                'search'
            ]);
            
            $sortBy = $request->get('sort_by', 'order_index');
            $sortDirection = $request->get('sort_direction', 'asc');
            $perPage = (int) $request->get('per_page', 15);
            
            $taskTemplates = $this->taskTemplateService->listTaskTemplatesForTemplate(
                $tenantId,
                $tpl,
                $filters,
                $perPage,
                $sortBy,
                $sortDirection
            );

            if (method_exists($taskTemplates, 'items')) {
                return $this->paginatedResponse(
                    $taskTemplates->items(),
                    [
                        'current_page' => $taskTemplates->currentPage(),
                        'per_page' => $taskTemplates->perPage(),
                        'total' => $taskTemplates->total(),
                        'last_page' => $taskTemplates->lastPage(),
                        'from' => $taskTemplates->firstItem(),
                        'to' => $taskTemplates->lastItem(),
                    ],
                    'Task templates retrieved successfully',
                    [
                        'first' => $taskTemplates->url(1),
                        'last' => $taskTemplates->url($taskTemplates->lastPage()),
                        'prev' => $taskTemplates->previousPageUrl(),
                        'next' => $taskTemplates->nextPageUrl(),
                    ]
                );
            }

            return $this->successResponse($taskTemplates, 'Task templates retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index', 'template_id' => $tpl]);
            return $this->errorResponse('Failed to retrieve task templates', 500);
        }
    }

    /**
     * Create task template for a template (API)
     * 
     * @param TaskTemplateStoreRequest $request
     * @param string $tpl Template ID
     * @return JsonResponse
     */
    public function store(TaskTemplateStoreRequest $request, string $tpl): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $taskTemplate = $this->taskTemplateService->createTaskTemplateForTemplate(
                $tenantId,
                $tpl,
                $request->validated()
            );
            
            return $this->successResponse(
                $taskTemplate,
                'Task template created successfully',
                201
            );
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Re-throw HTTP exceptions (like 404 from abort())
            throw $e;
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'store', 'template_id' => $tpl]);
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Update task template for a template (API)
     * 
     * @param TaskTemplateUpdateRequest $request
     * @param string $tpl Template ID
     * @param string $taskTemplateId Task template ID
     * @return JsonResponse
     */
    public function update(TaskTemplateUpdateRequest $request, string $tpl, string $taskTemplateId): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // updateTaskTemplateForTemplate already handles the lookup and returns 404 if not found
            $taskTemplate = $this->taskTemplateService->updateTaskTemplateForTemplate(
                $tenantId,
                $tpl,
                $taskTemplateId,
                $request->validated()
            );
            
            return $this->successResponse($taskTemplate, 'Task template updated successfully');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Re-throw HTTP exceptions (like 404 from abort())
            throw $e;
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'update', 'template_id' => $tpl, 'task_template_id' => $taskTemplateId]);
            return $this->errorResponse(
                $e->getMessage() ?: 'An error occurred while updating the task template',
                $e->getCode() && $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500
            );
        }
    }

    /**
     * Delete task template for a template (API)
     * 
     * @param string $tpl Template ID
     * @param string $taskTemplateId Task template ID
     * @return JsonResponse
     */
    public function destroy(string $tpl, string $taskTemplateId): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // deleteTaskTemplateForTemplate already handles the lookup and returns 404 if not found
            $this->taskTemplateService->deleteTaskTemplateForTemplate($tenantId, $tpl, $taskTemplateId);
            
            return $this->successResponse(null, 'Task template deleted successfully', 200);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Re-throw HTTP exceptions (like 404 from abort())
            throw $e;
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'destroy', 'template_id' => $tpl, 'task_template_id' => $taskTemplateId]);
            return $this->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }
}
