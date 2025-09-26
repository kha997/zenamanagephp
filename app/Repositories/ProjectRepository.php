<?php

namespace App\Repositories;

use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class ProjectRepository
{
    protected $model;

    public function __construct(Project $model)
    {
        $this->model = $model;
    }

    /**
     * Get all projects with pagination.
     */
    public function getAll(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->model->query();

        // Apply filters
        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['priority'])) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['manager_id'])) {
            $query->where('manager_id', $filters['manager_id']);
        }

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['start_date'])) {
            $query->where('start_date', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('end_date', '<=', $filters['end_date']);
        }

        return $query->with(['manager', 'tenant', 'teams', 'tasks'])->paginate($perPage);
    }

    /**
     * Get project by ID.
     */
    public function getById(int $id): ?Project
    {
        return $this->model->with(['manager', 'tenant', 'teams', 'tasks'])->find($id);
    }

    /**
     * Get projects by tenant ID.
     */
    public function getByTenantId(int $tenantId): Collection
    {
        return $this->model->where('tenant_id', $tenantId)
                          ->with(['manager', 'tenant', 'teams', 'tasks'])
                          ->get();
    }

    /**
     * Get projects by manager ID.
     */
    public function getByManagerId(int $managerId): Collection
    {
        return $this->model->where('manager_id', $managerId)
                          ->with(['manager', 'tenant', 'teams', 'tasks'])
                          ->get();
    }

    /**
     * Get projects by status.
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)
                          ->with(['manager', 'tenant', 'teams', 'tasks'])
                          ->get();
    }

    /**
     * Create a new project.
     */
    public function create(array $data): Project
    {
        $project = $this->model->create($data);

        // Assign teams if provided
        if (isset($data['teams'])) {
            $project->teams()->sync($data['teams']);
        }

        Log::info('Project created', [
            'project_id' => $project->id,
            'name' => $project->name,
            'tenant_id' => $project->tenant_id,
            'manager_id' => $project->manager_id
        ]);

        return $project->load(['manager', 'tenant', 'teams', 'tasks']);
    }

    /**
     * Update project.
     */
    public function update(int $id, array $data): ?Project
    {
        $project = $this->model->find($id);

        if (!$project) {
            return null;
        }

        $project->update($data);

        // Update teams if provided
        if (isset($data['teams'])) {
            $project->teams()->sync($data['teams']);
        }

        Log::info('Project updated', [
            'project_id' => $project->id,
            'name' => $project->name,
            'tenant_id' => $project->tenant_id
        ]);

        return $project->load(['manager', 'tenant', 'teams', 'tasks']);
    }

    /**
     * Delete project.
     */
    public function delete(int $id): bool
    {
        $project = $this->model->find($id);

        if (!$project) {
            return false;
        }

        $project->delete();

        Log::info('Project deleted', [
            'project_id' => $id,
            'name' => $project->name,
            'tenant_id' => $project->tenant_id
        ]);

        return true;
    }

    /**
     * Soft delete project.
     */
    public function softDelete(int $id): bool
    {
        $project = $this->model->find($id);

        if (!$project) {
            return false;
        }

        $project->delete();

        Log::info('Project soft deleted', [
            'project_id' => $id,
            'name' => $project->name,
            'tenant_id' => $project->tenant_id
        ]);

        return true;
    }

    /**
     * Restore soft deleted project.
     */
    public function restore(int $id): bool
    {
        $project = $this->model->withTrashed()->find($id);

        if (!$project) {
            return false;
        }

        $project->restore();

        Log::info('Project restored', [
            'project_id' => $id,
            'name' => $project->name,
            'tenant_id' => $project->tenant_id
        ]);

        return true;
    }

    /**
     * Get active projects.
     */
    public function getActive(): Collection
    {
        return $this->model->where('status', 'active')
                          ->with(['manager', 'tenant', 'teams', 'tasks'])
                          ->get();
    }

    /**
     * Get completed projects.
     */
    public function getCompleted(): Collection
    {
        return $this->model->where('status', 'completed')
                          ->with(['manager', 'tenant', 'teams', 'tasks'])
                          ->get();
    }

    /**
     * Get overdue projects.
     */
    public function getOverdue(): Collection
    {
        return $this->model->where('end_date', '<', now())
                          ->where('status', '!=', 'completed')
                          ->with(['manager', 'tenant', 'teams', 'tasks'])
                          ->get();
    }

    /**
     * Get projects starting soon.
     */
    public function getStartingSoon(int $days = 7): Collection
    {
        $startDate = now()->addDays($days);

        return $this->model->where('start_date', '<=', $startDate)
                          ->where('start_date', '>=', now())
                          ->where('status', 'pending')
                          ->with(['manager', 'tenant', 'teams', 'tasks'])
                          ->get();
    }

    /**
     * Get projects ending soon.
     */
    public function getEndingSoon(int $days = 7): Collection
    {
        $endDate = now()->addDays($days);

        return $this->model->where('end_date', '<=', $endDate)
                          ->where('end_date', '>=', now())
                          ->where('status', '!=', 'completed')
                          ->with(['manager', 'tenant', 'teams', 'tasks'])
                          ->get();
    }

    /**
     * Update project status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $project = $this->model->find($id);

        if (!$project) {
            return false;
        }

        $project->update([
            'status' => $status,
            'status_updated_at' => now()
        ]);

        Log::info('Project status updated', [
            'project_id' => $id,
            'status' => $status
        ]);

        return true;
    }

    /**
     * Assign team to project.
     */
    public function assignTeam(int $projectId, int $teamId, string $role = 'member'): bool
    {
        $project = $this->model->find($projectId);

        if (!$project) {
            return false;
        }

        $project->teams()->syncWithoutDetaching([
            $teamId => ['role' => $role, 'assigned_at' => now()]
        ]);

        Log::info('Team assigned to project', [
            'project_id' => $projectId,
            'team_id' => $teamId,
            'role' => $role
        ]);

        return true;
    }

    /**
     * Remove team from project.
     */
    public function removeTeam(int $projectId, int $teamId): bool
    {
        $project = $this->model->find($projectId);

        if (!$project) {
            return false;
        }

        $project->teams()->detach($teamId);

        Log::info('Team removed from project', [
            'project_id' => $projectId,
            'team_id' => $teamId
        ]);

        return true;
    }

    /**
     * Get project statistics.
     */
    public function getStatistics(int $tenantId = null): array
    {
        $query = $this->model->query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return [
            'total_projects' => $query->count(),
            'active_projects' => $query->where('status', 'active')->count(),
            'completed_projects' => $query->where('status', 'completed')->count(),
            'pending_projects' => $query->where('status', 'pending')->count(),
            'overdue_projects' => $query->where('end_date', '<', now())
                                      ->where('status', '!=', 'completed')
                                      ->count(),
            'high_priority_projects' => $query->where('priority', 'high')->count(),
            'medium_priority_projects' => $query->where('priority', 'medium')->count(),
            'low_priority_projects' => $query->where('priority', 'low')->count()
        ];
    }

    /**
     * Search projects.
     */
    public function search(string $term, int $limit = 10): Collection
    {
        return $this->model->where(function ($q) use ($term) {
            $q->where('name', 'like', '%' . $term . '%')
              ->orWhere('description', 'like', '%' . $term . '%');
        })->with(['manager', 'tenant', 'teams', 'tasks'])
          ->limit($limit)
          ->get();
    }

    /**
     * Get projects by multiple IDs.
     */
    public function getByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)
                          ->with(['manager', 'tenant', 'teams', 'tasks'])
                          ->get();
    }

    /**
     * Bulk update projects.
     */
    public function bulkUpdate(array $ids, array $data): int
    {
        $updated = $this->model->whereIn('id', $ids)->update($data);

        Log::info('Projects bulk updated', [
            'count' => $updated,
            'ids' => $ids
        ]);

        return $updated;
    }

    /**
     * Bulk delete projects.
     */
    public function bulkDelete(array $ids): int
    {
        $deleted = $this->model->whereIn('id', $ids)->delete();

        Log::info('Projects bulk deleted', [
            'count' => $deleted,
            'ids' => $ids
        ]);

        return $deleted;
    }

    /**
     * Get project progress.
     */
    public function getProgress(int $id): array
    {
        $project = $this->model->with('tasks')->find($id);

        if (!$project) {
            return [];
        }

        $totalTasks = $project->tasks->count();
        $completedTasks = $project->tasks->where('status', 'completed')->count();
        $progress = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'progress_percentage' => $progress,
            'estimated_completion' => $this->calculateEstimatedCompletion($project)
        ];
    }

    /**
     * Calculate estimated completion date.
     */
    protected function calculateEstimatedCompletion(Project $project): ?string
    {
        if ($project->status === 'completed') {
            return $project->completed_at?->toDateString();
        }

        $totalTasks = $project->tasks->count();
        $completedTasks = $project->tasks->where('status', 'completed')->count();

        if ($totalTasks === 0) {
            return $project->end_date?->toDateString();
        }

        $progress = $completedTasks / $totalTasks;
        $daysElapsed = now()->diffInDays($project->start_date);
        $estimatedTotalDays = $daysElapsed / $progress;
        $remainingDays = $estimatedTotalDays - $daysElapsed;

        return now()->addDays($remainingDays)->toDateString();
    }

    /**
     * Get project timeline.
     */
    public function getTimeline(int $id): array
    {
        $project = $this->model->with(['tasks' => function ($q) {
            $q->orderBy('due_date');
        }])->find($id);

        if (!$project) {
            return [];
        }

        $timeline = [];

        // Add project milestones
        $timeline[] = [
            'type' => 'project_start',
            'date' => $project->start_date,
            'title' => 'Project Started',
            'description' => 'Project ' . $project->name . ' started'
        ];

        // Add task milestones
        foreach ($project->tasks as $task) {
            $timeline[] = [
                'type' => 'task',
                'date' => $task->due_date,
                'title' => $task->name,
                'description' => $task->description,
                'status' => $task->status,
                'priority' => $task->priority
            ];
        }

        // Add project end
        if ($project->end_date) {
            $timeline[] = [
                'type' => 'project_end',
                'date' => $project->end_date,
                'title' => 'Project Deadline',
                'description' => 'Project ' . $project->name . ' deadline'
            ];
        }

        // Sort by date
        usort($timeline, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $timeline;
    }
}