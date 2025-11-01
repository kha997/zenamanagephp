<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TaskStoreRequest;
use App\Http\Requests\TaskUpdateRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use App\Services\TaskService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TasksController extends Controller
{
    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    /**
     * Display a listing of tasks
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $filters = $request->only(['status', 'priority', 'project_id', 'assignee_id', 'search']);
            $filters['tenant_id'] = $user->tenant_id;
            
            $tasks = $this->taskService->getTasksList($filters, $user->id, $user->tenant_id);

            return response()->json([
                'success' => true,
                'data' => $tasks,
                'meta' => [
                    'total' => $tasks->total(),
                    'per_page' => $tasks->perPage(),
                    'current_page' => $tasks->currentPage(),
                    'last_page' => $tasks->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created task
     */
    public function store(TaskStoreRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $task = $this->taskService->createTask($request->validated(), $user->id, $user->tenant_id);

            return response()->json([
                'success' => true,
                'data' => new TaskResource($task),
                'message' => 'Task created successfully'
            ], 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Display the specified task
     */
    public function show(Task $task): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($task->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Task belongs to different tenant', 403);
            }

            $task->load(['project', 'assignee', 'creator', 'dependencies']);

            return response()->json([
                'success' => true,
                'data' => new TaskResource($task)
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified task
     */
    public function update(TaskUpdateRequest $request, Task $task): JsonResponse
    {
        try {
            $user = Auth::user();
            $updatedTask = $this->taskService->updateTask($task->id, $request->validated(), $user->id, $user->tenant_id);

            return response()->json([
                'success' => true,
                'data' => new TaskResource($updatedTask),
                'message' => 'Task updated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified task
     */
    public function destroy(Task $task): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->taskService->deleteTask($task->id, $user->id, $user->tenant_id);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Task deleted successfully'
                ]);
            } else {
                return $this->errorResponse('Failed to delete task', 500);
            }
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Assign task to user
     */
    public function assign(Request $request, Task $task): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'assignee_id' => 'required|string|exists:users,id'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $user = Auth::user();
            $result = $this->taskService->assignTask($task->id, $request->assignee_id, $user->id, $user->tenant_id);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Task assigned successfully'
                ]);
            } else {
                return $this->errorResponse('Failed to assign task', 500);
            }
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Unassign task from user
     */
    public function unassign(Task $task): JsonResponse
    {
        try {
            $user = Auth::user();
            $result = $this->taskService->unassignTask($task->id, $user->id, $user->tenant_id);

            if ($result) {
                return response()->json([
                    'success' => true,
                    'message' => 'Task unassigned successfully'
                ]);
            } else {
                return $this->errorResponse('Failed to unassign task', 500);
            }
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update task progress
     */
    public function updateProgress(Request $request, Task $task): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'progress_percent' => 'required|numeric|min:0|max:100',
                'status' => 'nullable|in:pending,in_progress,completed,cancelled'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $user = Auth::user();
            $updatedTask = $this->taskService->updateTaskProgress($task->id, $request->all(), $user->id, $user->tenant_id);

            return response()->json([
                'success' => true,
                'data' => $updatedTask,
                'message' => 'Task progress updated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get task documents
     */
    public function documents(Task $task): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($task->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Task belongs to different tenant', 403);
            }

            $documents = $task->documents()->latest()->get();

            return response()->json([
                'success' => true,
                'data' => $documents
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Get task history
     */
    public function history(Task $task): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($task->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Task belongs to different tenant', 403);
            }

            // TODO: Implement task history/audit log
            $history = [];

            return response()->json([
                'success' => true,
                'data' => $history
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Standardized error response with error envelope
     */
    private function errorResponse(string $message, int $status = 500, $errors = null): JsonResponse
    {
        $errorId = uniqid('err_', true);
        
        $response = [
            'success' => false,
            'error' => [
                'id' => $errorId,
                'message' => $message,
                'status' => $status,
                'timestamp' => now()->toISOString()
            ]
        ];

        if ($errors) {
            $response['error']['details'] = $errors;
        }

        return response()->json($response, $status);
    }
}
