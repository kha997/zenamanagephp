<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Subtask;
use App\Models\Task;
use App\Models\User;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Subtask Management Service
 * 
 * Handles all subtask-related business logic including CRUD operations,
 * bulk actions, and subtask relationships.
 */
class SubtaskManagementService
{
    use ServiceBaseTrait;

    /**
     * Get subtasks for a task
     */
    public function getSubtasksForTask(string|int $taskId, ?string $tenantId = null): Collection
    {
        $this->validateTenantAccess($tenantId);
        
        return Subtask::with(['assignee', 'creator'])
            ->where('task_id', $taskId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->ordered()
            ->get();
    }

    /**
     * Get subtask by ID with tenant isolation
     */
    public function getSubtaskById(string|int $id, ?string $tenantId = null): ?Subtask
    {
        $this->validateTenantAccess($tenantId);
        
        return Subtask::with(['task', 'assignee', 'creator'])
            ->where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->first();
    }

    /**
     * Create new subtask
     */
    public function createSubtask(array $data, ?string $tenantId = null): Subtask
    {
        $this->validateTenantAccess($tenantId);
        
        // Verify parent task exists
        $task = Task::where('id', $data['task_id'])
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->first();
        
        if (!$task) {
            throw new \InvalidArgumentException('Parent task not found');
        }
        
        $data['tenant_id'] = $tenantId;
        $data['created_by'] = auth()->id();
        
        // Set sort order if not provided
        if (!isset($data['sort_order'])) {
            $maxOrder = Subtask::where('task_id', $data['task_id'])
                ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
                ->max('sort_order') ?? 0;
            $data['sort_order'] = $maxOrder + 1;
        }
        
        $subtask = Subtask::create($data);
        
        $this->logCrudOperation('created', $subtask, $data);
        
        // Update parent task progress
        $this->updateParentTaskProgress($task);
        
        return $subtask->load(['task', 'assignee', 'creator']);
    }

    /**
     * Update subtask
     */
    public function updateSubtask(string|int $id, array $data, ?string $tenantId = null): Subtask
    {
        $this->validateTenantAccess($tenantId);
        
        $subtask = Subtask::where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();
        
        $subtask->update($data);
        
        $this->logCrudOperation('updated', $subtask, $data);
        
        // Update parent task progress
        $this->updateParentTaskProgress($subtask->task);
        
        return $subtask->load(['task', 'assignee', 'creator']);
    }

    /**
     * Delete subtask
     */
    public function deleteSubtask(string|int $id, ?string $tenantId = null): bool
    {
        $this->validateTenantAccess($tenantId);
        
        $subtask = Subtask::where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();
        
        $task = $subtask->task;
        
        $this->logCrudOperation('deleted', $subtask, ['name' => $subtask->name]);
        
        $deleted = $subtask->delete();
        
        // Update parent task progress
        if ($deleted && $task) {
            $this->updateParentTaskProgress($task);
        }
        
        return $deleted;
    }

    /**
     * Bulk delete subtasks
     */
    public function bulkDeleteSubtasks(array $subtaskIds, ?string $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        $subtasks = Subtask::whereIn('id', $subtaskIds)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->get();
        
        $deletedCount = 0;
        $taskIds = [];
        
        foreach ($subtasks as $subtask) {
            $taskIds[] = $subtask->task_id;
            $subtask->delete();
            $deletedCount++;
        }
        
        $this->logBulkOperation('deleted', 'Subtask', $deletedCount, [
            'subtask_ids' => $subtaskIds
        ]);
        
        // Update parent task progress for affected tasks
        foreach (array_unique($taskIds) as $taskId) {
            $task = Task::find($taskId);
            if ($task) {
                $this->updateParentTaskProgress($task);
            }
        }
        
        return [
            'success' => true,
            'message' => "Successfully deleted {$deletedCount} subtasks",
            'deleted_count' => $deletedCount
        ];
    }

    /**
     * Bulk update subtask status
     */
    public function bulkUpdateStatus(array $subtaskIds, string $status, ?string $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        if (!in_array($status, Subtask::VALID_STATUSES)) {
            return [
                'success' => false,
                'message' => 'Invalid status provided'
            ];
        }
        
        $subtasks = Subtask::whereIn('id', $subtaskIds)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->get();
        
        $updatedCount = 0;
        $taskIds = [];
        
        foreach ($subtasks as $subtask) {
            $subtask->update(['status' => $status]);
            $taskIds[] = $subtask->task_id;
            $updatedCount++;
        }
        
        $this->logBulkOperation('status_updated', 'Subtask', $updatedCount, [
            'new_status' => $status,
            'subtask_ids' => $subtaskIds
        ]);
        
        // Update parent task progress for affected tasks
        foreach (array_unique($taskIds) as $taskId) {
            $task = Task::find($taskId);
            if ($task) {
                $this->updateParentTaskProgress($task);
            }
        }
        
        return [
            'success' => true,
            'message' => "Successfully updated {$updatedCount} subtasks to {$status}",
            'updated_count' => $updatedCount
        ];
    }

    /**
     * Bulk assign subtasks
     */
    public function bulkAssignSubtasks(array $subtaskIds, string $assigneeId, ?string $tenantId = null): array
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
        
        $updatedCount = Subtask::whereIn('id', $subtaskIds)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->update(['assignee_id' => $assigneeId]);
        
        $this->logBulkOperation('assigned', 'Subtask', $updatedCount, [
            'assignee_id' => $assigneeId,
            'subtask_ids' => $subtaskIds
        ]);
        
        return [
            'success' => true,
            'message' => "Successfully assigned {$updatedCount} subtasks to {$assignee->name}",
            'updated_count' => $updatedCount
        ];
    }

