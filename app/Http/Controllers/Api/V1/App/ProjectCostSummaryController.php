<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Resources\ProjectCostSummaryResource;
use App\Models\Project;
use App\Services\ProjectCostSummaryService;
use App\Services\ProjectManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * ProjectCostSummaryController
 * 
 * Round 222: Project Cost Summary API (Budget vs Contract vs Actual)
 * 
 * Handles read-only cost summary operations for projects
 */
class ProjectCostSummaryController extends BaseApiV1Controller
{
    public function __construct(
        private ProjectCostSummaryService $costSummaryService,
        private ProjectManagementService $projectService
    ) {}

    /**
     * Get project cost summary
     * 
     * GET /api/v1/app/projects/{proj}/cost-summary
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

            $summary = $this->costSummaryService->getProjectCostSummary($tenantId, $project);

            return $this->successResponse(
                new ProjectCostSummaryResource($summary),
                'Cost summary retrieved successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Project not found', 404, null, 'PROJECT_NOT_FOUND');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'show', 'project_id' => $proj]);
            return $this->errorResponse('Failed to retrieve cost summary', 500);
        }
    }
}
