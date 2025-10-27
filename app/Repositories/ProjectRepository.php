<?php

namespace App\Repositories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class ProjectRepository
{
    public function create(array $data): Project
    {
        return Project::create($data);
    }
    
    public function findById(string $id, string $tenantId): ?Project
    {
        return Project::where('id', $id)
                     ->where('tenant_id', $tenantId)
                     ->first();
    }
    
    public function getById(string $id, string $tenantId): ?Project
    {
        return $this->findById($id, $tenantId);
    }
    
    public function update(string $id, array $data, string $tenantId): Project
    {
        $project = Project::where('id', $id)
                         ->where('tenant_id', $tenantId)
                         ->firstOrFail();
        $project->update($data);
        return $project;
    }
    
    public function delete(string $id, string $tenantId): bool
    {
        $project = Project::where('id', $id)
                         ->where('tenant_id', $tenantId)
                         ->firstOrFail();
        return $project->delete();
    }
    
    public function softDelete(string $id, string $tenantId): bool
    {
        $project = Project::where('id', $id)
                         ->where('tenant_id', $tenantId)
                         ->firstOrFail();
        return $project->delete();
    }
    
    public function restore(string $id, string $tenantId): bool
    {
        $project = Project::withTrashed()
                         ->where('id', $id)
                         ->where('tenant_id', $tenantId)
                         ->firstOrFail();
        return $project->restore();
    }
    