    /**
     * Update subtask progress
     */
    public function updateSubtaskProgress(string|int $id, float $progress, ?string $tenantId = null): Subtask
    {
        $this->validateTenantAccess($tenantId);
        
        $subtask = Subtask::where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->firstOrFail();
        
        $subtask->updateProgress($progress);
        
        $this->logCrudOperation('progress_updated', $subtask, [
            'old_progress' => $subtask->progress_percent,
            'new_progress' => $progress
        ]);
        
        return $subtask->load(['task', 'assignee', 'creator']);
    }

    /**
     * Reorder subtasks
     */
    public function reorderSubtasks(array $subtaskIds, ?string $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        $subtasks = Subtask::whereIn('id', $subtaskIds)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->get();
        
        foreach ($subtaskIds as $index => $subtaskId) {
            $subtask = $subtasks->firstWhere('id', $subtaskId);
            if ($subtask) {
                $subtask->update(['sort_order' => $index + 1]);
            }
        }
        
        $this->logCrudOperation('reordered', null, [
            'subtask_ids' => $subtaskIds,
            'new_order' => $subtaskIds
        ]);
        
        return [
            'success' => true,
            'message' => 'Subtasks reordered successfully'
        ];
    }

    /**
     * Get subtask statistics for a task
     */
    public function getSubtaskStatistics(string|int $taskId, ?string $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        $baseQuery = Subtask::where('task_id', $taskId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));
        
        return [
            'total' => (clone $baseQuery)->count(),
            'pending' => (clone $baseQuery)->where('status', Subtask::STATUS_PENDING)->count(),
            'in_progress' => (clone $baseQuery)->where('status', Subtask::STATUS_IN_PROGRESS)->count(),
            'completed' => (clone $baseQuery)->where('status', Subtask::STATUS_COMPLETED)->count(),
            'canceled' => (clone $baseQuery)->where('status', Subtask::STATUS_CANCELED)->count(),
            'total_estimated_hours' => (clone $baseQuery)->sum('estimated_hours'),
            'total_actual_hours' => (clone $baseQuery)->sum('actual_hours'),
            'average_progress' => (clone $baseQuery)->avg('progress_percent') ?? 0
        ];
    }

    /**
     * Update parent task progress based on subtasks
     */
    private function updateParentTaskProgress(Task $task): void
    {
        $subtasks = $task->subtasks;
        if ($subtasks->isEmpty()) {
            $task->update(['progress_percent' => 0]);
            return;
        }

        $totalProgress = $subtasks->sum('progress_percent');
        $averageProgress = $totalProgress / $subtasks->count();
        
        $task->updateProgress($averageProgress);
    }
}
