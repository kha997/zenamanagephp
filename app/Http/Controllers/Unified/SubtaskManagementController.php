<?php declare(strict_types=1);

namespace App\Http\Controllers\Unified;

use App\Http\Controllers\Controller;
use App\Services\SubtaskManagementService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * Unified Subtask Management Controller
 * 
 * Handles all subtask-related API operations including CRUD,
 * bulk actions, and subtask management features.
 */
class SubtaskManagementController extends Controller
{
    public function __construct(
        private SubtaskManagementService $subtaskService
    ) {}

    /**
     * Get subtasks for a task
     */
    public function getSubtasksForTask(Request $request, string $taskId): JsonResponse
    {
        try {
            $subtasks = $this->subtaskService->getSubtasksForTask($taskId, auth()->user()->tenant_id);

            return ApiResponse::success([
                'data' => $subtasks,
                'meta' => [
                    'total' => $subtasks->count(),
                    'task_id' => $taskId
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch subtasks for task', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return ApiResponse::error('Failed to fetch subtasks', 500);
        }
    }

    /**
     * Get subtask by ID
     */
    public function getSubtask(Request $request, string $id): JsonResponse
    {
        try {
            $subtask = $this->subtaskService->getSubtaskById($id, auth()->user()->tenant_id);

            if (!$subtask) {
                return ApiResponse::error('Subtask not found', 404);
            }

            return ApiResponse::success([
                'data' => $subtask
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch subtask', [
                'subtask_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return ApiResponse::error('Failed to fetch subtask', 500);
        }
    }

    /**
     * Create new subtask
     */
    public function createSubtask(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'task_id' => 'required|string|ulid',
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'nullable|string|in:pending,in_progress,completed,canceled',
                'priority' => 'nullable|string|in:low,normal,high,urgent',
                'assignee_id' => 'nullable|string|ulid',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'estimated_hours' => 'nullable|numeric|min:0',
                'sort_order' => 'nullable|integer|min:0'
            ]);

            $subtask = $this->subtaskService->createSubtask($request->all(), auth()->user()->tenant_id);

            return ApiResponse::success([
                'data' => $subtask
            ], 'Subtask created successfully', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\InvalidArgumentException $e) {
            return ApiResponse::error($e->getMessage(), 400);
        } catch (\Exception $e) {
            Log::error('Failed to create subtask', [
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return ApiResponse::error('Failed to create subtask', 500);
        }
    }

    /**
     * Update subtask
     */
    public function updateSubtask(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'status' => 'sometimes|string|in:pending,in_progress,completed,canceled',
                'priority' => 'sometimes|string|in:low,normal,high,urgent',
                'assignee_id' => 'nullable|string|ulid',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'estimated_hours' => 'nullable|numeric|min:0',
                'actual_hours' => 'nullable|numeric|min:0',
                'progress_percent' => 'nullable|numeric|min:0|max:100',
                'sort_order' => 'nullable|integer|min:0'
            ]);

            $subtask = $this->subtaskService->updateSubtask($id, $request->all(), auth()->user()->tenant_id);

            return ApiResponse::success([
                'data' => $subtask
            ], 'Subtask updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to update subtask', [
                'subtask_id' => $id,
                'data' => $request->all(),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return ApiResponse::error('Failed to update subtask', 500);
        }
    }

    /**
     * Delete subtask
     */
    public function deleteSubtask(Request $request, string $id): JsonResponse
    {
        try {
            $deleted = $this->subtaskService->deleteSubtask($id, auth()->user()->tenant_id);

            if (!$deleted) {
                return ApiResponse::error('Failed to delete subtask', 500);
            }

            return ApiResponse::success([], 'Subtask deleted successfully');
        } catch (\Exception $e) {
            Log::error('Failed to delete subtask', [
                'subtask_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return ApiResponse::error('Failed to delete subtask', 500);
        }
    }

    /**
     * Update subtask progress
     */
    public function updateSubtaskProgress(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'progress' => 'required|numeric|min:0|max:100'
            ]);

            $subtask = $this->subtaskService->updateSubtaskProgress($id, $request->input('progress'), auth()->user()->tenant_id);

            return ApiResponse::success([
                'data' => $subtask
            ], 'Subtask progress updated successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to update subtask progress', [
                'subtask_id' => $id,
                'progress' => $request->input('progress'),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return ApiResponse::error('Failed to update subtask progress', 500);
        }
    }

    /**
     * Get subtask statistics for a task
     */
    public function getSubtaskStatistics(Request $request, string $taskId): JsonResponse
    {
        try {
            $stats = $this->subtaskService->getSubtaskStatistics($taskId, auth()->user()->tenant_id);

            return ApiResponse::success([
                'data' => $stats,
                'meta' => [
                    'task_id' => $taskId
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch subtask statistics', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return ApiResponse::error('Failed to fetch subtask statistics', 500);
        }
    }

    /**
     * Bulk delete subtasks
     */
    public function bulkDeleteSubtasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'subtask_ids' => 'required|array',
                'subtask_ids.*' => 'required|string|ulid'
            ]);

            $result = $this->subtaskService->bulkDeleteSubtasks($request->input('subtask_ids'), auth()->user()->tenant_id);

            return ApiResponse::success($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to bulk delete subtasks', [
                'subtask_ids' => $request->input('subtask_ids'),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return ApiResponse::error('Failed to bulk delete subtasks', 500);
        }
    }

    /**
     * Bulk update subtask status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'subtask_ids' => 'required|array',
                'subtask_ids.*' => 'required|string|ulid',
                'status' => 'required|string|in:pending,in_progress,completed,canceled'
            ]);

            $result = $this->subtaskService->bulkUpdateStatus(
                $request->input('subtask_ids'),
                $request->input('status'),
                auth()->user()->tenant_id
            );

            return ApiResponse::success($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to bulk update subtask status', [
                'subtask_ids' => $request->input('subtask_ids'),
                'status' => $request->input('status'),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return ApiResponse::error('Failed to bulk update subtask status', 500);
        }
    }

    /**
     * Bulk assign subtasks
     */
    public function bulkAssignSubtasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'subtask_ids' => 'required|array',
                'subtask_ids.*' => 'required|string|ulid',
                'assignee_id' => 'required|string|ulid'
            ]);

            $result = $this->subtaskService->bulkAssignSubtasks(
                $request->input('subtask_ids'),
                $request->input('assignee_id'),
                auth()->user()->tenant_id
            );

            return ApiResponse::success($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to bulk assign subtasks', [
                'subtask_ids' => $request->input('subtask_ids'),
                'assignee_id' => $request->input('assignee_id'),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return ApiResponse::error('Failed to bulk assign subtasks', 500);
        }
    }

    /**
     * Reorder subtasks
     */
    public function reorderSubtasks(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'subtask_ids' => 'required|array',
                'subtask_ids.*' => 'required|string|ulid'
            ]);

            $result = $this->subtaskService->reorderSubtasks($request->input('subtask_ids'), auth()->user()->tenant_id);

            return ApiResponse::success($result);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ApiResponse::error('Validation failed', 422, $e->errors());
        } catch (\Exception $e) {
            Log::error('Failed to reorder subtasks', [
                'subtask_ids' => $request->input('subtask_ids'),
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return ApiResponse::error('Failed to reorder subtasks', 500);
        }
    }
}