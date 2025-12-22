<?php declare(strict_types=1);

namespace App\Services\RealData;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Real Activity Service
 * 
 * Provides real activity data instead of mock data
 * Replaces mock activity data in controllers
 */
class RealActivityService
{
    use ServiceBaseTrait;

    /**
     * Get recent activities for dashboard
     */
    public function getRecentActivities(string|int|null $tenantId = null, int $limit = 10): array
    {
        $this->validateTenantAccess($tenantId);
        
        $activities = [];
        
        // Get recent project activities
        $projectActivities = $this->getProjectActivities($tenantId, $limit);
        $activities = array_merge($activities, $projectActivities);
        
        // Get recent task activities
        $taskActivities = $this->getTaskActivities($tenantId, $limit);
        $activities = array_merge($activities, $taskActivities);
        
        // Get recent user activities
        $userActivities = $this->getUserActivities($tenantId, $limit);
        $activities = array_merge($activities, $userActivities);
        
        // Sort by timestamp and limit
        usort($activities, fn($a, $b) => strtotime($b['timestamp'] ?? '1970-01-01') - strtotime($a['timestamp'] ?? '1970-01-01'));
        
        return array_slice($activities, 0, $limit);
    }

    /**
     * Get project-related activities
     */
    protected function getProjectActivities(string|int|null $tenantId, int $limit): array
    {
        $query = Project::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['owner'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit);

        $projects = $query->get();
        
        $activities = [];
        foreach ($projects as $project) {
            $activities[] = [
                'id' => 'project_' . $project->id,
                'type' => 'project',
                'action' => $project->wasRecentlyCreated ? 'created' : 'updated',
                'description' => $project->wasRecentlyCreated 
                    ? "Project '{$project->name}' was created"
                    : "Project '{$project->name}' was updated",
                'timestamp' => $project->updated_at->toISOString(),
                'user' => [
                    'id' => $project->owner?->id ?? 'unknown',
                    'name' => $project->owner?->name ?? 'Unknown User'
                ],
                'metadata' => [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'status' => $project->status,
                    'progress' => $project->progress
                ]
            ];
        }
        
        return $activities;
    }

