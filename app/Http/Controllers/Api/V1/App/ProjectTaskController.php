<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Http\Requests\ProjectTaskReorderRequest;
use App\Http\Requests\ProjectTaskUpdateRequest;
use App\Http\Resources\ProjectTaskResource;
use App\Models\ProjectActivity;
use App\Services\ProjectManagementService;
use App\Services\ProjectTaskManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * ProjectTask API Controller (V1)
 * 
 * API controller for project task operations.
 * Project tasks belong to a Project and can be auto-generated from TaskTemplates.
 * 
 * Round 202: MVP - List tasks for a project
 * Round 206: Added update, complete, incomplete endpoints
 * 
 * Routes: /api/v1/app/projects/{proj}/tasks
 */
class ProjectTaskController extends BaseApiV1Controller
{
    public function __construct(
        private ProjectTaskManagementService $projectTaskService,
        private ProjectManagementService $projectService
    ) {}

    /**
     * Get project tasks list for a project (API)
     * 
     * @param Request $request
     * @param string $proj Project ID
     * @return JsonResponse
     */
    public function index(Request $request, string $proj): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $filters = $request->only([
                'status',
                'is_milestone',
                'is_hidden',
                'search'
            ]);
            
            $sortBy = $request->get('sort_by', 'sort_order');
            $sortDirection = $request->get('sort_direction', 'asc');
            $perPage = (int) $request->get('per_page', 15);
            
            $tasks = $this->projectTaskService->listTasksForProject(
                $tenantId,
                $proj,
                $filters,
                $perPage,
                $sortBy,
                $sortDirection
            );

            if (method_exists($tasks, 'items')) {
                return $this->paginatedResponse(
                    $tasks->items(),
                    [
                        'current_page' => $tasks->currentPage(),
                        'per_page' => $tasks->perPage(),
                        'total' => $tasks->total(),
                        'last_page' => $tasks->lastPage(),
                        'from' => $tasks->firstItem(),
                        'to' => $tasks->lastItem(),
                    ],
                    'Project tasks retrieved successfully',
                    [
                        'first' => $tasks->url(1),
                        'last' => $tasks->url($tasks->lastPage()),
                        'prev' => $tasks->previousPageUrl(),
                        'next' => $tasks->nextPageUrl(),
                    ]
                );
            }

            return $this->successResponse($tasks, 'Project tasks retrieved successfully');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Re-throw HTTP exceptions (like 404 from abort())
            throw $e;
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index', 'project_id' => $proj]);
            return $this->errorResponse('Failed to retrieve project tasks', 500);
        }
    }

    /**
     * Update project task
     * 
     * Round 206: Update task fields (name, description, status, due_date, sort_order, is_milestone)
     * 
     * @param ProjectTaskUpdateRequest $request
     * @param string $proj Project ID
     * @param string $proj_task Task ID
     * @return JsonResponse
     */
    public function update(ProjectTaskUpdateRequest $request, string $proj, string $proj_task): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }
            
            // Get task before update for activity logging
            $taskBefore = $this->projectTaskService->findTaskForProjectOrFail($tenantId, $project, $proj_task, false);
            $beforeData = [
                'status' => $taskBefore->status,
                'is_completed' => $taskBefore->is_completed,
            ];
            
            $task = $this->projectTaskService->updateTaskForProject(
                $tenantId,
                $project,
                $proj_task,
                $request->validated()
            );
            
            // Log activity
            $afterData = [
                'status' => $task->status,
                'is_completed' => $task->is_completed,
            ];
            
            ProjectActivity::logProjectTaskUpdated(
                $task,
                (string) Auth::id(),
                [
                    'before' => $beforeData,
                    'after' => $afterData,
                ]
            );
            
            return $this->successResponse(
                new ProjectTaskResource($task),
                'Task updated successfully'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Task not found', 404);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'update', 'project_id' => $proj, 'task_id' => $proj_task]);
            return $this->errorResponse('Failed to update task', 500);
        }
    }

    /**
     * Mark task as completed
     * 
     * Round 206: Mark task as completed with timestamp
     * 
     * @param string $proj Project ID
     * @param string $proj_task Task ID
     * @return JsonResponse
     */
    public function complete(string $proj, string $proj_task): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }
            
            $task = $this->projectTaskService->markTaskCompletedForProject(
                $tenantId,
                $project,
                $proj_task
            );
            
            // Log activity
            ProjectActivity::logProjectTaskCompleted($task, (string) Auth::id());
            
            return $this->successResponse(
                new ProjectTaskResource($task),
                'Task marked as completed'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Task not found', 404);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'complete', 'project_id' => $proj, 'task_id' => $proj_task]);
            return $this->errorResponse('Failed to complete task', 500);
        }
    }

    /**
     * Mark task as incomplete
     * 
     * Round 206: Mark task as incomplete, clear completion timestamp
     * 
     * @param string $proj Project ID
     * @param string $proj_task Task ID
     * @return JsonResponse
     */
    public function incomplete(string $proj, string $proj_task): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }
            
            // Round 208: Get task before update to capture completed_at
            $taskBefore = $this->projectTaskService->findTaskForProjectOrFail($tenantId, $project, $proj_task, false);
            $completedAtBefore = $taskBefore->completed_at?->toISOString();
            
            $task = $this->projectTaskService->markTaskIncompleteForProject(
                $tenantId,
                $project,
                $proj_task
            );
            
            // Log activity with completed_at_before
            ProjectActivity::logProjectTaskMarkedIncomplete($task, (string) Auth::id(), $completedAtBefore);
            
            return $this->successResponse(
                new ProjectTaskResource($task),
                'Task marked as incomplete'
            );
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return $this->errorResponse('Task not found', 404);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'incomplete', 'project_id' => $proj, 'task_id' => $proj_task]);
            return $this->errorResponse('Failed to mark task as incomplete', 500);
        }
    }

    /**
     * Reorder tasks for project
     * 
     * Round 210: Reorder tasks within a project by updating sort_order
     * 
     * @param ProjectTaskReorderRequest $request
     * @param string $proj Project ID
     * @return JsonResponse
     */
    public function reorder(ProjectTaskReorderRequest $request, string $proj): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            $project = $this->projectService->getProjectById($proj, $tenantId);
            if (!$project) {
                return $this->errorResponse('Project not found', 404);
            }
            
            $this->projectTaskService->reorderTasksForProject(
                $tenantId,
                $project,
                $request->validated()['ordered_ids']
            );
            
            // Return 204 No Content as recommended
            return response()->json(null, 204);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Re-throw HTTP exceptions (like 404 from abort())
            throw $e;
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'reorder', 'project_id' => $proj]);
            return $this->errorResponse('Failed to reorder tasks', 500);
        }
    }
}
