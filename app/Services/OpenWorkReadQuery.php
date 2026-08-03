<?php declare(strict_types=1);

namespace App\Services;

use App\Models\DesignItem;
use App\Models\Task;
use App\Support\Work\OpenWorkItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Toàn bộ open work item (Task + DesignItem) của 1 tenant — chưa lọc theo
 * actor, chưa nhóm theo người. Dùng chung bởi WorkloadPageController
 * (My Work, Workload) và TodayWorkspaceReadService.
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §3.1
 */
class OpenWorkReadQuery
{
    /**
     * @return Collection<int, OpenWorkItem>
     */
    public function collect(string $tenantId): Collection
    {
        $today = Carbon::now()->startOfDay();

        $tasks = Task::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [Task::STATUS_PENDING, Task::STATUS_IN_PROGRESS, Task::STATUS_ON_HOLD])
            ->with('project:id,tenant_id,name')
            ->get([
                'id', 'tenant_id', 'project_id', 'name', 'title', 'status',
                'assigned_to', 'end_date', 'blocked_at', 'blocker_note', 'blocked_by', 'priority',
            ]);

        $designItems = DesignItem::query()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('review_status', [DesignItem::STATUS_APPROVED, DesignItem::STATUS_FINAL])
            ->with('project:id,tenant_id,name')
            ->get([
                'id', 'tenant_id', 'project_id', 'name', 'review_status',
                'assigned_to', 'blocked_at', 'blocker_note', 'blocked_by',
            ]);

        $items = collect();

        foreach ($tasks as $task) {
            $isOverdue = $task->end_date !== null
                && Carbon::parse(substr((string) $task->end_date, 0, 10))->startOfDay()->lt($today);

            $items->push(new OpenWorkItem(
                sourceType: 'task',
                sourceId: (string) $task->id,
                assignedTo: $task->assigned_to !== null ? (string) $task->assigned_to : null,
                kindLabel: 'Công việc',
                name: (string) ($task->name ?? $task->title ?? $task->id),
                projectId: $task->project_id !== null ? (string) $task->project_id : null,
                projectName: $task->project?->name ?? '—',
                endDate: $task->end_date,
                isOverdue: $isOverdue,
                isBlocked: $task->blocked_at !== null,
                blockerNote: $task->blocker_note,
                blockedBy: $task->blocked_by,
                priority: $task->priority,
                status: (string) $task->status,
                url: route('app.tasks.show', $task->id),
            ));
        }

        foreach ($designItems as $designItem) {
            $items->push(new OpenWorkItem(
                sourceType: 'design_item',
                sourceId: (string) $designItem->id,
                assignedTo: $designItem->assigned_to !== null ? (string) $designItem->assigned_to : null,
                kindLabel: 'Hạng mục thiết kế',
                name: (string) $designItem->name,
                projectId: $designItem->project_id !== null ? (string) $designItem->project_id : null,
                projectName: $designItem->project?->name ?? '—',
                endDate: null,
                isOverdue: false,
                isBlocked: $designItem->blocked_at !== null,
                blockerNote: $designItem->blocker_note,
                blockedBy: $designItem->blocked_by,
                priority: null,
                status: (string) $designItem->review_status,
                url: route('operator.design-items.show', $designItem->id),
            ));
        }

        return $items;
    }
}
