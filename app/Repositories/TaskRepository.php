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
     * Get all tasks with pagination - MANDATORY tenant isolation
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // MANDATORY: Every query must filter by tenant_id
        if (!isset($filters['tenant_id']) || !$filters['tenant_id']) {
            throw new \InvalidArgumentException('tenant_id is required for all queries');
        }

        $query->where('tenant_id', $filters['tenant_id']);

        // Apply other filters
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
     * Get task by ID - MANDATORY tenant isolation
     */
    public function getById(string $id, string $tenantId): ?Task
    {
        return $this->model->where('id', $id)
                          ->where('tenant_id', $tenantId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->first();
    }

    /**
     * Get tasks by project ID - MANDATORY tenant isolation
     */
    public function getByProjectId(string $projectId, string $tenantId): Collection
    {
        return $this->model->where('project_id', $projectId)
                          ->where('tenant_id', $tenantId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get tasks by assignee ID - MANDATORY tenant isolation
     */
    public function getByAssigneeId(string $assigneeId, string $tenantId): Collection
    {
        return $this->model->where('assignee_id', $assigneeId)
                          ->where('tenant_id', $tenantId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get tasks by status - MANDATORY tenant isolation
     */
    public function getByStatus(string $status, string $tenantId): Collection
    {
        return $this->model->where('status', $status)
                          ->where('tenant_id', $tenantId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get tasks by priority - MANDATORY tenant isolation
     */
    public function getByPriority(string $priority, string $tenantId): Collection
    {
        return $this->model->where('priority', $priority)
                          ->where('tenant_id', $tenantId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Create a new task
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
            'assignee_id' => $task->assignee_id,
            'tenant_id' => $task->tenant_id
        ]);

        return $task->load(['project', 'assignee', 'creator', 'dependencies']);
    }

    /**
     * Update task - MANDATORY tenant isolation
     */
    public function update(string $id, array $data, string $tenantId): ?Task
    {
        $task = $this->model->where('id', $id)
                           ->where('tenant_id', $tenantId)
                           ->first();

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
            'project_id' => $task->project_id,
            'tenant_id' => $task->tenant_id
        ]);

        return $task->load(['project', 'assignee', 'creator', 'dependencies']);
    }

    /**
     * Delete task - MANDATORY tenant isolation
     */
    public function delete(string $id, string $tenantId): bool
    {
        $task = $this->model->where('id', $id)
                           ->where('tenant_id', $tenantId)
                           ->first();

        if (!$task) {
            return false;
        }

        $task->delete();

        Log::info('Task deleted', [
            'task_id' => $id,
            'name' => $task->name,
            'project_id' => $task->project_id,
            'tenant_id' => $task->tenant_id
        ]);

        return true;
    }

    /**
     * Soft delete task - MANDATORY tenant isolation
     */
    public function softDelete(string $id, string $tenantId): bool
    {
        $task = $this->model->where('id', $id)
                           ->where('tenant_id', $tenantId)
                           ->first();

        if (!$task) {
            return false;
        }

        $task->delete();

        Log::info('Task soft deleted', [
            'task_id' => $id,
            'name' => $task->name,
            'project_id' => $task->project_id,
            'tenant_id' => $task->tenant_id
        ]);

        return true;
    }

    /**
     * Restore soft deleted task - MANDATORY tenant isolation
     */
    public function restore(string $id, string $tenantId): bool
    {
        $task = $this->model->withTrashed()
                           ->where('id', $id)
                           ->where('tenant_id', $tenantId)
                           ->first();

        if (!$task) {
            return false;
        }

        $task->restore();

        Log::info('Task restored', [
            'task_id' => $id,
            'name' => $task->name,
            'project_id' => $task->project_id,
            'tenant_id' => $task->tenant_id
        ]);

        return true;
    }

    /**
     * Get pending tasks - MANDATORY tenant isolation
     */
    public function getPending(string $tenantId): Collection
    {
        return $this->model->where('status', 'pending')
                          ->where('tenant_id', $tenantId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get in progress tasks - MANDATORY tenant isolation
     */
    public function getInProgress(string $tenantId): Collection
    {
        return $this->model->where('status', 'in_progress')
                          ->where('tenant_id', $tenantId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get completed tasks - MANDATORY tenant isolation
     */
    public function getCompleted(string $tenantId): Collection
    {
        return $this->model->where('status', 'completed')
                          ->where('tenant_id', $tenantId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get overdue tasks - MANDATORY tenant isolation
     */
    public function getOverdue(string $tenantId): Collection
    {
        return $this->model->where('due_date', '<', now())
                          ->where('status', '!=', 'completed')
                          ->where('tenant_id', $tenantId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get tasks due soon - MANDATORY tenant isolation
     */
    public function getDueSoon(string $tenantId, int $days = 3): Collection
    {
        $dueDate = now()->addDays($days);

        return $this->model->where('due_date', '<=', $dueDate)
                          ->where('due_date', '>=', now())
                          ->where('status', '!=', 'completed')
                          ->where('tenant_id', $tenantId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get high priority tasks - MANDATORY tenant isolation
     */
    public function getHighPriority(string $tenantId): Collection
    {
        return $this->model->where('priority', 'high')
                          ->where('status', '!=', 'completed')
                          ->where('tenant_id', $tenantId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Update task status - MANDATORY tenant isolation
     */
    public function updateStatus(string $id, string $status, string $tenantId): bool
    {
        $task = $this->model->where('id', $id)
                           ->where('tenant_id', $tenantId)
                           ->first();

        if (!$task) {
            return false;
        }

        $task->update([
            'status' => $status,
            'status_updated_at' => now()
        ]);

        Log::info('Task status updated', [
            'task_id' => $id,
            'status' => $status,
            'tenant_id' => $tenantId
        ]);

        return true;
    }

    /**
     * Assign task to user - MANDATORY tenant isolation
     */
    public function assignToUser(string $taskId, string $userId, string $tenantId): bool
    {
        $task = $this->model->where('id', $taskId)
                           ->where('tenant_id', $tenantId)
                           ->first();

        if (!$task) {
            return false;
        }

        $task->update([
            'assignee_id' => $userId,
            'assigned_at' => now()
        ]);

        Log::info('Task assigned to user', [
            'task_id' => $taskId,
            'user_id' => $userId,
            'tenant_id' => $tenantId
        ]);

        return true;
    }

    /**
     * Add dependency to task - MANDATORY tenant isolation
     */
    public function addDependency(string $taskId, string $dependencyId, string $tenantId): bool
    {
        $task = $this->model->where('id', $taskId)
                           ->where('tenant_id', $tenantId)
                           ->first();

        if (!$task) {
            return false;
        }

        $task->dependencies()->syncWithoutDetaching([$dependencyId]);

        Log::info('Task dependency added', [
            'task_id' => $taskId,
            'dependency_id' => $dependencyId,
            'tenant_id' => $tenantId
        ]);

        return true;
    }

    /**
     * Remove dependency from task - MANDATORY tenant isolation
     */
    public function removeDependency(string $taskId, string $dependencyId, string $tenantId): bool
    {
        $task = $this->model->where('id', $taskId)
                           ->where('tenant_id', $tenantId)
                           ->first();

        if (!$task) {
            return false;
        }

        $task->dependencies()->detach($dependencyId);

        Log::info('Task dependency removed', [
            'task_id' => $taskId,
            'dependency_id' => $dependencyId,
            'tenant_id' => $tenantId
        ]);

        return true;
    }

    /**
     * Get task statistics - MANDATORY tenant isolation
     */
    public function getStatistics(string $tenantId, string $projectId = null): array
    {
        $query = $this->model->where('tenant_id', $tenantId);

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
     * Search tasks - MANDATORY tenant isolation
     */
    public function search(string $term, string $tenantId, int $limit = 10): Collection
    {
        return $this->model->where('tenant_id', $tenantId)
                          ->where(function ($q) use ($term) {
                              $q->where('name', 'like', '%' . $term . '%')
                                ->orWhere('description', 'like', '%' . $term . '%');
                          })->with(['project', 'assignee', 'creator', 'dependencies'])
                            ->limit($limit)
                            ->get();
    }

    /**
     * Get tasks by multiple IDs - MANDATORY tenant isolation
     */
    public function getByIds(array $ids, string $tenantId): Collection
    {
        return $this->model->whereIn('id', $ids)
                          ->where('tenant_id', $tenantId)
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Bulk update tasks - MANDATORY tenant isolation
     */
    public function bulkUpdate(array $ids, array $data, string $tenantId): int
    {
        $updated = $this->model->whereIn('id', $ids)
                              ->where('tenant_id', $tenantId)
                              ->update($data);

        Log::info('Tasks bulk updated', [
            'count' => $updated,
            'ids' => $ids,
            'tenant_id' => $tenantId
        ]);

        return $updated;
    }

    /**
     * Bulk delete tasks - MANDATORY tenant isolation
     */
    public function bulkDelete(array $ids, string $tenantId): int
    {
        $deleted = $this->model->whereIn('id', $ids)
                              ->where('tenant_id', $tenantId)
                              ->delete();

        Log::info('Tasks bulk deleted', [
            'count' => $deleted,
            'ids' => $ids,
            'tenant_id' => $tenantId
        ]);

        return $deleted;
    }

    /**
     * Get task progress - MANDATORY tenant isolation
     */
    public function getProgress(string $id, string $tenantId): array
    {
        $task = $this->model->where('id', $id)
                           ->where('tenant_id', $tenantId)
                           ->first();

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
     * Get task timeline - MANDATORY tenant isolation
     */
    public function getTimeline(string $id, string $tenantId): array
    {
        $task = $this->model->where('id', $id)
                           ->where('tenant_id', $tenantId)
                           ->with(['dependencies'])
                           ->first();

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
     * Get tasks with dependencies - MANDATORY tenant isolation
     */
    public function getWithDependencies(string $tenantId): Collection
    {
        return $this->model->where('tenant_id', $tenantId)
                          ->whereHas('dependencies')
                          ->with(['project', 'assignee', 'creator', 'dependencies'])
                          ->get();
    }

    /**
     * Get tasks without dependencies - MANDATORY tenant isolation
     */
    public function getWithoutDependencies(string $tenantId): Collection
    {
        return $this->model->where('tenant_id', $tenantId)
                          ->whereDoesntHave('dependencies')
                          ->with(['project', 'assignee', 'creator'])
                          ->get();
    }
}