<?php declare(strict_types=1);

namespace Src\WorkTemplate\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Src\WorkTemplate\Models\ProjectTask;
use Src\WorkTemplate\Requests\UpdateTaskRequest;
use Src\WorkTemplate\Requests\ToggleConditionalRequest;
use Src\WorkTemplate\Resources\ProjectTaskResource;
use Src\WorkTemplate\Resources\ProjectTaskCollection;
use Src\WorkTemplate\Services\ProjectTaskService;
use Src\Foundation\Utils\JSendResponse;

/**
 * ProjectTaskController
 * 
 * Quản lý các API endpoints cho Project Tasks
 * Bao gồm operations cho conditional tags và task management
 */
class ProjectTaskController extends Controller
{
    /**
     * Project task service để xử lý business logic
     */
    protected ProjectTaskService $taskService;

    /**
     * Constructor
     */
    public function __construct(ProjectTaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /**
     * Lấy danh sách tasks của project với filtering
     * 
     * @param Request $request
     * @param string $projectId
     * @return JsonResponse
     */
    public function index(Request $request, string $projectId): JsonResponse
    {
        $query = ProjectTask::query()
            ->with(['phase', 'template'])
            ->byProject($projectId);
        
        // Filter theo phase nếu có
        if ($request->has('phase_id')) {
            $query->byPhase($request->get('phase_id'));
        }
        
        // Filter theo status nếu có
        if ($request->has('status')) {
            $query->byStatus($request->get('status'));
        }
        
        // Filter theo visibility (bao gồm cả hidden tasks nếu requested)
        if (!$request->boolean('include_hidden', false)) {
            $query->visible();
        }
        
        // Filter theo conditional tag nếu có
        if ($request->has('conditional_tag')) {
            $query->byConditionalTag($request->get('conditional_tag'));
        }
        
        // Search theo name nếu có
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->get('search') . '%');
        }
        
        // Sắp xếp
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);
        
        // Pagination
        $perPage = min($request->get('per_page', 20), 100);
        $tasks = $query->paginate($perPage);
        
        return JSendResponse::success(
            new ProjectTaskCollection($tasks)
        );
    }

    /**
     * Lấy chi tiết task theo ID
     * 
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function show(string $projectId, string $taskId): JsonResponse
    {
        $task = ProjectTask::with(['phase', 'template', 'project'])
            ->byProject($projectId)
            ->find($taskId);
        
        if (!$task) {
            return JSendResponse::error('Task không tồn tại trong project này', 404);
        }
        
        return JSendResponse::success(
            new ProjectTaskResource($task)
        );
    }

    /**
     * Cập nhật task
     * 
     * @param UpdateTaskRequest $request
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function update(UpdateTaskRequest $request, string $projectId, string $taskId): JsonResponse
    {
        $task = ProjectTask::byProject($projectId)->find($taskId);
        
        if (!$task) {
            return JSendResponse::error('Task không tồn tại trong project này', 404);
        }
        
        try {
            $updatedTask = $this->taskService->updateTask(
                $task,
                $request->validated(),
                $request->user('api')->id  // Sửa từ $request->user()->id
            );
            
            return JSendResponse::success(
                new ProjectTaskResource($updatedTask->load(['phase', 'template']))
            );
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Failed to update task: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Toggle conditional tag visibility của task
     * 
     * @param ToggleConditionalRequest $request
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function toggleConditional(ToggleConditionalRequest $request, string $projectId, string $taskId): JsonResponse
    {
        $task = ProjectTask::byProject($projectId)->find($taskId);
        
        if (!$task) {
            return JSendResponse::error('Task không tồn tại trong project này', 404);
        }
        
        if (!$task->hasConditionalTag()) {
            return JSendResponse::error('Task này không có conditional tag', 400);
        }
        
        try {
            $isVisible = $request->has('is_visible')
                ? $request->boolean('is_visible')
                : $task->is_hidden;

            $this->taskService->toggleConditionalVisibility(
                $task,
                $isVisible,
                $request->user('api')->id  // Sửa từ $request->user()->id
            );

            return JSendResponse::success([
                'task' => new ProjectTaskResource($task->fresh()),
                'message' => 'Conditional visibility updated'
            ]);
        } catch (\Exception $e) {
            return JSendResponse::error(
                'Failed to toggle task visibility: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Cập nhật progress của task
     * 
     * @param Request $request
     * @param string $projectId
     * @param string $taskId
     * @return JsonResponse
     */
    public function updateProgress(Request $request, string $projectId, string $taskId): JsonResponse
    {
        $request->validate([
            'progress_percent' => 'required|numeric|min:0|max:100'
        ]);
        
        $task = ProjectTask::byProject($projectId)->find($taskId);
        
        if (!$task) {
            return $this->errorResponse('Task not found', 404);
        }
        
        try {
            $task->updateProgress($request->get('progress_percent'));
            
            return $this->successResponse(
                new ProjectTaskResource($task->fresh(['phase', 'template'])),
                'Task progress updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update task progress: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Lấy danh sách conditional tags có sẵn trong project
     * 
     * @param string $projectId
     * @return JsonResponse
     */
    public function conditionalTags(string $projectId): JsonResponse
    {
        $tags = ProjectTask::byProject($projectId)
            ->whereNotNull('conditional_tag')
            ->distinct()
            ->pluck('conditional_tag')
            ->filter()
            ->values();
        
        return $this->successResponse(
            $tags,
            'Conditional tags retrieved successfully'
        );
    }

    /**
     * Lấy tasks có conditional tags và summary
     */
    public function conditionalTasks(string $projectId): JsonResponse
    {
        $tasks = ProjectTask::with('phase')
            ->byProject($projectId)
            ->whereNotNull('conditional_tag')
            ->get();

        $summary = [
            'total_conditional_tasks' => $tasks->count(),
            'hidden_tasks' => $tasks->where('is_hidden', true)->count(),
            'visible_tasks' => $tasks->where('is_hidden', false)->count(),
            'conditional_tags' => $tasks->pluck('conditional_tag')->filter()->unique()->values(),
        ];

        return $this->successResponse([
            'tasks' => ProjectTaskResource::collection($tasks),
            'summary' => $summary,
        ], 'Conditional tasks retrieved successfully');
    }

    /**
     * Lấy statistics của tasks trong project
     * 
     * @param string $projectId
     * @return JsonResponse
     */
    public function statistics(string $projectId): JsonResponse
    {
        $stats = $this->taskService->getProjectTaskStatistics($projectId);
        
        return $this->successResponse(
            $stats,
            'Task statistics retrieved successfully'
        );
    }

    /**
     * Convenience helper for success responses.
     */
    private function successResponse($data, string $message = 'Success'): JsonResponse
    {
        return JSendResponse::success($data, $message);
    }
}
