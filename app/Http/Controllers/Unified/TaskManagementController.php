<?php declare(strict_types=1);

namespace App\Http\Controllers\Unified;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Services\TaskManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Unified Task Management Controller
 * 
 * Handles all task-related API operations including CRUD,
 * bulk actions, filtering, and task management features.
 */
class TaskManagementController extends Controller
{
    public function __construct(
        private TaskManagementService $taskService
    ) {}

    /**
     * Get tasks with filtering and pagination
     */
    public function getTasks(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'project_id',
                'status',
                'priority',
                'assignee_id',
                'search',
                'start_date_from',
                'start_date_to',
                'end_date_from',
                'end_date_to',
                'sort_by',
                'sort_direction',
                'per_page'
            ]);

            $tasks = $this->taskService->getTasks($filters, auth()->user()->tenant_id);

            return ApiResponse::success([
                'data' => $tasks->items(),
                'meta' => [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                    'from' => $tasks->firstItem(),
                    'to' => $tasks->lastItem()
                ]
            ], 'Tasks retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to get tasks', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id
            ]);

            return ApiResponse::error('Failed to retrieve tasks', 500);
        }
    }

    /**
     * Get task by ID
     */
    public function getTask(string $projectId, string $id): JsonResponse
    {
        try {
            $task = $this->taskService->getTaskById($id, (string) auth()->user()->tenant_id);

            if (!$task) {
                return ApiResponse::error('Task not found', 404);
            }

            return ApiResponse::success($task, 'Task retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to get task', [
                'task_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id
            ]);

            return ApiResponse::error('Failed to retrieve task', 500);
        }
    }

    /**
     * Create new task
     */
    public function createTask(Request $request, string $projectId): JsonResponse
    {
        try {
            $request->validate([
                'project_id' => 'required|string|ulid',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|string|in:' . implode(',', \App\Models\Task::VALID_STATUSES),
                'priority' => 'nullable|string|in:' . implode(',', \App\Models\Task::VALID_PRIORITIES),
                'assignee_id' => 'nullable|string|ulid',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'estimated_hours' => 'nullable|numeric|min:0',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50'
            ]);

            $task = $this->taskService->createTask($request->all(), (string) auth()->user()->tenant_id);

            return ApiResponse::success($task, 'Task created successfully', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to create task', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id
            ]);

            return ApiResponse::error('Failed to create task', 500);
        }
    }

    /**
     * Update task
     */
    public function updateTask(Request $request, string $projectId, string $id): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|string|in:' . implode(',', \App\Models\Task::VALID_STATUSES),
                'priority' => 'sometimes|string|in:' . implode(',', \App\Models\Task::VALID_PRIORITIES),
                'assignee_id' => 'nullable|string|ulid',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'estimated_hours' => 'nullable|numeric|min:0',
                'actual_hours' => 'nullable|numeric|min:0',
                'progress_percent' => 'nullable|numeric|min:0|max:100',
                'tags' => 'nullable|array',
                'tags.*' => 'string|max:50'
            ]);

            $task = $this->taskService->updateTask($id, $request->all(), (string) auth()->user()->tenant_id);

            return ApiResponse::success($task, 'Task updated successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to update task', [
                'task_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id
            ]);

            return ApiResponse::error('Failed to update task', 500);
        }
    }

    /**
     * Delete task
     */
    public function deleteTask(string $projectId, string $id): JsonResponse
    {
        try {
            $deleted = $this->taskService->deleteTask($id, (string) auth()->user()->tenant_id);

            if (!$deleted) {
                return ApiResponse::error('Task not found', 404);
            }

            return ApiResponse::success(null, 'Task deleted successfully');

        } catch (\Exception $e) {
            Log::error('Failed to delete task', [
                'task_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id
            ]);

            return ApiResponse::error('Failed to delete task', 500);
        }
    }

    /**
     * Bulk delete tasks
     */
    public function bulkDeleteTasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|string|ulid'
            ]);

            $result = $this->taskService->bulkDeleteTasks($request->input('ids'), auth()->user()->tenant_id);

            return ApiResponse::success($result, $result['message']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to bulk delete tasks', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id
            ]);

            return ApiResponse::error('Failed to delete tasks', 500);
        }
    }

    /**
     * Bulk update task status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|string|ulid',
                'status' => 'required|string|in:' . implode(',', \App\Models\Task::VALID_STATUSES)
            ]);

            $result = $this->taskService->bulkUpdateStatus(
                $request->input('ids'),
                $request->input('status'),
                auth()->user()->tenant_id
            );

            return ApiResponse::success($result, $result['message']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to bulk update task status', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id
            ]);

            return ApiResponse::error('Failed to update task status', 500);
        }
    }

    /**
     * Bulk assign tasks
     */
    public function bulkAssignTasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|string|ulid',
                'assignee_id' => 'required|string|ulid'
            ]);

            $result = $this->taskService->bulkAssignTasks(
                $request->input('ids'),
                $request->input('assignee_id'),
                auth()->user()->tenant_id
            );

            return ApiResponse::success($result, $result['message']);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to bulk assign tasks', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id
            ]);

            return ApiResponse::error('Failed to assign tasks', 500);
        }
    }

    /**
     * Get tasks for project
     */
    public function getTasksForProject(Request $request, string $projectId): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $status = $request->get('status');
            $search = $request->get('search');
            $assignee = $request->get('assigned_to');

            // Check if user has access to this project
            $project = Project::where('id', $projectId)
                ->where('tenant_id', auth()->user()->tenant_id)
                ->first();

            if (!$project) {
                return ApiResponse::forbidden('Access denied to this project');
            }

            $query = Task::with(['assignee', 'creator'])
                ->where('project_id', $projectId)
                ->where('tenant_id', auth()->user()->tenant_id);

            // Apply filters
            if ($status) {
                $query->where('status', $status);
            }

            if ($search) {
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            if ($assignee) {
                $query->where('assigned_to', $assignee);
            }

            $tasks = $query->orderBy('priority', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return ApiResponse::paginated($tasks->items(), [
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
                'per_page' => $tasks->perPage(),
                'total' => $tasks->total(),
                'from' => $tasks->firstItem(),
                'to' => $tasks->lastItem(),
            ], 'Project tasks retrieved successfully', [
                'first' => $tasks->url(1),
                'last' => $tasks->url($tasks->lastPage()),
                'prev' => $tasks->previousPageUrl(),
                'next' => $tasks->nextPageUrl(),
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get project tasks', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id
            ]);

            return ApiResponse::error('Failed to retrieve project tasks', 500);
        }
    }

    /**
     * Get task statistics
     */
    public function getTaskStatistics(): JsonResponse
    {
        try {
            $stats = $this->taskService->getTaskStatistics((string) auth()->user()->tenant_id);

            return ApiResponse::success($stats, 'Task statistics retrieved successfully');

        } catch (\Exception $e) {
            Log::error('Failed to get task statistics', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id
            ]);

            return ApiResponse::error('Failed to retrieve task statistics', 500);
        }
    }

    /**
     * Update task progress
     */
    public function updateTaskProgress(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'progress' => 'required|numeric|min:0|max:100'
            ]);

            $task = $this->taskService->updateTaskProgress(
                $id,
                $request->input('progress'),
                auth()->user()->tenant_id
            );

            return ApiResponse::success($task, 'Task progress updated successfully');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to update task progress', [
                'task_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'tenant_id' => auth()->user()->tenant_id
            ]);

            return ApiResponse::error('Failed to update task progress', 500);
        }
    }
}
