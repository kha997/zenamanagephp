<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Resources\ProjectTaskResource;
use App\Services\ProjectTaskManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * MyTasksController
 * 
 * Round 213: API controller for "My Tasks" - tasks assigned to the current user
 * 
 * Routes: /api/v1/app/my/tasks
 */
class MyTasksController extends BaseApiV1Controller
{
    public function __construct(
        private ProjectTaskManagementService $projectTaskService
    ) {}

    /**
     * Get tasks assigned to the current user
     * 
     * GET /api/v1/app/my/tasks
     * 
     * Query parameters:
     * - status: 'open' (default), 'completed', or 'all'
     * - range: 'today', 'next_7_days', 'overdue', or 'all' (default)
     * 
     * Round 217: Added range filter support
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $userId = (string) Auth::id();
            
            $filters = [
                'status' => $request->input('status', 'open'),
                'range' => $request->input('range', 'all'),
            ];
            
            $tasks = $this->projectTaskService->listTasksAssignedToUser(
                $tenantId,
                $userId,
                $filters
            );
            
            return $this->successResponse(
                ProjectTaskResource::collection($tasks),
                'My tasks retrieved successfully'
            );
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index']);
            return $this->errorResponse('Failed to retrieve my tasks', 500);
        }
    }
}
