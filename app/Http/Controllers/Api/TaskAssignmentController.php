<?php

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\Auth;


use App\Http\Controllers\BaseApiController;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $query = TaskAssignment::with(['task', 'user', 'assignedBy']);

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
    public function store(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

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
        $task = Task::find($request->input('task_id'));
        if (!$task) {
            return $this->errorResponse('Task not found', 404);
        }

        // Check if user is already assigned to this task
        $existingAssignment = TaskAssignment::where('task_id', $request->input('task_id'))
            ->where('user_id', $request->input('user_id'))
            ->first();

        if ($existingAssignment) {
            return $this->errorResponse('User is already assigned to this task', 400);
        }

        // Check total split percentage
        $currentTotal = TaskAssignment::where('task_id', $request->input('task_id'))
            ->sum('split_percent');
        
        $newPercentage = $request->input('split_percent', 100);
        
        if ($currentTotal + $newPercentage > 100) {
            return $this->errorResponse(
                'Total split percentage cannot exceed 100%. Current total: ' . $currentTotal . '%',
                400
            );
        }

        try {
            $assignment = TaskAssignment::create([
                'task_id' => $request->input('task_id'),
                'user_id' => $request->input('user_id'),
                'role' => $request->input('role'),
                'split_percent' => $newPercentage,
                'assigned_at' => now(),
                'assigned_by' => $user->id,
                'notes' => $request->input('notes'),
                'status' => 'pending',
            ]);

            return $this->successResponse($assignment->load(['task', 'user', 'assignedBy']), 'Assignment created successfully', 201);

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

        $assignment = TaskAssignment::with(['task', 'user', 'assignedBy'])
            ->find($id);

        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
        }

        return $this->successResponse($assignment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $assignment = TaskAssignment::find($id);

        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
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

        // Check total split percentage if updating split_percent
        if ($request->has('split_percent')) {
            $currentTotal = TaskAssignment::where('task_id', $assignment->task_id)
                ->where('id', '!=', $id)
                ->sum('split_percent');
            
            $newPercentage = $request->input('split_percent');
            
            if ($currentTotal + $newPercentage > 100) {
                return $this->errorResponse(
                    'Total split percentage cannot exceed 100%. Current total: ' . $currentTotal . '%',
                    400
                );
            }
        }

        try {
            $assignment->update($request->only([
                'role', 'split_percent', 'notes', 'status'
            ]));

            return $this->successResponse($assignment->load(['task', 'user', 'assignedBy']), 'Assignment updated successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $assignment = TaskAssignment::find($id);

        if (!$assignment) {
            return $this->errorResponse('Assignment not found', 404);
        }

        try {
            $assignment->delete();

            return $this->successResponse(null, 'Assignment deleted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get assignments for a specific task
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

        $assignments = TaskAssignment::with(['user', 'assignedBy'])
            ->where('task_id', $taskId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($assignments);
    }

    /**
     * Get assignments for a specific user
     */
    public function getUserAssignments(string $userId): JsonResponse
    {
        $user = Auth::user();
        
        if (!$user) {
            return $this->errorResponse('Unauthorized', 401);
        }

        $assignments = TaskAssignment::with(['task.project', 'assignedBy'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        return $this->successResponse($assignments);
    }

    /**
     * Accept assignment
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

            return $this->successResponse($assignment->load(['task', 'user', 'assignedBy']), 'Assignment accepted successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to accept assignment: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Reject assignment
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

            return $this->successResponse($assignment->load(['task', 'user', 'assignedBy']), 'Assignment rejected successfully');

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to reject assignment: ' . $e->getMessage(), 500);
        }
    }
}