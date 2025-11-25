<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Services\TaskManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Tasks API Controller (V1)
 * 
 * Pure API controller for task operations.
 * Only returns JSON responses - no view rendering.
 * 
 * This replaces the unified TaskManagementController for API routes.
 */
class TasksController extends BaseApiV1Controller
{
    public function __construct(
        private TaskManagementService $taskService
    ) {}

    /**
     * Get tasks with filtering and pagination
     * 
     * Supports both offset pagination (default) and cursor pagination (use cursor parameter)
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Check authorization via policy (viewAny permission)
            $this->authorize('viewAny', \App\Models\Task::class);
            
            $filters = $request->only([
                'project_id',
                'status',
                'priority',
                'assignee_id',
                'search',
                'start_date_from',
                'start_date_to',
                'end_date_from',
                'end_date_to'
            ]);

            // Clean up empty string values but keep '0' and false
            $filters = array_filter($filters, function($value, $key) {
                if ($value === '' || $value === null) {
                    return false;
                }
                return true;
            }, ARRAY_FILTER_USE_BOTH);

            $sortBy = $request->get('sort_by', 'updated_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $tenantId = $this->getTenantId();

            // Check if cursor pagination is requested
            $cursor = $request->get('cursor');
            if ($cursor) {
                $limit = (int) $request->get('limit', 15);
                
                $result = $this->taskService->getTasksCursor(
                    $filters,
                    $limit,
                    $cursor,
                    $sortBy,
                    $sortDirection,
                    $tenantId
                );
                
                return $this->successResponse([
                    'data' => $result['data'],
                    'pagination' => [
                        'next_cursor' => $result['next_cursor'],
                        'has_more' => $result['has_more'],
                    ]
                ], 'Tasks retrieved successfully');
            }

            // Default: offset pagination
            $perPage = (int) $request->get('per_page', 15);

            $tasks = $this->taskService->getTasks($filters, $perPage, $sortBy, $sortDirection, $tenantId);

            return $this->paginatedResponse(
                $tasks->items(),
                [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
                    'from' => $tasks->firstItem(),
                    'to' => $tasks->lastItem()
                ],
                'Tasks retrieved successfully',
                [
                    'first' => $tasks->url(1),
                    'last' => $tasks->url($tasks->lastPage()),
                    'prev' => $tasks->previousPageUrl(),
                    'next' => $tasks->nextPageUrl(),
                ]
            );
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'index']);
            return $this->errorResponse('Failed to retrieve tasks', 500);
        }
    }

    /**
     * Get task by ID
     * 
     * @param string $id Task ID
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $task = $this->taskService->getTaskById($id, $tenantId);

            if (!$task) {
                return $this->errorResponse('Task not found', 404, null, 'TASK_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('view', $task);

            return $this->successResponse($task, 'Task retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'show', 'task_id' => $id]);
            return $this->errorResponse('Failed to retrieve task', 500);
        }
    }

    /**
     * Create new task
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Check authorization via policy (create permission)
            $this->authorize('create', \App\Models\Task::class);
            
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

            $tenantId = $this->getTenantId();
            $task = $this->taskService->createTask($request->all(), $tenantId);

            return $this->successResponse($task, 'Task created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'store']);
            return $this->errorResponse('Failed to create task', 500);
        }
    }

    /**
     * Update task
     * 
     * @param Request $request
     * @param string $id Task ID
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $task = $this->taskService->getTaskById($id, $tenantId);

            if (!$task) {
                return $this->errorResponse('Task not found', 404, null, 'TASK_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('update', $task);
            
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|string|in:' . implode(',', \App\Enums\TaskStatus::values()),
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

            $tenantId = $this->getTenantId();
            $task = $this->taskService->updateTask($id, $request->all(), $tenantId);

            return $this->successResponse($task, 'Task updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'update', 'task_id' => $id]);
            return $this->errorResponse('Failed to update task', 500);
        }
    }

    /**
     * Delete task
     * 
     * @param string $id Task ID
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $deleted = $this->taskService->deleteTask($id, $tenantId);

            if (!$deleted) {
                return $this->errorResponse('Task not found', 404);
            }

            return $this->successResponse(null, 'Task deleted successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'destroy', 'task_id' => $id]);
            return $this->errorResponse('Failed to delete task', 500);
        }
    }

    /**
     * Get tasks for project
     * 
     * @param Request $request
     * @param string $projectId Project ID
     * @return JsonResponse
     */
    public function getTasksForProject(Request $request, string $projectId): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $perPage = (int) $request->get('per_page', 15);
            
