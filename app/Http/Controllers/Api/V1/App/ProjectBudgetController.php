<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\ProjectBudgetLineStoreRequest;
use App\Http\Requests\ProjectBudgetLineUpdateRequest;
use App\Http\Resources\ProjectBudgetLineResource;
use App\Models\Project;
use App\Services\ProjectBudgetService;
use App\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProjectBudgetController
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 * 
 * Handles budget lines CRUD operations for projects
 */
class ProjectBudgetController extends BaseApiV1Controller
{
    public function __construct(
        private ProjectBudgetService $budgetService,
        private ProjectManagementService $projectService
    ) {}

    /**
     * List budget lines for a project
     * 
     * GET /api/v1/app/projects/{proj}/budget-lines
     */
    public function index(Request $request, string $proj): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            $budgetLines = $this->budgetService->listBudgetLinesForProject($tenantId, $project);

            return $this->successResponse(
                ProjectBudgetLineResource::collection($budgetLines),
                'Budget lines retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index', 'project_id' => $proj]);
            return $this->errorResponse('Failed to retrieve budget lines', 500);
        }
    }

    /**
     * Create budget line for a project
     * 
     * POST /api/v1/app/projects/{proj}/budget-lines
     */
    public function store(ProjectBudgetLineStoreRequest $request, string $proj): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            $budgetLine = $this->budgetService->createBudgetLineForProject(
                $tenantId,
                $project,
                $request->validated()
            );

            return $this->successResponse(
                new ProjectBudgetLineResource($budgetLine),
                'Budget line created successfully',
                201
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'store', 'project_id' => $proj]);
            return $this->errorResponse('Failed to create budget line', 500);
        }
    }

    /**
     * Update budget line for a project
     * 
     * PATCH /api/v1/app/projects/{proj}/budget-lines/{budget_line}
     */
    public function update(ProjectBudgetLineUpdateRequest $request, string $proj, string $budget_line): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            $budgetLine = $this->budgetService->updateBudgetLineForProject(
                $tenantId,
                $project,
                $budget_line,
                $request->validated()
            );

            return $this->successResponse(
                new ProjectBudgetLineResource($budgetLine),
                'Budget line updated successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Budget line not found', 404, null, 'BUDGET_LINE_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'update', 'project_id' => $proj, 'budget_line_id' => $budget_line]);
            return $this->errorResponse('Failed to update budget line', 500);
        }
    }

    /**
     * Delete budget line for a project
     * 
     * DELETE /api/v1/app/projects/{proj}/budget-lines/{budget_line}
     */
    public function destroy(Request $request, string $proj, string $budget_line): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            $this->budgetService->deleteBudgetLineForProject($tenantId, $project, $budget_line);

            return $this->successResponse(null, 'Budget line deleted successfully');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Budget line not found', 404, null, 'BUDGET_LINE_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'destroy', 'project_id' => $proj, 'budget_line_id' => $budget_line]);
            return $this->errorResponse('Failed to delete budget line', 500);
        }
    }
}
