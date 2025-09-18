<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use Src\CoreProject\Models\Task;
use Src\CoreProject\Models\TaskAssignment;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Task Service - Business logic for task management
 */
class TaskService
{
    /**
     * Get filtered tasks with pagination
     */
    public function getTasks(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        $query = Task::with(['project', 'assignments.user']);

        // Apply project filter only if project_id is provided
        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['component_id'])) {
            $query->where('component_id', $filters['component_id']);
        }

        if (!empty($filters['assigned_to'])) {
            $query->whereHas('assignments', function ($q) use ($filters) {
                $q->where('user_id', $filters['assigned_to']);
            });
        }

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'LIKE', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'LIKE', '%' . $filters['search'] . '%');
            });
        }

        if (!empty($filters['start_date'])) {
            $query->where('start_date', '>=', $filters['start_date']);
        }

        if (!empty($filters['end_date'])) {
            $query->where('end_date', '<=', $filters['end_date']);
        }

        // Default sorting
        $query->orderBy('created_at', 'desc');

        return $query->paginate($perPage);
    }

    /**
     * Get task by ID with optional includes
     */
    public function getTaskById(string $taskId, array $includes = []): ?Task
    {
        $query = Task::query();

        if (in_array('assignments', $includes)) {
            $query->with('assignments.user');
        }

        if (in_array('component', $includes)) {
            // $query->with('component'); // Component table doesn't exist
        }

        if (in_array('project', $includes)) {
            $query->with('project');
        }

        if (in_array('interaction_logs', $includes)) {
            $query->with('interactionLogs');
        }

        return $query->find($taskId);
    }

    /**
     * Create a new task
     */
    public function createTask(array $data): Task
    {
        try {
            DB::beginTransaction();

            $task = Task::create([
                'project_id' => $data['project_id'],
                'component_id' => $data['component_id'] ?? null,
                'phase_id' => $data['phase_id'] ?? null,
                'name' => $data['name'] ?? 'Untitled Task',
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'status' => $data['status'] ?? 'pending',
                'priority' => $data['priority'] ?? 'medium',
                'dependencies' => $data['dependencies'] ?? null,
                'conditional_tag' => $data['conditional_tag'] ?? null,
                'is_hidden' => $data['is_hidden'] ?? false,
                'estimated_hours' => $data['estimated_hours'] ?? 0.0,
                'actual_hours' => $data['actual_hours'] ?? 0.0,
                'progress_percent' => $data['progress_percent'] ?? 0.0,
                'tags' => $data['tags'] ?? null,
                'visibility' => $data['visibility'] ?? 'internal',
                'client_approved' => $data['client_approved'] ?? false,
                'assignee_id' => $data['assignee_id'] ?? null,
            ]);

            // Create assignments if provided
            if (!empty($data['assignments'])) {
                foreach ($data['assignments'] as $assignment) {
                    TaskAssignment::create([
                        'task_id' => $task->id,
                        'user_id' => $assignment['user_id'],
                        'split_percent' => $assignment['split_percent'] ?? 100.0,
                        'role' => $assignment['role'] ?? 'assignee',
                    ]);
                }
            }

            DB::commit();

            return $task->load(['project', 'assignments.user']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task creation failed', [
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update an existing task
     */
    public function updateTask(string $taskId, array $data): ?Task
    {
        try {
            // Debug: Log update data
            \Log::info('TaskService updateTask called', [
                'task_id' => $taskId,
                'data' => $data
            ]);
            
            DB::beginTransaction();

            $task = Task::find($taskId);
            if (!$task) {
                \Log::error('Task not found in TaskService', ['task_id' => $taskId]);
                return null;
            }

            $task->update([
                'project_id' => $data['project_id'] ?? $task->project_id,
                'component_id' => $data['component_id'] ?? $task->component_id,
                'phase_id' => $data['phase_id'] ?? $task->phase_id,
                'name' => $data['name'] ?? $task->name,
                'description' => $data['description'] ?? $task->description,
                'start_date' => $data['start_date'] ?? $task->start_date,
                'end_date' => $data['end_date'] ?? $task->end_date,
                'status' => $data['status'] ?? $task->status,
                'priority' => $data['priority'] ?? $task->priority,
                'dependencies' => $data['dependencies'] ?? $task->dependencies,
                'conditional_tag' => $data['conditional_tag'] ?? $task->conditional_tag,
                'is_hidden' => $data['is_hidden'] ?? $task->is_hidden,
                'estimated_hours' => $data['estimated_hours'] ?? $task->estimated_hours,
                'actual_hours' => $data['actual_hours'] ?? $task->actual_hours,
                'progress_percent' => $data['progress_percent'] ?? $task->progress_percent,
                'tags' => $data['tags'] ?? $task->tags,
                'visibility' => $data['visibility'] ?? $task->visibility,
                'client_approved' => $data['client_approved'] ?? $task->client_approved,
                'assignee_id' => !empty($data['assignee_id']) ? $data['assignee_id'] : null,
            ]);

            // Update assignments if provided
            if (isset($data['assignments'])) {
                // Delete existing assignments
                $task->assignments()->delete();

                // Create new assignments
                foreach ($data['assignments'] as $assignment) {
                    TaskAssignment::create([
                        'task_id' => $task->id,
                        'user_id' => $assignment['user_id'],
                        'split_percent' => $assignment['split_percent'] ?? 100.0,
                        'role' => $assignment['role'] ?? 'assignee',
                    ]);
                }
            }

            DB::commit();

            return $task->load(['project', 'assignments.user']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Task update failed', [
                'task_id' => $taskId,
                'data' => $data,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Update task status
     */
    public function updateTaskStatus(string $taskId, string $status): ?Task
    {
        $task = Task::find($taskId);
        if (!$task) {
            return null;
        }

        $task->update(['status' => $status]);

        return $task->load(['project', 'assignments.user']);
    }

    /**
     * Delete a task
     */
    public function deleteTask(string $taskId): bool
    {
        try {
            $task = Task::find($taskId);
            if (!$task) {
                return false;
            }

            // Delete assignments first
            $task->assignments()->delete();

            // Delete the task
            $task->delete();

            return true;

        } catch (\Exception $e) {
            Log::error('Task deletion failed', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    /**
     * Get tasks by project
     */
    public function getTasksByProject(string $projectId, array $filters = []): Collection
    {
        $query = Task::where('project_id', $projectId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['component_id'])) {
            $query->where('component_id', $filters['component_id']);
        }

        return $query->with(['assignments.user'])->get();
    }

    /**
     * Get tasks by component
     */
    public function getTasksByComponent(string $componentId): Collection
    {
        return Task::where('component_id', $componentId)
            ->with(['project', 'assignments.user'])
            ->get();
    }

    /**
     * Get tasks assigned to user
     */
    public function getTasksByUser(string $userId, array $filters = []): Collection
    {
        $query = Task::whereHas('assignments', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        });

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        return $query->with(['project', 'assignments.user'])->get();
    }
}
