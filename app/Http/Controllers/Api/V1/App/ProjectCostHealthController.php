<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Resources\ProjectCostHealthResource;
use App\Services\ProjectCostHealthService;
use App\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProjectCostHealthController
 * 
 * Round 226: Project Cost Health Status + Alert Indicators
 * 
 * Handles read-only cost health operations for projects
 */
class ProjectCostHealthController extends BaseApiV1Controller
{
    public function __construct(
        private ProjectCostHealthService $costHealthService,
        private ProjectManagementService $projectService
    ) {}

    /**
     * Get project cost health
     * 
     * GET /api/v1/app/projects/{proj}/cost-health
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

            $health = $this->costHealthService->getCostHealth($tenantId, $project);

            return $this->successResponse(
                new ProjectCostHealthResource($health),
                'Cost health retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'show', 'project_id' => $proj]);
            return $this->errorResponse('Failed to retrieve cost health', 500);
        }
    }
}
