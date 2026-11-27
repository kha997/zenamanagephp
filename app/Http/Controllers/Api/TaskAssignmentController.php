<?php

namespace App\Http\Controllers\Api;

use App\Models\Task;
use App\Models\TaskAssignment;
use App\Services\TaskAssignmentShareException;
use App\Services\TaskAssignmentShares;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class TaskAssignmentController extends BaseApiController
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $query = TaskAssignment::with(['task', 'user']);

        // Apply filters
        if ($request->has('task_id')) {
            $query->where('task_id', $request->input('task_id'));
        }

        if ($request->has('user_id')) {
            $query->where('user_id', $request->input('user_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->has('role')) {
            $query->where('role', $request->input('role'));
        }

        // Pagination
        $perPage = $request->input('per_page', 15);
        $assignments = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return $this->successResponse($assignments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, string $taskId): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $request->merge(['task_id' => $taskId]);

        $validator = Validator::make($request->all(), [
            'task_id' => 'required|exists:tasks,id',
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:assignee,reviewer,observer',
            'split_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        // Check if task exists
        $task = Task::find($taskId);
        if (!$task) {
            return $this->errorResponse('Task not found', 404);
        }

        // UAT-RECOVERY-04b (O-13): assignment is an operation-specific, owning-project-PM action.
        if (!$user->can('assign', $task)) {
            return $this->errorResponse('Forbidden', 403);
        }

        // The assignee must belong to the task's tenant (no cross-tenant assignment; no implicit user/membership creation).
        if (!\App\Models\User::query()->whereKey($request->input('user_id'))->where('tenant_id', $task->tenant_id)->exists()) {
            return $this->errorResponse('User not found in this tenant', 422);
        }

        $input = $request->input('split_percent');
        $newPercentage = TaskAssignmentShares::normalise($input === null ? TaskAssignmentShares::DEFAULT_PERCENT : (float) $input);

        try {
            // OWN-2026-024 SPLIT-PERCENT: duplicate check, 100 % check and insert run under one task-row lock, so concurrent
            // requests serialise; the response is re-read from the database (what is returned is what was stored).
            $assignmentId = TaskAssignmentShares::withTaskLock((string) $task->id, function () use ($request, $task, $taskId, $newPercentage, $user): string {
                if (TaskAssignmentShares::isAssigned($taskId, (string) $request->input('user_id'))) {
                    throw TaskAssignmentShareException::duplicate();
                }
                TaskAssignmentShares::assertFits($taskId, $newPercentage);

                return (string) TaskAssignment::create([
                    'task_id' => $request->input('task_id'),
                    'tenant_id' => (string) $task->tenant_id,
                    'user_id' => $request->input('user_id'),
                    'role' => $request->input('role'),
                    'split_percent' => $newPercentage,
                    'assigned_at' => now(),
                    'assigned_by' => (string) $user->id,
                    'notes' => $request->input('notes'),
                    'status' => 'pending',
                ])->getKey();
            });

            $assignment = TaskAssignment::query()->withoutGlobalScopes()->with(['task', 'user'])->findOrFail($assignmentId);

            return $this->successResponse($assignment, 'Assignment created successfully', 201);
        } catch (TaskAssignmentShareException $e) {
            return $this->errorResponse($e->getMessage(), $e->reason === TaskAssignmentShareException::REASON_TASK_NOT_FOUND ? 404 : 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $assignment = TaskAssignment::with(['task', 'user'])
            ->find($id);

        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
        }

        return $this->successResponse($assignment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $assignmentId): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $assignment = TaskAssignment::find($assignmentId);

        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
        }

        // UAT-RECOVERY-04b (O-13): reassignment / unassignment use the same boundary as assignment.
        $assignedTask = Task::query()->find($assignment->task_id);
        if (!$assignedTask || !$user->can('assign', $assignedTask)) {
            return $this->errorResponse('Forbidden', 403);
        }

        $validator = Validator::make($request->all(), [
            'role' => 'sometimes|required|in:assignee,reviewer,observer',
            'split_percent' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'status' => 'sometimes|required|in:pending,accepted,rejected,completed',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', 422, $validator->errors());
        }

        $changes = $request->only(['role', 'split_percent', 'notes', 'status']);
        // An explicit null share is not a valid recorded value; it would silently erase the share (unchanged contract: only a
        // supplied number changes it).
        if (array_key_exists('split_percent', $changes) && $changes['split_percent'] === null) {
            unset($changes['split_percent']);
        }
        if (array_key_exists('split_percent', $changes)) {
            $changes['split_percent'] = TaskAssignmentShares::normalise((float) $changes['split_percent']);
        }

        try {
            // OWN-2026-024 SPLIT-PERCENT: the 100 % check (excluding this assignment) and the update run under the task lock.
            TaskAssignmentShares::withTaskLock((string) $assignment->task_id, function () use ($assignment, $changes): void {
                if (array_key_exists('split_percent', $changes)) {
                    TaskAssignmentShares::assertFits((string) $assignment->task_id, (float) $changes['split_percent'], (string) $assignment->getKey());
                }
                $assignment->update($changes);
            });

            $fresh = TaskAssignment::query()->withoutGlobalScopes()->with(['task', 'user'])->findOrFail($assignment->getKey());

            return $this->successResponse($fresh, 'Assignment updated successfully');
        } catch (TaskAssignmentShareException $e) {
            return $this->errorResponse($e->getMessage(), $e->reason === TaskAssignmentShareException::REASON_TASK_NOT_FOUND ? 404 : 400);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $assignmentId): JsonResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $assignment = TaskAssignment::find($assignmentId);

        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
        }

        // UAT-RECOVERY-04b (O-13): reassignment / unassignment use the same boundary as assignment.
        $assignedTask = Task::query()->find($assignment->task_id);
        if (!$assignedTask || !$user->can('assign', $assignedTask)) {
            return $this->errorResponse('Forbidden', 403);
        }

        try {
            $assignment->delete();

            return $this->successResponse(null, 'Assignment deleted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get assignments for a specific task.
     */
    public function getTaskAssignments(string $taskId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $task = Task::find($taskId);
        if (!$task) {
            return $this->errorResponse('Task not found', 404);
        }

        $assignments = TaskAssignment::with(['user'])
            ->where('task_id', $taskId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($assignments);
    }

    /**
     * Get assignments for a specific user.
     */
    public function getUserAssignments(string $userId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $assignments = TaskAssignment::with(['task.project'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($assignments);
    }

    /**
     * Get minimal assignment statistics for a specific user.
     */
    public function getUserStats(string $userId): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $query = TaskAssignment::query()->where('user_id', $userId);

        $tenantId = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
        if (is_string($tenantId) && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }

        return $this->successResponse([
            'total_assignments' => $query->count(),
        ]);
    }

    /**
     * Accept assignment.
     */
    public function acceptAssignment(string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $assignment = TaskAssignment::find($id);

        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
        }

        // Check if user is the assignee
        if ($assignment->user_id !== $user->id) {
            return $this->errorResponse('You can only accept your own assignments', 403);
        }

        try {
            $assignment->update(['status' => 'accepted']);

            return $this->successResponse($assignment->load(['task', 'user']), 'Assignment accepted successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to accept assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject assignment.
     */
    public function rejectAssignment(string $id): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $assignment = TaskAssignment::find($id);

        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
        }

        // Check if user is the assignee
        if ($assignment->user_id !== $user->id) {
            return $this->errorResponse('You can only reject your own assignments', 403);
        }

        try {
            $assignment->update(['status' => 'rejected']);

            return $this->successResponse($assignment->load(['task', 'user']), 'Assignment rejected successfully');
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reject assignment: ' . $e->getMessage(), 500);
        }
    }
}
