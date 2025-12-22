<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Task;
use App\Models\Project;
use App\Models\User;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Task Management Service
 * 
 * Handles all task-related business logic including CRUD operations,
 * bulk actions, filtering, and task relationships.
 */
class TaskManagementService
{
    use ServiceBaseTrait;

    /**
     * Get tasks with filtering and pagination
     */
    public function getTasks(
        array $filters = [],
        int $perPage = 15,
        string $sortBy = 'updated_at',
        string $sortDirection = 'desc',
        string|int|null $tenantId = null
    ): LengthAwarePaginator
    {
        $this->validateTenantAccess($tenantId);
        
        $query = Task::with(['project', 'assignee', 'creator'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));

        // Apply filters
        if (isset($filters['project_id']) && $filters['project_id']) {
            $query->where('project_id', $filters['project_id']);
        }

        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['priority']) && $filters['priority']) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['assignee_id']) && $filters['assignee_id']) {
            $query->where('assignee_id', $filters['assignee_id']);
        }

        if (isset($filters['search']) && $filters['search']) {
            $query->where(function($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['start_date_from']) && $filters['start_date_from']) {
            $query->where('start_date', '>=', $filters['start_date_from']);
        }

        if (isset($filters['start_date_to']) && $filters['start_date_to']) {
            $query->where('start_date', '<=', $filters['start_date_to']);
        }

        if (isset($filters['end_date_from']) && $filters['end_date_from']) {
            $query->where('end_date', '>=', $filters['end_date_from']);
        }

        if (isset($filters['end_date_to']) && $filters['end_date_to']) {
            $query->where('end_date', '<=', $filters['end_date_to']);
        }

        // Sorting and pagination
        $sortBy = $filters['sort_by'] ?? $sortBy;
        $sortDirection = $filters['sort_direction'] ?? $sortDirection;
        $perPage = $filters['per_page'] ?? $perPage;

        return $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage);
    }

    /**
     * Get task by ID with tenant isolation
     */
    public function getTaskById(string|int $id, ?string $tenantId = null): ?Task
    {
        $this->validateTenantAccess($tenantId);
        
        return Task::with(['project', 'assignee', 'creator', 'dependencies'])
            ->where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->first();
    }

    /**
     * Create new task
     */
    public function createTask(array $data, ?string $tenantId = null): Task
    {
        $this->validateTenantAccess($tenantId);
        
        $data['tenant_id'] = $tenantId;
        $data['created_by'] = auth()->id();
        
        $task = Task::create($data);
        
        $this->logCrudOperation('created', $task, $data);
        
        return $task->load(['project', 'assignee', 'creator']);
    }

    /**
     * Update task
     */
    public function updateTask(string|int $id, array $data, ?string $tenantId = null): Task
    {
        $this->validateTenantAccess($tenantId);
        
        $task = Task::where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();
        
        $task->update($data);
        
        $this->logCrudOperation('updated', $task, $data);
        
        return $task->load(['project', 'assignee', 'creator']);
    }

    /**
     * Delete task
     */
    public function deleteTask(string|int $id, ?string $tenantId = null): bool
    {
        $this->validateTenantAccess($tenantId);
        
        $task = Task::where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();
        
        $this->logCrudOperation('deleted', $task, ['name' => $task->name]);
        
        return $task->delete();
    }

    /**
     * Bulk delete tasks
     */
    public function bulkDeleteTasks(array $taskIds, ?string $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        $tasks = Task::whereIn('id', $taskIds)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->get();
        
        $deletedCount = 0;
        
        foreach ($tasks as $task) {
            $task->delete();
            $deletedCount++;
        }
        
        $this->logBulkOperation('deleted', 'Task', $deletedCount, [
            'task_ids' => $taskIds
        ]);
        
        return [
            'success' => true,
            'message' => "Successfully deleted {$deletedCount} tasks",
            'deleted_count' => $deletedCount
        ];
    }

    /**
     * Bulk update task status
     */
    public function bulkUpdateStatus(array $taskIds, string $status, ?string $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        if (!in_array($status, Task::VALID_STATUSES)) {
            return [
                'success' => false,
                'message' => 'Invalid status provided'
            ];
        }
        
        $updatedCount = Task::whereIn('id', $taskIds)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->update(['status' => $status]);
        
        $this->logBulkOperation('status_updated', 'Task', $updatedCount, [
            'new_status' => $status,
            'task_ids' => $taskIds
        ]);
        
        return [
            'success' => true,
            'message' => "Successfully updated {$updatedCount} tasks to {$status}",
            'updated_count' => $updatedCount
        ];
    }

    /**
     * Bulk assign tasks
     */
    public function bulkAssignTasks(array $taskIds, string $assigneeId, ?string $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        // Verify assignee exists
        $assignee = User::where('id', $assigneeId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->first();
        
        if (!$assignee) {
            return [
                'success' => false,
                'message' => 'Assignee not found'
            ];
        }
        
        $updatedCount = Task::whereIn('id', $taskIds)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->update(['assignee_id' => $assigneeId]);
        
        $this->logBulkOperation('assigned', 'Task', $updatedCount, [
            'assignee_id' => $assigneeId,
            'task_ids' => $taskIds
        ]);
        
        return [
            'success' => true,
            'message' => "Successfully assigned {$updatedCount} tasks to {$assignee->name}",
            'updated_count' => $updatedCount
        ];
    }

    /**
     * Get tasks for project
     */
    public function getTasksForProject(string|int $projectId, ?string $tenantId = null): Collection
    {
        $this->validateTenantAccess($tenantId);
        
        return Task::with(['assignee', 'creator'])
            ->where('project_id', $projectId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('priority', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get task statistics for dashboard
     */
    public function getTaskStatistics(?string $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        $baseQuery = Task::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));
        
        return [
            'total' => (clone $baseQuery)->count(),
            'backlog' => (clone $baseQuery)->where('status', Task::STATUS_BACKLOG)->count(),
            'in_progress' => (clone $baseQuery)->where('status', Task::STATUS_IN_PROGRESS)->count(),
            'blocked' => (clone $baseQuery)->where('status', Task::STATUS_BLOCKED)->count(),
            'done' => (clone $baseQuery)->where('status', Task::STATUS_DONE)->count(),
            'canceled' => (clone $baseQuery)->where('status', Task::STATUS_CANCELED)->count(),
            'overdue' => (clone $baseQuery)->where('end_date', '<', now())
                ->whereNotIn('status', [Task::STATUS_DONE, Task::STATUS_CANCELED])
                ->count(),
            'due_today' => (clone $baseQuery)->whereDate('end_date', today())
                ->whereNotIn('status', [Task::STATUS_DONE, Task::STATUS_CANCELED])
                ->count(),
            'due_this_week' => (clone $baseQuery)->whereBetween('end_date', [now(), now()->addWeek()])
                ->whereNotIn('status', [Task::STATUS_DONE, Task::STATUS_CANCELED])
                ->count()
        ];
    }

    /**
     * Update task progress
     */
    public function updateTaskProgress(string|int $id, float $progress, ?string $tenantId = null): Task
    {
        $this->validateTenantAccess($tenantId);
        
        $task = Task::where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();
        
        $task->updateProgress($progress);
        
        $this->logCrudOperation('progress_updated', $task, [
            'old_progress' => $task->progress_percent,
            'new_progress' => $progress
        ]);
        
        return $task->load(['project', 'assignee', 'creator']);
    }
}
