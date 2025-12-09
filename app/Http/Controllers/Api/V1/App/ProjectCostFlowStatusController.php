<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Resources\ProjectCostFlowStatusResource;
use App\Services\ProjectCostFlowStatusService;
use App\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProjectCostFlowStatusController
 * 
 * Round 232: Project Cost Flow Status
 * 
 * Handles read-only cost flow status operations for projects
 */
class ProjectCostFlowStatusController extends BaseApiV1Controller
{
    public function __construct(
        private ProjectCostFlowStatusService $flowStatusService,
        private ProjectManagementService $projectService
    ) {}

    /**
     * Get project cost flow status
     * 
     * GET /api/v1/app/projects/{proj}/cost-flow-status
     * 
     * @param Request $request
     * @param string $proj Project ID
     * @return JsonResponse
     */
    public function show(Request $request, string $proj): JsonResponse
    {
        try {
            $user = $request->user();
            if (!$user || !$user->hasPermission('projects.cost.view')) {
                return $this->errorResponse('You do not have permission to view cost data', 403, null, 'COST_VIEW_PERMISSION_DENIED');
            }
            
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
            }

            $flowStatus = $this->flowStatusService->getFlowStatus($tenantId, $project);

            return $this->successResponse(
                new ProjectCostFlowStatusResource($flowStatus),
                'Cost flow status retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'show', 'project_id' => $proj]);
            return $this->errorResponse('Failed to retrieve cost flow status', 500);
        }
    }
}