    public function getList(array $filters = [], string $userId = null, string $tenantId = null): Collection
    {
        $query = Project::query();
        
        // MANDATORY: Every query must filter by tenant_id
        if (!$tenantId) {
            throw new \InvalidArgumentException('tenant_id is required for all queries');
        }
        
        $query->where('tenant_id', $tenantId);
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['manager_id'])) {
            $query->where('pm_id', $filters['manager_id']);
        }
        
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('code', 'like', '%' . $filters['search'] . '%');
            });
        }
        
        return $query->get();
    }
    
    public function getAll(array $filters = [], int $perPage = 15): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $query = Project::query();
        
        // MANDATORY: Every query must filter by tenant_id
        if (!isset($filters['tenant_id']) || !$filters['tenant_id']) {
            throw new \InvalidArgumentException('tenant_id is required for all queries');
        }
        
        $query->where('tenant_id', $filters['tenant_id']);
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['manager_id'])) {
            $query->where('pm_id', $filters['manager_id']);
        }
        
        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('code', 'like', '%' . $filters['search'] . '%');
            });
        }
        
        return $query->paginate($perPage);
    }
    
    public function getByTenantId(string $tenantId): Collection
    {
        return Project::where('tenant_id', $tenantId)->get();
    }
    
    public function getByManagerId(string $managerId, string $tenantId): Collection
    {
        return Project::where('pm_id', $managerId)
                     ->where('tenant_id', $tenantId)
                     ->get();
    }
    
    public function getByStatus(string $status, string $tenantId): Collection
    {
        return Project::where('status', $status)
                     ->where('tenant_id', $tenantId)
                     ->get();
    }
    
    public function search(string $searchTerm, string $tenantId): Collection
    {
        return Project::where('tenant_id', $tenantId)
                     ->where(function ($query) use ($searchTerm) {
                         $query->where('name', 'like', '%' . $searchTerm . '%')
                               ->orWhere('description', 'like', '%' . $searchTerm . '%')
                               ->orWhere('code', 'like', '%' . $searchTerm . '%');
                     })
                     ->get();
    }
    
    public function getActive(string $tenantId): Collection
    {
        return Project::where('tenant_id', $tenantId)
                     ->where('status', 'active')
                     ->get();
    }
    
    public function getCompleted(string $tenantId): Collection
    {
        return Project::where('tenant_id', $tenantId)
                     ->where('status', 'completed')
                     ->get();
    }
    
    public function getOverdue(string $tenantId): Collection
    {
        return Project::where('tenant_id', $tenantId)
                     ->where('end_date', '<', now())
                     ->whereIn('status', ['active', 'on_hold'])
                     ->get();
    }
    
    public function getStartingSoon(string $tenantId, int $days = 7): Collection
    {
        return Project::where('tenant_id', $tenantId)
                     ->where('start_date', '<=', now()->addDays($days))
                     ->where('start_date', '>=', now())
                     ->whereIn('status', ['planning', 'active'])
                     ->get();
    }
    
    public function getEndingSoon(string $tenantId, int $days = 7): Collection
    {
        return Project::where('tenant_id', $tenantId)
                     ->where('end_date', '<=', now()->addDays($days))
                     ->where('end_date', '>=', now())
                     ->whereIn('status', ['active', 'on_hold'])
                     ->get();
    }
    
    public function updateStatus(string $id, string $status, string $tenantId): Project
    {
        $project = Project::where('id', $id)
                         ->where('tenant_id', $tenantId)
                         ->firstOrFail();
        $project->update(['status' => $status]);
        return $project;
    }
    
    public function assignTeam(string $projectId, string $teamId, string $tenantId): bool
    {
        $project = Project::where('id', $projectId)
                         ->where('tenant_id', $tenantId)
                         ->firstOrFail();
        
        // Use pivot table relationship
        $project->teams()->syncWithoutDetaching([$teamId => ['role' => 'member']]);
        return true;
    }
    
    public function removeTeam(string $projectId, string $tenantId): bool
    {
        $project = Project::where('id', $projectId)
                         ->where('tenant_id', $tenantId)
                         ->firstOrFail();
        
        // Remove all teams from project
        $project->teams()->detach();
        return true;
    }
    
    public function getStatistics(string $tenantId): array
    {
        $stats = [
            'total' => Project::where('tenant_id', $tenantId)->count(),
            'by_status' => Project::where('tenant_id', $tenantId)
                               ->selectRaw('status, count(*) as count')
                               ->groupBy('status')
                               ->pluck('count', 'status')
                               ->toArray(),
            'by_priority' => Project::where('tenant_id', $tenantId)
                                 ->selectRaw('priority, count(*) as count')
                                 ->groupBy('priority')
                                 ->pluck('count', 'priority')
                                 ->toArray(),
            'average_progress' => Project::where('tenant_id', $tenantId)
                                       ->avg('progress_pct') ?? 0,
            'total_budget' => Project::where('tenant_id', $tenantId)
                                   ->sum('budget_total') ?? 0,
            'total_spent' => Project::where('tenant_id', $tenantId)
                                  ->sum('budget_actual') ?? 0,
            'created_this_month' => Project::where('tenant_id', $tenantId)
                                         ->whereMonth('created_at', now()->month)
                                         ->whereYear('created_at', now()->year)
                                         ->count(),
            'overdue' => $this->getOverdue($tenantId)->count(),
        ];
        
        return $stats;
    }
    
    public function getByIds(array $ids, string $tenantId): Collection
    {
        return Project::whereIn('id', $ids)
                     ->where('tenant_id', $tenantId)
                     ->get();
    }
    
    public function bulkUpdate(array $ids, array $data, string $tenantId): int
    {
        return Project::whereIn('id', $ids)
                     ->where('tenant_id', $tenantId)
                     ->update($data);
    }
    
    public function bulkDelete(array $ids, string $tenantId): int
    {
        return Project::whereIn('id', $ids)
                     ->where('tenant_id', $tenantId)
                     ->delete();
    }
    
    public function getProgress(string $id, string $tenantId): array
    {
        $project = Project::where('id', $id)
                         ->where('tenant_id', $tenantId)
                         ->firstOrFail();
        
        return [
            'project_id' => $id,
            'progress_pct' => $project->progress_pct,
            'completion_percentage' => $project->completion_percentage,
            'budget_spent' => $project->budget_actual,
            'budget_total' => $project->budget_total,
            'budget_variance' => $project->budget_total - $project->budget_actual,
            'hours_estimated' => $project->estimated_hours,
            'hours_actual' => $project->actual_hours,
            'status' => $project->status,
            'last_activity_at' => $project->last_activity_at,
        ];
    }
    
    public function getTimeline(string $id, string $tenantId): array
    {
        $project = Project::where('id', $id)
                         ->where('tenant_id', $tenantId)
                         ->firstOrFail();
        
        $timelineItems = collect();
        
        // Add project milestones
        if ($project->start_date) {
            $timelineItems->push([
                'id' => 'project_start_' . $project->id,
                'title' => 'Project Start',
                'date' => $project->start_date,
                'type' => 'milestone',
                'status' => $project->start_date <= now() ? 'completed' : 'pending',
                'description' => 'Project start date'
            ]);
        }
        
        if ($project->end_date) {
            $timelineItems->push([
                'id' => 'project_end_' . $project->id,
                'title' => 'Project End',
                'date' => $project->end_date,
                'type' => 'milestone',
                'status' => $project->end_date <= now() ? ($project->status === 'completed' ? 'completed' : 'overdue') : 'pending',
                'description' => 'Project end date'
            ]);
        }
        
        // Add project creation event
        $timelineItems->push([
            'id' => 'project_created_' . $project->id,
            'title' => 'Project Created',
            'date' => $project->created_at,
            'type' => 'event',
            'status' => 'completed',
            'description' => 'Project was created'
        ]);
        
        // Sort timeline items by date
        $timelineItems = $timelineItems->sortBy('date')->values();
        
        return [
            'project_id' => $id,
            'project_name' => $project->name,
            'timeline' => $timelineItems->toArray()
        ];
    }
}