<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;

/**
 * Overdue Service
 * 
 * Single source of truth for overdue calculation logic.
 * Centralizes all overdue rules for tasks and projects.
 */
class OverdueService
{
    /**
     * Check if a task is overdue
     * 
     * Rule: Task is overdue if:
     * - end_date < today (start of day)
     * - AND status NOT IN [done, completed, canceled, cancelled]
     * 
     * @param Task $task
     * @return bool
     */
    public function isTaskOverdue(Task $task): bool
    {
        // Task must have an end_date
        if (!$task->end_date) {
            return false;
        }

        // Check if end_date is in the past (before today, start of day)
        $today = now()->startOfDay();
        $endDate = Carbon::parse($task->end_date)->startOfDay();

        if ($endDate->gte($today)) {
            return false;
        }

        // Check if task status is NOT in terminal/completed states
        $status = is_string($task->status) ? $task->status : $task->status->value ?? null;
        
        $nonOverdueStatuses = ['done', 'completed', 'canceled', 'cancelled'];
        
        return !in_array(strtolower($status ?? ''), array_map('strtolower', $nonOverdueStatuses), true);
    }

    /**
     * Check if a project is overdue
     * 
     * Rule: Project is overdue if:
     * - end_date < today (start of day)
     * - AND status IN [active, on_hold]
     * 
     * Note: Planning projects are not considered overdue as they haven't started yet.
     * Completed/cancelled/archived projects are not considered overdue.
     * 
     * @param Project $project
     * @return bool
     */
    public function isProjectOverdue(Project $project): bool
    {
        // Project must have an end_date
        if (!$project->end_date) {
            return false;
        }

        // Check if end_date is in the past (before today, start of day)
        $today = now()->startOfDay();
        $endDate = Carbon::parse($project->end_date)->startOfDay();

        if ($endDate->gte($today)) {
            return false;
        }

        // Check if project status is in active states (not planning, completed, cancelled, archived)
        $status = $project->status ?? '';
        
        $activeStatuses = ['active', 'on_hold'];
        
        return in_array($status, $activeStatuses, true);
    }

    /**
     * Get overdue tasks count for a project
     * 
     * @param string|int $projectId
     * @param string|int|null $tenantId
     * @return int
     */
    public function getOverdueTasksCount(string|int $projectId, string|int|null $tenantId = null): int
    {
        $today = now()->startOfDay();
        
        return Task::where('project_id', $projectId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->whereNotIn('status', ['done', 'completed', 'canceled', 'cancelled'])
            ->count();
    }

    /**
     * Get overdue projects count for a tenant
     * 
     * @param string|int|null $tenantId
     * @return int
     */
    public function getOverdueProjectsCount(string|int|null $tenantId = null): int
    {
        $today = now()->startOfDay();
        
        return Project::when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->whereIn('status', ['active', 'on_hold'])
            ->count();
    }
}

