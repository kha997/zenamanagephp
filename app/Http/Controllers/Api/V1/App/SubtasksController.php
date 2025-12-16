<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Services\SubtaskManagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * Subtasks API Controller (V1)
 * 
 * Pure API controller for subtask operations.
 * Only returns JSON responses - no view rendering.
 * 
 * This replaces the unified SubtaskManagementController for API routes.
 */
class SubtasksController extends BaseApiV1Controller
{
    public function __construct(
        private SubtaskManagementService $subtaskService
    ) {}

    /**
     * Get subtasks for a task
     * 
     * @param Request $request
     * @param string $taskId
     * @return JsonResponse
     */
    public function getSubtasksForTask(Request $request, string $taskId): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $subtasks = $this->subtaskService->getSubtasksForTask($taskId, $tenantId);

            return $this->successResponse([
                'data' => $subtasks,
                'meta' => [
                    'total' => $subtasks->count(),
                    'task_id' => $taskId
                ]
            ], 'Subtasks retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'task_id' => $taskId,
                'user_id' => Auth::id(),
            ]);

            return $this->errorResponse(
                'Failed to fetch subtasks: ' . $e->getMessage(),
                500,
                null,
                'SUBTASKS_FETCH_FAILED'
            );
        }
    }

    /**
     * Get subtask by ID
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $subtask = $this->subtaskService->getSubtaskById($id, $tenantId);

            if (!$subtask) {
                return $this->errorResponse('Subtask not found', 404, null, 'SUBTASK_NOT_FOUND');
            }

            return $this->successResponse($subtask, 'Subtask retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'subtask_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return $this->errorResponse(
                'Failed to fetch subtask: ' . $e->getMessage(),
                500,
                null,
                'SUBTASK_FETCH_FAILED'
            );
        }
    }

    /**
     * Create new subtask
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
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

            $tenantId = $this->getTenantId();
            $subtask = $this->subtaskService->createSubtask($validated, $tenantId);

            return $this->successResponse($subtask, 'Subtask created successfully', 201);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors(), 'VALIDATION_FAILED');
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 400, null, 'INVALID_ARGUMENT');
        } catch (\Exception $e) {
            $this->logError($e, [
                'data' => $request->all(),
                'user_id' => Auth::id(),
            ]);

            return $this->errorResponse(
                'Failed to create subtask: ' . $e->getMessage(),
                500,
                null,
                'SUBTASK_CREATE_FAILED'
            );
        }
    }

    /**
     * Update subtask
     * 
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
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

            $tenantId = $this->getTenantId();
            $subtask = $this->subtaskService->updateSubtask($id, $validated, $tenantId);

            return $this->successResponse($subtask, 'Subtask updated successfully');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors(), 'VALIDATION_FAILED');
        } catch (\Exception $e) {
            $this->logError($e, [
                'subtask_id' => $id,
                'data' => $request->all(),
                'user_id' => Auth::id(),
            ]);

            return $this->errorResponse(
                'Failed to update subtask: ' . $e->getMessage(),
                500,
                null,
                'SUBTASK_UPDATE_FAILED'
            );
        }
    }

    /**
     * Delete subtask
     * 
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $deleted = $this->subtaskService->deleteSubtask($id, $tenantId);

            if (!$deleted) {
                return $this->errorResponse('Failed to delete subtask', 500, null, 'SUBTASK_DELETE_FAILED');
            }

            return $this->successResponse(null, 'Subtask deleted successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'subtask_id' => $id,
                'user_id' => Auth::id(),
            ]);

            return $this->errorResponse(
                'Failed to delete subtask: ' . $e->getMessage(),
                500,
                null,
                'SUBTASK_DELETE_FAILED'
            );
        }
    }

    /**
     * Update subtask progress
     * 
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function updateProgress(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'progress' => 'required|numeric|min:0|max:100'
            ]);

            $tenantId = $this->getTenantId();
            $subtask = $this->subtaskService->updateSubtaskProgress($id, $validated['progress'], $tenantId);

            return $this->successResponse($subtask, 'Subtask progress updated successfully');
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', 422, $e->errors(), 'VALIDATION_FAILED');
        } catch (\Exception $e) {
            $this->logError($e, [
                'subtask_id' => $id,
                'progress' => $request->input('progress'),
                'user_id' => Auth::id(),
            ]);

            return $this->errorResponse(
                'Failed to update subtask progress: ' . $e->getMessage(),
                500,
                null,
                'SUBTASK_PROGRESS_UPDATE_FAILED'
            );
        }
    }

    /**
     * Get subtask statistics for a task
     * 
     * @param Request $request
     * @param string $taskId
     * @return JsonResponse
     */
    public function getStatistics(Request $request, string $taskId): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $stats = $this->subtaskService->getSubtaskStatistics($taskId, $tenantId);

            return $this->successResponse([
                'data' => $stats,
                'meta' => [
                    'task_id' => $taskId
                ]
            ], 'Subtask statistics retrieved successfully');
        } catch (\Exception $e) {
            $this->logError($e, [
                'task_id' => $taskId,
                'user_id' => Auth::id(),
            ]);

            return $this->errorResponse(
                'Failed to fetch subtask statistics: ' . $e->getMessage(),
                500,
                null,
                'SUBTASK_STATS_FAILED'
            );
        }
    }
}

