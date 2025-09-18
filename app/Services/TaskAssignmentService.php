<?php declare(strict_types=1);

namespace App\Services;

use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\TaskAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Task Assignment Service - Business logic for task assignment management
 */
class TaskAssignmentService
{
    /**
     * Get assignments for a task
     */
    public function getAssignmentsForTask(string $taskId): Collection
    {
        return TaskAssignment::where('task_id', $taskId)
            ->with('user')
            ->get();
    }

    /**
     * Get assignments for a user
     */
    public function getAssignmentsForUser(string $userId, array $filters = []): Collection
    {
        $query = TaskAssignment::where('user_id', $userId)
            ->with(['task.project', 'task.component']);

        if (!empty($filters['status'])) {
            $query->whereHas('task', function ($q) use ($filters) {
                $q->where('status', $filters['status']);
            });
        }

        if (!empty($filters['project_id'])) {
            $query->whereHas('task', function ($q) use ($filters) {
                $q->where('project_id', $filters['project_id']);
            });
        }

        return $query->get();
    }

    /**
     * Create a new task assignment
     */
    public function createAssignment(array $data): TaskAssignment
    {
        try {
            DB::beginTransaction();

            // Validate task exists
            $task = Task::find($data['task_id']);
            if (!$task) {
                throw new \Exception('Task not found');
            }

            // Validate user exists
            $user = User::find($data['user_id']);
            if (!$user) {
                throw new \Exception('User not found');
            }

            // Check if assignment already exists
            $existingAssignment = TaskAssignment::where('task_id', $data['task_id'])
                ->where('user_id', $data['user_id'])
                ->first();

            if ($existingAssignment) {
                throw new \Exception('Assignment already exists');
            }

            $assignment = TaskAssignment::create([
                'task_id' => $data['task_id'],
                'user_id' => $data['user_id'],
                'split_percent' => $data['split_percent'] ?? 100.0,
                'role' => $data['role'] ?? 'assignee',
            ]);

            DB::commit();

            return $assignment->load(['user', 'task']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task assignment creation failed', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing assignment
     */
    public function updateAssignment(string $assignmentId, array $data): ?TaskAssignment
    {
        try {
            DB::beginTransaction();

            $assignment = TaskAssignment::find($assignmentId);
            if (!$assignment) {
                return null;
            }

            $assignment->update([
                'split_percent' => $data['split_percent'] ?? $assignment->split_percent,
                'role' => $data['role'] ?? $assignment->role,
            ]);

            DB::commit();

            return $assignment->load(['user', 'task']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task assignment update failed', [
                'assignment_id' => $assignmentId,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Delete an assignment
     */
    public function deleteAssignment(string $assignmentId): bool
    {
        try {
            $assignment = TaskAssignment::find($assignmentId);
            if (!$assignment) {
                return false;
            }

            $assignment->delete();

            return true;

        } catch (\Exception $e) {
            Log::error('Task assignment deletion failed', [
                'assignment_id' => $assignmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Assign multiple users to a task
     */
    public function assignMultipleUsers(string $taskId, array $assignments): Collection
    {
        try {
            DB::beginTransaction();

            $createdAssignments = collect();

            foreach ($assignments as $assignmentData) {
                $assignmentData['task_id'] = $taskId;
                $assignment = $this->createAssignment($assignmentData);
                $createdAssignments->push($assignment);
            }

            DB::commit();

            return $createdAssignments;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Multiple user assignment failed', [
                'task_id' => $taskId,
                'assignments' => $assignments,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update all assignments for a task
     */
    public function updateTaskAssignments(string $taskId, array $assignments): Collection
    {
        try {
            DB::beginTransaction();

            // Delete existing assignments
            TaskAssignment::where('task_id', $taskId)->delete();

            // Create new assignments
            $createdAssignments = collect();
            foreach ($assignments as $assignmentData) {
                $assignmentData['task_id'] = $taskId;
                $assignment = TaskAssignment::create($assignmentData);
                $createdAssignments->push($assignment);
            }

            DB::commit();

            return $createdAssignments->load(['user', 'task']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task assignments update failed', [
                'task_id' => $taskId,
                'assignments' => $assignments,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Get assignment statistics for a user
     */
    public function getUserAssignmentStats(string $userId): array
    {
        $assignments = TaskAssignment::where('user_id', $userId)
            ->with('task')
            ->get();

        $stats = [
            'total_assignments' => $assignments->count(),
            'by_status' => $assignments->groupBy('task.status')->map->count(),
            'by_priority' => $assignments->groupBy('task.priority')->map->count(),
            'total_split_percent' => $assignments->sum('split_percent'),
            'by_project' => $assignments->groupBy('task.project_id')->map->count(),
        ];

        return $stats;
    }

    /**
     * Get assignment statistics for a task
     */
    public function getTaskAssignmentStats(string $taskId): array
    {
        $assignments = TaskAssignment::where('task_id', $taskId)
            ->with('user')
            ->get();

        $stats = [
            'total_assignments' => $assignments->count(),
            'total_split_percent' => $assignments->sum('split_percent'),
            'by_role' => $assignments->groupBy('role')->map->count(),
            'assigned_users' => $assignments->pluck('user.name')->toArray(),
        ];

        return $stats;
    }

    /**
     * Check if user is assigned to task
     */
    public function isUserAssignedToTask(string $userId, string $taskId): bool
    {
        return TaskAssignment::where('user_id', $userId)
            ->where('task_id', $taskId)
            ->exists();
    }

    /**
     * Get user's workload (total split percentage)
     */
    public function getUserWorkload(string $userId): float
    {
        return TaskAssignment::where('user_id', $userId)
            ->whereHas('task', function ($q) {
                $q->whereIn('status', ['pending', 'in_progress']);
            })
            ->sum('split_percent');
    }
}
