<?php

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TaskController extends Controller
{
    /**
     * Display a listing of tasks
     */
    public function index(Request $request): JsonResponse
    {
        try {
            // Mock data for now - replace with actual database queries
            $tasks = [
                [
                    'id' => 1,
                    'title' => 'Design System Architecture',
                    'description' => 'Create comprehensive system architecture documentation',
                    'project_id' => 1,
                    'assignee_id' => 1,
                    'priority' => 'high',
                    'status' => 'in_progress',
                    'due_date' => '2024-01-15',
                    'created_at' => '2024-01-10T00:00:00Z',
                    'updated_at' => '2024-01-10T00:00:00Z'
                ],
                [
                    'id' => 2,
                    'title' => 'Implement User Authentication',
                    'description' => 'Set up JWT-based authentication system',
                    'project_id' => 1,
                    'assignee_id' => 2,
                    'priority' => 'high',
                    'status' => 'completed',
                    'due_date' => '2024-01-12',
                    'created_at' => '2024-01-08T00:00:00Z',
                    'updated_at' => '2024-01-12T00:00:00Z'
                ]
            ];

            $this->logAudit('tasks.index', 'Listed tasks', [
                'user_id' => Auth::id(),
                'filters' => $request->all()
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $tasks,
                'meta' => [
                    'total' => count($tasks),
                    'per_page' => 15,
                    'current_page' => 1
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('TaskController@index error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve tasks'
            ], 500);
        }
    }

    /**
     * Store a newly created task
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'project_id' => 'required|integer',
                'assignee_id' => 'nullable|integer',
                'priority' => 'required|in:low,medium,high',
                'status' => 'required|in:pending,in_progress,completed,cancelled',
                'due_date' => 'nullable|date'
            ]);

            // Mock task creation - replace with actual database operation
            $task = array_merge($validated, [
                'id' => rand(1000, 9999),
                'created_at' => now()->toISOString(),
                'updated_at' => now()->toISOString()
            ]);

            $this->logAudit('tasks.create', 'Created task', [
                'user_id' => Auth::id(),
                'task_id' => $task['id'],
                'task_title' => $task['title'],
                'project_id' => $task['project_id']
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $task,
                'message' => 'Task created successfully'
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('TaskController@store error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create task'
            ], 500);
        }
    }

    /**
     * Display the specified task
     */
    public function show(string $id): JsonResponse
    {
        try {
            // Mock task retrieval - replace with actual database query
            $task = [
                'id' => (int) $id,
                'title' => 'Design System Architecture',
                'description' => 'Create comprehensive system architecture documentation',
                'project_id' => 1,
                'assignee_id' => 1,
                'priority' => 'high',
                'status' => 'in_progress',
                'due_date' => '2024-01-15',
                'created_at' => '2024-01-10T00:00:00Z',
                'updated_at' => '2024-01-10T00:00:00Z'
            ];

            $this->logAudit('tasks.show', 'Viewed task', [
                'user_id' => Auth::id(),
                'task_id' => $id
            ]);

                return response()->json([
                'status' => 'success',
                'data' => $task
            ]);

        } catch (\Exception $e) {
            Log::error('TaskController@show error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve task'
            ], 500);
        }
    }

    /**
     * Update the specified task
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'project_id' => 'sometimes|integer',
                'assignee_id' => 'nullable|integer',
                'priority' => 'sometimes|in:low,medium,high',
                'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
                'due_date' => 'nullable|date'
            ]);

            // Mock task update - replace with actual database operation
            $task = array_merge([
                'id' => (int) $id,
                'title' => 'Design System Architecture',
                'description' => 'Create comprehensive system architecture documentation',
                'project_id' => 1,
                'assignee_id' => 1,
                'priority' => 'high',
                'status' => 'in_progress',
                'due_date' => '2024-01-15',
                'created_at' => '2024-01-10T00:00:00Z'
            ], $validated, [
                'updated_at' => now()->toISOString()
            ]);

            $this->logAudit('tasks.update', 'Updated task', [
                'user_id' => Auth::id(),
                'task_id' => $id,
                'changes' => $validated
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $task,
                'message' => 'Task updated successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('TaskController@update error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update task'
            ], 500);
        }
    }

    /**
     * Remove the specified task
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            // Mock task deletion - replace with actual database operation
            $this->logAudit('tasks.delete', 'Deleted task', [
                'user_id' => Auth::id(),
                'task_id' => $id
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Task deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('TaskController@destroy error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete task'
            ], 500);
        }
    }
    
    /**
     * Move task to different project or status
     */
    public function move(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'project_id' => 'sometimes|integer',
                'status' => 'sometimes|in:pending,in_progress,completed,cancelled',
                'position' => 'sometimes|integer',
            'reason' => 'nullable|string|max:500'
        ]);

            // Mock task move - replace with actual database operation
            $task = [
                'id' => (int) $id,
                'title' => 'Design System Architecture',
                'description' => 'Create comprehensive system architecture documentation',
                'project_id' => $validated['project_id'] ?? 1,
                'assignee_id' => 1,
                'priority' => 'high',
                'status' => $validated['status'] ?? 'in_progress',
                'due_date' => '2024-01-15',
                'created_at' => '2024-01-10T00:00:00Z',
                'updated_at' => now()->toISOString()
            ];

            $this->logAudit('tasks.move', 'Moved task', [
                'user_id' => Auth::id(),
                'task_id' => $id,
                'changes' => $validated,
                'reason' => $validated['reason'] ?? null
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $task,
                'message' => 'Task moved successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('TaskController@move error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to move task'
            ], 500);
        }
    }

    /**
     * Archive task
     */
    public function archive(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'reason' => 'nullable|string|max:500',
                'permanent' => 'boolean'
            ]);

            // Mock task archive - replace with actual database operation
            $task = [
                'id' => (int) $id,
                'title' => 'Design System Architecture',
                'description' => 'Create comprehensive system architecture documentation',
                'project_id' => 1,
                'assignee_id' => 1,
                'priority' => 'high',
                'status' => 'archived',
                'due_date' => '2024-01-15',
                'created_at' => '2024-01-10T00:00:00Z',
                'updated_at' => now()->toISOString(),
                'archived_at' => now()->toISOString(),
                'archived_by' => Auth::id()
            ];

            $this->logAudit('tasks.archive', 'Archived task', [
                'user_id' => Auth::id(),
                'task_id' => $id,
                'reason' => $validated['reason'] ?? null,
                'permanent' => $validated['permanent'] ?? false
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $task,
                'message' => 'Task archived successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            Log::error('TaskController@archive error: ' . $e->getMessage());

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to archive task'
            ], 500);
        }
    }
    
    /**
     * Log audit trail
     */
    private function logAudit(string $action, string $description, array $context = []): void
    {
        Log::info('AUDIT: ' . $action, array_merge([
            'action' => $action,
            'description' => $description,
            'timestamp' => now()->toISOString(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent()
        ], $context));
    }
}