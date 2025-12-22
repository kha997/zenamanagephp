<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Resources\ProjectCostDashboardResource;
use App\Services\ProjectCostDashboardService;
use App\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProjectCostDashboardController
 * 
 * Round 223: Project Cost Dashboard API (Variance + Timeline + Forecast)
 * 
 * Handles read-only cost dashboard operations for projects
 */
class ProjectCostDashboardController extends BaseApiV1Controller
{
    public function __construct(
        private ProjectCostDashboardService $costDashboardService,
        private ProjectManagementService $projectService
    ) {}

    /**
     * Get project cost dashboard
     * 
     * GET /api/v1/app/projects/{proj}/cost-dashboard
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

            $dashboard = $this->costDashboardService->getProjectCostDashboard($tenantId, $project);

            return $this->successResponse(
                new ProjectCostDashboardResource($dashboard),
                'Cost dashboard retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'show', 'project_id' => $proj]);
            return $this->errorResponse('Failed to retrieve cost dashboard', 500);
        }
    }
}
