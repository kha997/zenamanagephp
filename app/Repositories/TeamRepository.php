<?php

namespace App\Repositories;

use App\Models\Team;
use App\Models\User;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Log;

class TeamRepository
{
    protected $model;

    public function __construct(Team $model)
    {
        $this->model = $model;
    }

    /**
     * Get all teams with pagination.
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

        if (isset($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('description', 'like', '%' . $filters['search'] . '%');
            });
        }

        if (isset($filters['leader_id'])) {
            $query->where('leader_id', $filters['leader_id']);
        }

        return $query->with(['leader', 'tenant', 'members', 'projects'])->paginate($perPage);
    }

    /**
     * Get team by ID.
     */
    public function getById(int $id): ?Team
    {
        return $this->model->with(['leader', 'tenant', 'members', 'projects'])->find($id);
    }

    /**
     * Get teams by tenant ID.
     */
    public function getByTenantId(int $tenantId): Collection
    {
        return $this->model->where('tenant_id', $tenantId)
                          ->with(['leader', 'tenant', 'members', 'projects'])
                          ->get();
    }

    /**
     * Get teams by leader ID.
     */
    public function getByLeaderId(int $leaderId): Collection
    {
        return $this->model->where('leader_id', $leaderId)
                          ->with(['leader', 'tenant', 'members', 'projects'])
                          ->get();
    }

    /**
     * Get teams by status.
     */
    public function getByStatus(string $status): Collection
    {
        return $this->model->where('status', $status)
                          ->with(['leader', 'tenant', 'members', 'projects'])
                          ->get();
    }

    /**
     * Create a new team.
     */
    public function create(array $data): Team
    {
        $team = $this->model->create($data);

        // Add members if provided
        if (isset($data['members'])) {
            $team->members()->sync($data['members']);
        }

        Log::info('Team created', [
            'team_id' => $team->id,
            'name' => $team->name,
            'tenant_id' => $team->tenant_id,
            'leader_id' => $team->leader_id
        ]);

        return $team->load(['leader', 'tenant', 'members', 'projects']);
    }

    /**
     * Update team.
     */
    public function update(int $id, array $data): ?Team
    {
        $team = $this->model->find($id);

        if (!$team) {
            return null;
        }

        $team->update($data);

        // Update members if provided
        if (isset($data['members'])) {
            $team->members()->sync($data['members']);
        }

        Log::info('Team updated', [
            'team_id' => $team->id,
            'name' => $team->name,
            'tenant_id' => $team->tenant_id
        ]);

        return $team->load(['leader', 'tenant', 'members', 'projects']);
    }

    /**
     * Delete team.
     */
    public function delete(int $id): bool
    {
        $team = $this->model->find($id);

        if (!$team) {
            return false;
        }

        $team->delete();

        Log::info('Team deleted', [
            'team_id' => $id,
            'name' => $team->name,
            'tenant_id' => $team->tenant_id
        ]);

        return true;
    }

    /**
     * Soft delete team.
     */
    public function softDelete(int $id): bool
    {
        $team = $this->model->find($id);

        if (!$team) {
            return false;
        }

        $team->delete();

        Log::info('Team soft deleted', [
            'team_id' => $id,
            'name' => $team->name,
            'tenant_id' => $team->tenant_id
        ]);

        return true;
    }

    /**
     * Restore soft deleted team.
     */
    public function restore(int $id): bool
    {
        $team = $this->model->withTrashed()->find($id);

        if (!$team) {
            return false;
        }

        $team->restore();

        Log::info('Team restored', [
            'team_id' => $id,
            'name' => $team->name,
            'tenant_id' => $team->tenant_id
        ]);

        return true;
    }

    /**
     * Get active teams.
     */
    public function getActive(): Collection
    {
        return $this->model->where('status', 'active')
                          ->with(['leader', 'tenant', 'members', 'projects'])
                          ->get();
    }

    /**
     * Get inactive teams.
     */
    public function getInactive(): Collection
    {
        return $this->model->where('status', 'inactive')
                          ->with(['leader', 'tenant', 'members', 'projects'])
                          ->get();
    }