    /**
     * Get task-related activities
     */
    protected function getTaskActivities(string|int|null $tenantId, int $limit): array
    {
        // Assuming tasks are related to projects which have tenant_id
        $query = Task::query()
            ->whereHas('project', function($q) use ($tenantId) {
                $q->when($tenantId, fn($subQ) => $subQ->where('tenant_id', $tenantId));
            })
            ->with(['project', 'assignee'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit);

        $tasks = $query->get();
        
        $activities = [];
        foreach ($tasks as $task) {
            $activities[] = [
                'id' => 'task_' . $task->id,
                'type' => 'task',
                'action' => $task->wasRecentlyCreated ? 'created' : 'updated',
                'description' => $task->wasRecentlyCreated 
                    ? "Task '{$task->name}' was created in project '{$task->project->name}'"
                    : "Task '{$task->name}' was updated in project '{$task->project->name}'",
                'timestamp' => $task->updated_at->toISOString(),
                'user' => [
                    'id' => $task->assignee->id ?? $task->project->owner->id,
                    'name' => $task->assignee->name ?? $task->project->owner->name
                ],
                'metadata' => [
                    'task_id' => $task->id,
                    'task_name' => $task->name,
                    'project_id' => $task->project->id,
                    'project_name' => $task->project->name,
                    'status' => $task->status,
                    'priority' => $task->priority
                ]
            ];
        }
        
        return $activities;
    }

    /**
     * Get user-related activities
     */
    protected function getUserActivities(string|int|null $tenantId, int $limit): array
    {
        $query = User::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('updated_at', 'desc')
            ->limit($limit);

        $users = $query->get();
        
        $activities = [];
        foreach ($users as $user) {
            $activities[] = [
                'id' => 'user_' . $user->id,
                'type' => 'user',
                'action' => $user->wasRecentlyCreated ? 'created' : 'updated',
                'description' => $user->wasRecentlyCreated 
                    ? "User '{$user->name}' was created"
                    : "User '{$user->name}' was updated",
                'timestamp' => $user->updated_at->toISOString(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name
                ],
                'metadata' => [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'is_active' => $user->is_active
                ]
            ];
        }
        
        return $activities;
    }

    /**
     * Get activities by type
     */
    public function getActivitiesByType(string $type, string|int|null $tenantId = null, int $limit = 10): array
    {
        $this->validateTenantAccess($tenantId);
        
        return match($type) {
            'project' => $this->getProjectActivities($tenantId, $limit),
            'task' => $this->getTaskActivities($tenantId, $limit),
            'user' => $this->getUserActivities($tenantId, $limit),
            default => []
        };
    }

    /**
     * Get activities for specific user
     */
    public function getUserSpecificActivities(int $userId, string|int|null $tenantId = null, int $limit = 10): array
    {
        $this->validateTenantAccess($tenantId);
        
        $activities = [];
        
        // Get projects owned by user
        $userProjects = Project::where('owner_id', $userId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
            
        foreach ($userProjects as $project) {
            $activities[] = [
                'id' => 'user_project_' . $project->id,
                'type' => 'project',
                'action' => 'owned',
                'description' => "You own project '{$project->name}'",
                'timestamp' => $project->updated_at->toISOString(),
                'user' => [
                    'id' => $userId,
                    'name' => 'You'
                ],
                'metadata' => [
                    'project_id' => $project->id,
                    'project_name' => $project->name,
                    'status' => $project->status,
                    'progress' => $project->progress
                ]
            ];
        }
        
        // Get tasks assigned to user
        $userTasks = Task::where('assignee_id', $userId)
            ->whereHas('project', function($q) use ($tenantId) {
                $q->when($tenantId, fn($subQ) => $subQ->where('tenant_id', $tenantId));
            })
            ->with(['project'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
            
        foreach ($userTasks as $task) {
            $activities[] = [
                'id' => 'user_task_' . $task->id,
                'type' => 'task',
                'action' => 'assigned',
                'description' => "Task '{$task->name}' assigned to you in project '{$task->project->name}'",
                'timestamp' => $task->updated_at->toISOString(),
                'user' => [
                    'id' => $userId,
                    'name' => 'You'
                ],
                'metadata' => [
                    'task_id' => $task->id,
                    'task_name' => $task->name,
                    'project_id' => $task->project->id,
                    'project_name' => $task->project->name,
                    'status' => $task->status,
                    'priority' => $task->priority
                ]
            ];
        }
        
        // Sort by timestamp and limit
        usort($activities, fn($a, $b) => strtotime($b['timestamp']) - strtotime($a['timestamp']));
        
        return array_slice($activities, 0, $limit);
    }

    /**
     * Get activity statistics
     */
    public function getActivityStats(?int $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        $stats = [
            'total_activities' => 0,
            'activities_by_type' => [],
            'activities_by_user' => [],
            'recent_activity_count' => 0
        ];
        
        // Count project activities
        $projectCount = Project::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->count();
        $stats['activities_by_type']['project'] = $projectCount;
        
        // Count task activities
        $taskCount = Task::query()
            ->whereHas('project', function($q) use ($tenantId) {
                $q->when($tenantId, fn($subQ) => $subQ->where('tenant_id', $tenantId));
            })
            ->count();
        $stats['activities_by_type']['task'] = $taskCount;
        
        // Count user activities
        $userCount = User::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->count();
        $stats['activities_by_type']['user'] = $userCount;
        
        $stats['total_activities'] = $projectCount + $taskCount + $userCount;
        
        // Recent activities (last 24 hours)
        $recentCount = Project::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where('updated_at', '>=', now()->subDay())
            ->count();
        $stats['recent_activity_count'] = $recentCount;
        
        return $stats;
    }
}