            $filters = [
                'status' => $request->get('status'),
                'search' => $request->get('search'),
                'assigned_to' => $request->get('assigned_to'),
            ];
            
            $tasks = $this->taskService->getTasksForProject(
                $projectId,
                $tenantId,
                $filters,
                $perPage
            );

            return $this->paginatedResponse(
                $tasks->items(),
                [
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage(),
                    'per_page' => $tasks->perPage(),
                    'total' => $tasks->total(),
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
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getTasksForProject', 'project_id' => $projectId]);
            return $this->errorResponse('Failed to retrieve project tasks', 500);
        }
    }

    /**
     * Create task for project
     * 
     * @param Request $request
     * @param string $projectId Project ID
     * @return JsonResponse
     */
    public function createTask(Request $request, string $projectId): JsonResponse
    {
        try {
            $request->validate([
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

            $data = $request->all();
            $data['project_id'] = $projectId;

            $tenantId = $this->getTenantId();
            $task = $this->taskService->createTask($data, $tenantId);

            return $this->successResponse($task, 'Task created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'createTask', 'project_id' => $projectId]);
            return $this->errorResponse('Failed to create task', 500);
        }
    }

    /**
     * Assign task to user
     * 
     * @param Request $request
     * @param string $id Task ID
     * @return JsonResponse
     */
    public function assign(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'assignee_id' => 'required|string|ulid|exists:users,id'
            ]);

            $tenantId = $this->getTenantId();
            $task = $this->taskService->updateTask($id, ['assignee_id' => $request->input('assignee_id')], $tenantId);

            return $this->successResponse($task, 'Task assigned successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'assign', 'task_id' => $id]);
            return $this->errorResponse('Failed to assign task', 500);
        }
    }

    /**
     * Unassign task
     * 
     * @param Request $request
     * @param string $id Task ID
     * @return JsonResponse
     */
    public function unassign(Request $request, string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $task = $this->taskService->updateTask($id, ['assignee_id' => null], $tenantId);

            return $this->successResponse($task, 'Task unassigned successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'unassign', 'task_id' => $id]);
            return $this->errorResponse('Failed to unassign task', 500);
        }
    }

    /**
     * Update task progress
     * 
     * @param Request $request
     * @param string $id Task ID
     * @return JsonResponse
     */
    public function updateProgress(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'progress' => 'required|numeric|min:0|max:100'
            ]);

            $tenantId = $this->getTenantId();
            $task = $this->taskService->getTaskById($id, $tenantId);
            
            if (!$task) {
                return $this->errorResponse('Task not found', 404, null, 'TASK_NOT_FOUND');
            }

            // Check authorization via policy
            $this->authorize('update', $task);
            
            $task = $this->taskService->updateTaskProgress(
                $id,
                $request->input('progress'),
                $tenantId
            );