    /**
     * Add member to team.
     */
    public function addMember(int $teamId, int $userId, string $role = 'member'): bool
    {
        $team = $this->model->find($teamId);

        if (!$team) {
            return false;
        }

        $team->members()->syncWithoutDetaching([
            $userId => ['role' => $role, 'joined_at' => now()]
        ]);

        Log::info('Member added to team', [
            'team_id' => $teamId,
            'user_id' => $userId,
            'role' => $role
        ]);

        return true;
    }

    /**
     * Remove member from team.
     */
    public function removeMember(int $teamId, int $userId): bool
    {
        $team = $this->model->find($teamId);

        if (!$team) {
            return false;
        }

        $team->members()->detach($userId);

        Log::info('Member removed from team', [
            'team_id' => $teamId,
            'user_id' => $userId
        ]);

        return true;
    }

    /**
     * Update member role.
     */
    public function updateMemberRole(int $teamId, int $userId, string $role): bool
    {
        $team = $this->model->find($teamId);

        if (!$team) {
            return false;
        }

        $team->members()->updateExistingPivot($userId, [
            'role' => $role,
            'updated_at' => now()
        ]);

        Log::info('Member role updated', [
            'team_id' => $teamId,
            'user_id' => $userId,
            'role' => $role
        ]);

        return true;
    }

    /**
     * Get team members.
     */
    public function getMembers(int $teamId): Collection
    {
        $team = $this->model->with('members')->find($teamId);

        if (!$team) {
            return collect();
        }

        return $team->members;
    }

    /**
     * Get team projects.
     */
    public function getProjects(int $teamId): Collection
    {
        $team = $this->model->with('projects')->find($teamId);

        if (!$team) {
            return collect();
        }

        return $team->projects;
    }

    /**
     * Assign team to project.
     */
    public function assignToProject(int $teamId, int $projectId, string $role = 'member'): bool
    {
        $team = $this->model->find($teamId);

        if (!$team) {
            return false;
        }

        $team->projects()->syncWithoutDetaching([
            $projectId => ['role' => $role, 'assigned_at' => now()]
        ]);

        Log::info('Team assigned to project', [
            'team_id' => $teamId,
            'project_id' => $projectId,
            'role' => $role
        ]);

        return true;
    }

    /**
     * Remove team from project.
     */
    public function removeFromProject(int $teamId, int $projectId): bool
    {
        $team = $this->model->find($teamId);

        if (!$team) {
            return false;
        }

        $team->projects()->detach($projectId);

        Log::info('Team removed from project', [
            'team_id' => $teamId,
            'project_id' => $projectId
        ]);

        return true;
    }

    /**
     * Update team status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        $team = $this->model->find($id);

        if (!$team) {
            return false;
        }

        $team->update([
            'status' => $status,
            'status_updated_at' => now()
        ]);

        Log::info('Team status updated', [
            'team_id' => $id,
            'status' => $status
        ]);

        return true;
    }

    /**
     * Get team statistics.
     */
    public function getStatistics(int $tenantId = null): array
    {
        $query = $this->model->query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        return [
            'total_teams' => $query->count(),
            'active_teams' => $query->where('status', 'active')->count(),
            'inactive_teams' => $query->where('status', 'inactive')->count(),
            'teams_with_projects' => $query->whereHas('projects')->count(),
            'teams_without_projects' => $query->whereDoesntHave('projects')->count(),
            'average_members_per_team' => $query->withCount('members')->avg('members_count'),
            'largest_team_size' => $query->withCount('members')->max('members_count'),
            'smallest_team_size' => $query->withCount('members')->min('members_count')
        ];
    }

    /**
     * Search teams.
     */
    public function search(string $term, int $limit = 10): Collection
    {
        return $this->model->where(function ($q) use ($term) {
            $q->where('name', 'like', '%' . $term . '%')
              ->orWhere('description', 'like', '%' . $term . '%');
        })->with(['leader', 'tenant', 'members', 'projects'])
          ->limit($limit)
          ->get();
    }

    /**
     * Get teams by multiple IDs.
     */
    public function getByIds(array $ids): Collection
    {
        return $this->model->whereIn('id', $ids)
                          ->with(['leader', 'tenant', 'members', 'projects'])
                          ->get();
    }

