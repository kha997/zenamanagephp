<?php

namespace App\Repositories;

use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class TaskRepository
{
    protected $model;

    public function __construct(Task $model)
    {
        $this->model = $model;
    }

    /**
     * Get all tasks with pagination.
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }

        if (isset($filters['assignee_id'])) {
            $query->where('assignee_id', $filters['assignee_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['due_date_from'])) {
            $query->where('due_date', '>=', $filters['due_date_from']);
        }

        if (isset($filters['due_date_to'])) {
            $query->where('due_date', '<=', $filters['due_date_to']);
        }

        return $query->with(['project', 'assignee', 'creator', 'dependencies'])->paginate($perPage);
    }

    /**
     * Get task by ID.
     */
    public function getById(int $id): ?Task
    {
        return $this->model->with(['project', 'assignee', 'creator', 'dependencies'])->find($id);
    }

    /**
     * Get tasks by project ID.
     */
    public function getByProjectId(int $projectId): Collection
    {
        return $this->model->where('project_id', $projectId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get tasks by assignee ID.
     */
    public function getByAssigneeId(int $assigneeId): Collection
    {
        return $this->model->where('assignee_id', $assigneeId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get tasks by status.
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get tasks by priority.
     */
    public function getByPriority(string $priority): Collection
    {
        return $this->model->where('priority', $priority)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Create a new task.
     */
    public function create(array $data): Task
    {
        $task = $this->model->create($data);

        // Add dependencies if provided
        if (isset($data['dependencies'])) {
            $task->dependencies()->sync($data['dependencies']);
        }

        Log::info('Task created', [
            'task_id' => $task->id,
            'name' => $task->name,
            'project_id' => $task->project_id,
            'assignee_id' => $task->assignee_id
        ]);

        return $task->load(['project', 'assignee', 'creator', 'dependencies']);
    }

    /**
     * Update task.
     */
    public function update(int $id, array $data): ?Task
    {
        $task = $this->model->find($id);

        if (!$task) {
            return null;
        }

        $task->update($data);

        // Update dependencies if provided
        if (isset($data['dependencies'])) {
            $task->dependencies()->sync($data['dependencies']);
        }

        Log::info('Task updated', [
            'task_id' => $task->id,
            'name' => $task->name,
            'project_id' => $task->project_id
        ]);

        return $task->load(['project', 'assignee', 'creator', 'dependencies']);
    }

    /**
     * Delete task.
     */
    public function delete(int $id): bool
    {
        $task = $this->model->find($id);

        if (!$task) {
            return false;
        }

        $task->delete();

        Log::info('Task deleted', [
            'task_id' => $id,
            'name' => $task->name,
            'project_id' => $task->project_id
        ]);

        return true;
    }

    /**
     * Soft delete task.
     */
    public function softDelete(int $id): bool
    {
        $task = $this->model->find($id);

        if (!$task) {
            return false;
        }

        $task->delete();

        Log::info('Task soft deleted', [
            'task_id' => $id,
            'name' => $task->name,
            'project_id' => $task->project_id
        ]);

        return true;
    }

    /**
     * Restore soft deleted task.
     */
    public function restore(int $id): bool
    {
        $task = $this->model->withTrashed()->find($id);

        if (!$task) {
            return false;
        }

        $task->restore();

        Log::info('Task restored', [
            'task_id' => $id,
            'name' => $task->name,
            'project_id' => $task->project_id
        ]);

        return true;
    }

    /**
     * Get pending tasks.
     */
    public function getPending(): Collection
    {
        return $this->model->where('status', 'pending')
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get in progress tasks.
     */
    public function getInProgress(): Collection
    {
        return $this->model->where('status', 'in_progress')
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get completed tasks.
     */
    public function getCompleted(): Collection
    {
        return $this->model->where('status', 'completed')
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get overdue tasks.
     */
    public function getOverdue(): Collection
    {
        return $this->model->where('due_date', '<', now())
                          ->where('status', '!=', 'completed')
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get tasks due soon.
     */
    public function getDueSoon(int $days = 3): Collection
    {
        $dueDate = now()->addDays($days);

        return $this->model->where('due_date', '<=', $dueDate)
                          ->where('due_date', '>=', now())
                          ->where('status', '!=', 'completed')
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get high priority tasks.
     */
    public function getHighPriority(): Collection
    {
        return $this->model->where('priority', 'high')
                          ->where('status', '!=', 'completed')
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Update task status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $task = $this->model->find($id);

        if (!$task) {
            return false;
        }

        $task->update([
            'status' => $status,
            'status_updated_at' => now()
        ]);

        Log::info('Task status updated', [
            'task_id' => $id,
            'status' => $status
        ]);

        return true;
    }

    /**
     * Assign task to user.
     */
    public function assignToUser(int $taskId, int $userId): bool
    {
        $task = $this->model->find($taskId);

        if (!$task) {
            return false;
        }

        $task->update([
            'assignee_id' => $userId,
            'assigned_at' => now()
        ]);

        Log::info('Task assigned to user', [
            'task_id' => $taskId,
            'user_id' => $userId
        ]);

        return true;
    }

    /**
     * Add dependency to task.
     */
    public function addDependency(int $taskId, int $dependencyId): bool
    {
        $task = $this->model->find($taskId);

        if (!$task) {
            return false;
        }

        $task->dependencies()->syncWithoutDetaching([$dependencyId]);

        Log::info('Task dependency added', [
            'task_id' => $taskId,
            'dependency_id' => $dependencyId
        ]);

        return true;
    }

    /**
     * Remove dependency from task.
     */
    public function removeDependency(int $taskId, int $dependencyId): bool
    {
        $task = $this->model->find($taskId);

        if (!$task) {
            return false;
        }

        $task->dependencies()->detach($dependencyId);

        Log::info('Task dependency removed', [
            'task_id' => $taskId,
            'dependency_id' => $dependencyId
        ]);

        return true;
    }

    /**
     * Get task statistics.
     */
    public function getStatistics(int $projectId = null): array
    {
        $query = $this->model->query();

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        return [
            'total_tasks' => $query->count(),
            'pending_tasks' => $query->where('status', 'pending')->count(),
            'in_progress_tasks' => $query->where('status', 'in_progress')->count(),
            'completed_tasks' => $query->where('status', 'completed')->count(),
            'overdue_tasks' => $query->where('due_date', '<', now())
                                   ->where('status', '!=', 'completed')
                                   ->count(),
            'high_priority_tasks' => $query->where('priority', 'high')->count(),
            'medium_priority_tasks' => $query->where('priority', 'medium')->count(),
            'low_priority_tasks' => $query->where('priority', 'low')->count()
        ];
    }

    /**
     * Search tasks.
     */
    public function search(string $term, int $limit = 10): Collection
    {
        return $this->model->where(function ($q) use ($term) {
            $q->where('name', 'like', '%' . $term . '%')
              ->orWhere('description', 'like', '%' . $term . '%');
        })->with(['project', 'assignee', 'creator', 'dependencies'])
          ->limit($limit)
          ->get();
    }

    /**
     * Get tasks by multiple IDs.
     */
    public function getByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Bulk update tasks.
     */
    public function bulkUpdate(array $ids, array $data): int
    {
        $updated = $this->model->whereIn('id', $ids)->update($data);

        Log::info('Tasks bulk updated', [
            'count' => $updated,
            'ids' => $ids
        ]);

        return $updated;
    }

    /**
     * Bulk delete tasks.
     */
    public function bulkDelete(array $ids): int
    {
        $deleted = $this->model->whereIn('id', $ids)->delete();

        Log::info('Tasks bulk deleted', [
            'count' => $deleted,
            'ids' => $ids
        ]);

        return $deleted;
    }

    /**
     * Get task progress.
     */
    public function getProgress(int $id): array
    {
        $task = $this->model->find($id);

        if (!$task) {
            return [];
        }

        $progress = 0;
        if ($task->status === 'completed') {
            $progress = 100;
        } elseif ($task->status === 'in_progress') {
            $progress = 50; // Default progress for in-progress tasks
        }

        return [
            'status' => $task->status,
            'progress_percentage' => $progress,
            'estimated_completion' => $this->calculateEstimatedCompletion($task)
        ];
    }

    /**
     * Calculate estimated completion date.
     */
    protected function calculateEstimatedCompletion(Task $task): ?string
    {
        if ($task->status === 'completed') {
            return $task->completed_at?->toDateString();
        }

        if ($task->due_date) {
            return $task->due_date->toDateString();
        }

        // Calculate based on dependencies
        $dependencies = $task->dependencies;
        if ($dependencies->count() > 0) {
            $maxDependencyDate = $dependencies->max('due_date');
            if ($maxDependencyDate) {
                return $maxDependencyDate->addDays(1)->toDateString();
            }
        }

        return null;
    }

    /**
     * Get task timeline.
     */
    public function getTimeline(int $id): array
    {
        $task = $this->model->with(['dependencies'])->find($id);

        if (!$task) {
            return [];
        }

        $timeline = [];

        // Add task creation
        $timeline[] = [
            'type' => 'task_created',
            'date' => $task->created_at,
            'title' => 'Task Created',
            'description' => 'Task ' . $task->name . ' was created'
        ];

        // Add assignment
        if ($task->assignee_id && $task->assigned_at) {
            $timeline[] = [
                'type' => 'task_assigned',
                'date' => $task->assigned_at,
                'title' => 'Task Assigned',
                'description' => 'Task assigned to user'
            ];
        }

        // Add status changes
        if ($task->status_updated_at) {
            $timeline[] = [
                'type' => 'status_changed',
                'date' => $task->status_updated_at,
                'title' => 'Status Changed',
                'description' => 'Status changed to ' . $task->status
            ];
        }

        // Add completion
        if ($task->completed_at) {
            $timeline[] = [
                'type' => 'task_completed',
                'date' => $task->completed_at,
                'title' => 'Task Completed',
                'description' => 'Task ' . $task->name . ' was completed'
            ];
        }

        // Sort by date
        usort($timeline, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $timeline;
    }

    /**
     * Get tasks with dependencies.
     */
    public function getWithDependencies(): Collection
    {
        return $this->model->whereHas('dependencies')
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get tasks without dependencies.
     */
    public function getWithoutDependencies(): Collection
    {
        return $this->model->whereDoesntHave('dependencies')
                          ->with(['project', 'assignee', 'creator'])
                          ->get();
    }
}