            return $this->successResponse($task, 'Task progress updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'updateProgress', 'task_id' => $id]);
            return $this->errorResponse('Failed to update task progress', 500);
        }
    }

    /**
     * Move task (change status/position)
     * 
     * @param Request $request
     * @param string $id Task ID
     * @return JsonResponse
     */
    public function move(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'sometimes|string|in:' . implode(',', \App\Enums\TaskStatus::values()),
                'position' => 'sometimes|integer|min:0',
                'column_id' => 'sometimes|string|ulid'
            ]);

            $tenantId = $this->getTenantId();
            $updateData = [];
            
            if ($request->has('status')) {
                $updateData['status'] = $request->input('status');
            }
            if ($request->has('position')) {
                $updateData['position'] = $request->input('position');
            }
            
            $task = $this->taskService->updateTask($id, $updateData, $tenantId);

            return $this->successResponse($task, 'Task moved successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'move', 'task_id' => $id]);
            return $this->errorResponse('Failed to move task', 500);
        }
    }

    /**
     * Get task documents
     * 
     * @param string $id Task ID
     * @return JsonResponse
     */
    public function documents(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Get documents for this task
            $documents = \App\Models\Document::where('task_id', $id)
                ->where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->get();

            return $this->successResponse($documents, 'Task documents retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'documents', 'task_id' => $id]);
            return $this->errorResponse('Failed to retrieve task documents', 500);
        }
    }

    /**
     * Get task history
     * 
     * @param string $id Task ID
     * @return JsonResponse
     */
    public function history(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Get task audit logs or activity history
            // For now, return empty array - can be implemented later with audit log service
            $history = [];

            return $this->successResponse($history, 'Task history retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'history', 'task_id' => $id]);
            return $this->errorResponse('Failed to retrieve task history', 500);
        }
    }

    /**
     * Get task KPIs
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getKpis(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $period = $request->get('period', 'week');
            
            $kpis = $this->taskService->getTaskKpis($tenantId, $period);

            return $this->successResponse($kpis, 'Task KPIs retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getKpis']);
            return $this->errorResponse('Failed to load task KPIs', 500);
        }
    }

    /**
     * Get task alerts
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getAlerts(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            
            // Use the same logic from Unified controller
            $alerts = [];
            
            $today = now()->startOfDay();
            $overdueTasks = \App\Models\Task::whereHas('project', function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
                ->whereNotNull('end_date')
                ->whereDate('end_date', '<', $today)
                ->whereNotIn('status', ['done', 'completed', 'canceled', 'cancelled'])
                ->with('project')
                ->get();
                
            foreach ($overdueTasks as $task) {
                $endDate = $task->end_date ? $task->end_date->toISOString() : now()->toISOString();
                
                $alerts[] = [
                    'id' => 'overdue-' . $task->id,
                    'title' => 'Task Overdue',
                    'message' => "Task '{$task->name}' in project '{$task->project->name}' is overdue",
                    'severity' => 'high',
                    'status' => 'unread',
                    'type' => 'overdue',
                    'source' => 'task',
                    'createdAt' => $endDate,
                    'metadata' => ['task_id' => $task->id, 'project_id' => $task->project_id]
                ];
            }

            $nearingDeadline = \App\Models\Task::whereHas('project', function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
                ->whereNotNull('end_date')
                ->where('end_date', '>=', now())
                ->where('end_date', '<=', now()->addDay())
                ->whereNotIn('status', ['done', 'completed', 'canceled', 'cancelled'])
                ->with('project')
                ->get();
                
            foreach ($nearingDeadline as $task) {
                $alerts[] = [
                    'id' => 'deadline-' . $task->id,
                    'title' => 'Task Deadline Approaching',
                    'message' => "Task '{$task->name}' in project '{$task->project->name}' is due soon",
                    'severity' => 'medium',
                    'status' => 'unread',
                    'type' => 'deadline',
                    'source' => 'task',
                    'createdAt' => now()->toISOString(),
                    'metadata' => ['task_id' => $task->id, 'project_id' => $task->project_id]
                ];
            }

            return $this->successResponse($alerts, 'Task alerts retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getAlerts']);
            return $this->errorResponse('Failed to load task alerts', 500);
        }
    }

    /**
     * Get task activity
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getActivity(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $limit = (int) $request->get('limit', 10);

            // Use the same logic from Unified controller
            $activity = [];
            
            $recentTasks = \App\Models\Task::whereHas('project', function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
                ->orderBy('updated_at', 'desc')
                ->limit((int) ceil($limit / 2))
                ->with(['project', 'assignee'])
                ->get();
                
            foreach ($recentTasks as $task) {
                $activity[] = [
                    'id' => 'task-' . $task->id,
                    'type' => 'task',
                    'action' => 'updated',
                    'description' => "Task '{$task->name}' in '{$task->project->name}' was updated",
                    'timestamp' => $task->updated_at->toISOString(),
                    'user' => [
                        'id' => $task->assignee->id ?? auth()->id(),
                        'name' => $task->assignee->name ?? auth()->user()->name
                    ]
                ];
            }

            $recentComments = \App\Models\TaskComment::whereHas('task', function($q) use ($tenantId) {
                $q->whereHas('project', function($q2) use ($tenantId) {
                    $q2->where('tenant_id', $tenantId);
                });
            })
                ->orderBy('created_at', 'desc')
                ->limit((int) ceil($limit / 2))
                ->with(['task.project', 'user'])
                ->get();
                
            foreach ($recentComments as $comment) {
                $projectName = $comment->task->project->name ?? 'Unknown Project';
                $taskName = $comment->task->name ?? 'Unknown Task';
                $activity[] = [
                    'id' => 'comment-' . $comment->id,
                    'type' => 'comment',
                    'action' => 'commented',
                    'description' => "Commented on task '{$taskName}' in '{$projectName}'",
                    'timestamp' => $comment->created_at->toISOString(),
                    'user' => [
                        'id' => $comment->user->id ?? auth()->id(),
                        'name' => $comment->user->name ?? auth()->user()->name
                    ]
                ];
            }

            usort($activity, function($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
            $activity = array_slice($activity, 0, $limit);

            return $this->successResponse($activity, 'Task activity retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getActivity']);
            return $this->errorResponse('Failed to load task activity', 500);
        }
    }

    /**
     * Get task statistics
     * 
     * @return JsonResponse
     */
    public function getTaskStatistics(): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $stats = $this->taskService->getTaskStatistics($tenantId);

            return $this->successResponse($stats, 'Task statistics retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'getTaskStatistics']);
            return $this->errorResponse('Failed to retrieve task statistics', 500);
        }
    }

    /**
     * Bulk delete tasks
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkDeleteTasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'string|ulid',
            ]);

            $tenantId = $this->getTenantId();
            $ids = $request->input('ids');
            $result = $this->taskService->bulkDeleteTasks($ids, $tenantId);

            return $this->successResponse($result, $result['message'] ?? 'Tasks deleted successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'bulkDeleteTasks']);
            return $this->errorResponse('Failed to bulk delete tasks', 500);
        }
    }

    /**
     * Bulk update task status
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'string|ulid',
                'status' => 'required|string|in:' . implode(',', \App\Models\Task::VALID_STATUSES),
            ]);

            $tenantId = $this->getTenantId();
            $ids = $request->input('ids');
            $status = $request->input('status');
            $result = $this->taskService->bulkUpdateStatus($ids, $status, $tenantId);

            return $this->successResponse($result, $result['message'] ?? 'Task status updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'bulkUpdateStatus']);
            return $this->errorResponse('Failed to bulk update task status', 500);
        }
    }

    /**
     * Bulk assign tasks
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkAssignTasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'string|ulid',
                'assignee_id' => 'required|string|ulid',
            ]);

            $tenantId = $this->getTenantId();
            $ids = $request->input('ids');
            $assigneeId = $request->input('assignee_id');
            $result = $this->taskService->bulkAssignTasks($ids, $assigneeId, $tenantId);

            return $this->successResponse($result, $result['message'] ?? 'Tasks assigned successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            $this->logError($e, ['action' => 'bulkAssignTasks']);
            return $this->errorResponse('Failed to bulk assign tasks', 500);
        }
    }
}