    /**
     * Bulk update teams.
     */
    public function bulkUpdate(array $ids, array $data): int
    {
        $updated = $this->model->whereIn('id', $ids)->update($data);

        Log::info('Teams bulk updated', [
            'count' => $updated,
            'ids' => $ids
        ]);

        return $updated;
    }

    /**
     * Bulk delete teams.
     */
    public function bulkDelete(array $ids): int
    {
        $deleted = $this->model->whereIn('id', $ids)->delete();

        Log::info('Teams bulk deleted', [
            'count' => $deleted,
            'ids' => $ids
        ]);

        return $deleted;
    }

    /**
     * Get team performance.
     */
    public function getPerformance(int $id): array
    {
        $team = $this->model->with(['projects.tasks'])->find($id);

        if (!$team) {
            return [];
        }

        $totalTasks = 0;
        $completedTasks = 0;
        $overdueTasks = 0;

        foreach ($team->projects as $project) {
            $totalTasks += $project->tasks->count();
            $completedTasks += $project->tasks->where('status', 'completed')->count();
            $overdueTasks += $project->tasks->where('due_date', '<', now())
                                          ->where('status', '!=', 'completed')
                                          ->count();
        }

        $completionRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;

        return [
            'total_tasks' => $totalTasks,
            'completed_tasks' => $completedTasks,
            'overdue_tasks' => $overdueTasks,
            'completion_rate' => $completionRate,
            'active_projects' => $team->projects->where('status', 'active')->count(),
            'completed_projects' => $team->projects->where('status', 'completed')->count()
        ];
    }

    /**
     * Get team timeline.
     */
    public function getTimeline(int $id): array
    {
        $team = $this->model->with(['projects'])->find($id);

        if (!$team) {
            return [];
        }

        $timeline = [];

        // Add team creation
        $timeline[] = [
            'type' => 'team_created',
            'date' => $team->created_at,
            'title' => 'Team Created',
            'description' => 'Team ' . $team->name . ' was created'
        ];

        // Add project assignments
        foreach ($team->projects as $project) {
            $timeline[] = [
                'type' => 'project_assigned',
                'date' => $project->pivot->assigned_at ?? $project->created_at,
                'title' => 'Project Assigned',
                'description' => 'Assigned to project ' . $project->name
            ];
        }

        // Add status changes
        if ($team->status_updated_at) {
            $timeline[] = [
                'type' => 'status_changed',
                'date' => $team->status_updated_at,
                'title' => 'Status Changed',
                'description' => 'Status changed to ' . $team->status
            ];
        }

        // Sort by date
        usort($timeline, function ($a, $b) {
            return strtotime($a['date']) - strtotime($b['date']);
        });

        return $timeline;
    }

    /**
     * Get teams with members count.
     */
    public function getWithMembersCount(): Collection
    {
        return $this->model->withCount('members')
                          ->with(['leader', 'tenant', 'projects'])
                          ->get();
    }

    /**
     * Get teams with projects count.
     */
    public function getWithProjectsCount(): Collection
    {
        return $this->model->withCount('projects')
                          ->with(['leader', 'tenant', 'members'])
                          ->get();
    }

    /**
     * Get user teams.
     */
    public function getUserTeams(int $userId): Collection
    {
        return $this->model->whereHas('members', function ($q) use ($userId) {
            $q->where('user_id', $userId);
        })->with(['leader', 'tenant', 'members', 'projects'])->get();
    }

    /**
     * Check if user is team member.
     */
    public function isMember(int $teamId, int $userId): bool
    {
        $team = $this->model->find($teamId);

        if (!$team) {
            return false;
        }

        return $team->members()->where('user_id', $userId)->exists();
    }

    /**
     * Check if user is team leader.
     */
    public function isLeader(int $teamId, int $userId): bool
    {
        $team = $this->model->find($teamId);

        if (!$team) {
            return false;
        }

        return $team->leader_id === $userId;
    }

    /**
     * Get team member role.
     */
    public function getMemberRole(int $teamId, int $userId): ?string
    {
        $team = $this->model->find($teamId);

        if (!$team) {
            return null;
        }

        $member = $team->members()->where('user_id', $userId)->first();

        return $member ? $member->pivot->role : null;
    }
}
