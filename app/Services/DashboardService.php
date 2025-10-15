<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\Notification;
use App\Models\ProjectActivity;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

/**
 * Dashboard Service
 * 
 * Handles dashboard data aggregation and business logic
 */
class DashboardService
{
    protected int $cacheTtl = 300; // 5 minutes

    /**
     * Get comprehensive dashboard data
     */
    public function getDashboardData(string $userId, string $tenantId): array
    {
        $cacheKey = "dashboard_data_{$userId}_{$tenantId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($userId, $tenantId) {
            return [
                'user' => $this->getUserInfo($userId),
                'stats' => $this->getStats($tenantId),
                'recent_activities' => $this->getRecentActivities($tenantId),
                'notifications' => $this->getNotifications($userId, $tenantId),
                'recent_projects' => $this->getRecentProjects($tenantId),
                'recent_tasks' => $this->getRecentTasks($tenantId),
                'metrics' => $this->getMetrics($tenantId)
            ];
        });
    }

    /**
     * Get dashboard statistics
     */
    public function getStats(string $tenantId): array
    {
        $cacheKey = "dashboard_stats_{$tenantId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($tenantId) {
            return [
                'total_projects' => Project::where('tenant_id', $tenantId)->count(),
                'active_projects' => Project::where('tenant_id', $tenantId)
                    ->where('status', 'active')->count(),
                'completed_projects' => Project::where('tenant_id', $tenantId)
                    ->where('status', 'completed')->count(),
                'total_tasks' => Task::where('tenant_id', $tenantId)->count(),
                'completed_tasks' => Task::where('tenant_id', $tenantId)
                    ->where('status', 'completed')->count(),
                'pending_tasks' => Task::where('tenant_id', $tenantId)
                    ->where('status', 'pending')->count(),
                'overdue_tasks' => 0, // Skip due_date query as column doesn't exist
                'team_members' => User::where('tenant_id', $tenantId)->count()
            ];
        });
    }

    /**
     * Get recent projects
     */
    public function getRecentProjects(string $tenantId, int $limit = 5): array
    {
        return Project::where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'code' => $project->code,
                    'status' => $project->status,
                    'progress_percent' => $project->progress_percent ?? 0,
                    'start_date' => $project->start_date?->toISOString(),
                    'end_date' => $project->end_date?->toISOString(),
                    'created_at' => $project->created_at->toISOString()
                ];
            })
            ->toArray();
    }

    /**
     * Get recent tasks
     */
    public function getRecentTasks(string $tenantId, int $limit = 10): array
    {
        return Task::where('tenant_id', $tenantId)
            ->with(['project:id,name', 'user:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'name' => $task->name,
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'progress_percent' => $task->progress_percent ?? 0,
                    'due_date' => $task->due_date?->toISOString(),
                    'project' => $task->project ? [
                        'id' => $task->project->id,
                        'name' => $task->project->name
                    ] : null,
                    'assignee' => $task->user ? [
                        'id' => $task->user->id,
                        'name' => $task->user->name
                    ] : null,
                    'created_at' => $task->created_at->toISOString()
                ];
            })
            ->toArray();
    }

    /**
     * Get recent activities
     */
    public function getRecentActivities(string $tenantId, int $limit = 20): array
    {
        return ProjectActivity::where('tenant_id', $tenantId)
            ->with(['user:id,name', 'project:id,name'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => $activity->id,
                    'type' => $activity->type,
                    'description' => $activity->description,
                    'user' => $activity->user ? [
                        'id' => $activity->user->id,
                        'name' => $activity->user->name
                    ] : null,
                    'project' => $activity->project ? [
                        'id' => $activity->project->id,
                        'name' => $activity->project->name
                    ] : null,
                    'timestamp' => $activity->created_at->toISOString()
                ];
            })
            ->toArray();
    }

    /**
     * Get notifications
     */
    public function getNotifications(string $userId, string $tenantId, int $limit = 10): array
    {
        return Notification::where('user_id', $userId)
            ->where('tenant_id', $tenantId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'type' => $notification->type,
                    'read' => $notification->is_read,
                    'created_at' => $notification->created_at->toISOString()
                ];
            })
            ->toArray();
    }

    /**
     * Get metrics data
     */
    public function getMetrics(string $tenantId): array
    {
        $cacheKey = "dashboard_metrics_{$tenantId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($tenantId) {
            // Project completion rate
            $totalProjects = Project::where('tenant_id', $tenantId)->count();
            $completedProjects = Project::where('tenant_id', $tenantId)
                ->where('status', 'completed')->count();
            $projectCompletionRate = $totalProjects > 0 ? 
                round(($completedProjects / $totalProjects) * 100, 2) : 0;

            // Task completion rate
            $totalTasks = Task::where('tenant_id', $tenantId)->count();
            $completedTasks = Task::where('tenant_id', $tenantId)
                ->where('status', 'completed')->count();
            $taskCompletionRate = $totalTasks > 0 ? 
                round(($completedTasks / $totalTasks) * 100, 2) : 0;

            // Average task completion time
            $avgCompletionTime = Task::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereNotNull('end_date')
                ->whereNotNull('created_at')
                ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, end_date)) as avg_hours')
                ->value('avg_hours') ?? 0;

            return [
                [
                    'id' => 'project_completion_rate',
                    'code' => 'PCR',
                    'name' => 'Project Completion Rate',
                    'category' => 'project',
                    'unit' => 'percentage',
                    'value' => $projectCompletionRate,
                    'display_config' => ['color' => 'blue', 'icon' => 'chart'],
                    'recorded_at' => now()->toISOString()
                ],
                [
                    'id' => 'task_completion_rate',
                    'code' => 'TCR',
                    'name' => 'Task Completion Rate',
                    'category' => 'task',
                    'unit' => 'percentage',
                    'value' => $taskCompletionRate,
                    'display_config' => ['color' => 'blue', 'icon' => 'chart'],
                    'recorded_at' => now()->toISOString()
                ],
                [
                    'id' => 'avg_task_completion_hours',
                    'code' => 'ATCH',
                    'name' => 'Average Task Completion Hours',
                    'category' => 'task',
                    'unit' => 'hours',
                    'value' => round($avgCompletionTime, 2),
                    'display_config' => ['color' => 'red', 'icon' => 'clock'],
                    'recorded_at' => now()->toISOString()
                ],
                [
                    'id' => 'active_projects_count',
                    'code' => 'APC',
                    'name' => 'Active Projects Count',
                    'category' => 'project',
                    'unit' => 'count',
                    'value' => Project::where('tenant_id', $tenantId)->where('status', 'active')->count(),
                    'display_config' => ['color' => 'green', 'icon' => 'check'],
                    'recorded_at' => now()->toISOString()
                ],
                [
                    'id' => 'overdue_tasks_count',
                    'code' => 'OTC',
                    'name' => 'Overdue Tasks Count',
                    'category' => 'task',
                    'unit' => 'count',
                    'value' => 0, // Skip due_date query as column doesn't exist
                    'display_config' => ['color' => 'green', 'icon' => 'check'],
                    'recorded_at' => now()->toISOString()
                ]
            ];
        });
    }

    /**
     * Get user information
     */
    private function getUserInfo(string $userId): array
    {
        $user = User::find($userId);
        
        if (!$user) {
            return [
                'id' => $userId,
                'name' => 'Unknown User',
                'role' => 'guest'
            ];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role ?? 'member',
            'avatar' => $user->avatar_url ?? null,
            'last_login' => $user->last_login_at?->toISOString()
        ];
    }

    /**
     * Get dashboard metrics
     */
    public function getDashboardMetrics(string $tenantId): array
    {
        $cacheKey = "dashboard_metrics_{$tenantId}";
        
        return Cache::remember($cacheKey, $this->cacheTtl, function () use ($tenantId) {
            return [
                'project_completion_rate' => $this->getProjectCompletionRate($tenantId),
                'task_completion_rate' => $this->getTaskCompletionRate($tenantId),
                'avg_task_completion_hours' => $this->getAvgTaskCompletionTime($tenantId),
                'active_projects_count' => Project::where('tenant_id', $tenantId)
                    ->where('status', 'active')->count(),
                'overdue_tasks_count' => 0, // Skip due_date query as column doesn't exist
                'team_productivity' => $this->getTeamProductivity($tenantId),
                'budget_utilization' => $this->getBudgetUtilization($tenantId)
            ];
        });
    }

    /**
     * Get project completion rate
     */
    private function getProjectCompletionRate(string $tenantId): float
    {
        $totalProjects = Project::where('tenant_id', $tenantId)->count();
        $completedProjects = Project::where('tenant_id', $tenantId)
            ->where('status', 'completed')->count();
        
        return $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100, 2) : 0;
    }

    /**
     * Get task completion rate
     */
    private function getTaskCompletionRate(string $tenantId): float
    {
        $totalTasks = Task::where('tenant_id', $tenantId)->count();
        $completedTasks = Task::where('tenant_id', $tenantId)
            ->where('status', 'completed')->count();
        
        return $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0;
    }

    /**
     * Get average task completion time
     */
    private function getAvgTaskCompletionTime(string $tenantId): float
    {
        $avgCompletionTime = Task::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereNotNull('end_date')
            ->whereNotNull('created_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, end_date)) as avg_hours')
            ->value('avg_hours') ?? 0;
        
        return round($avgCompletionTime, 2);
    }

    /**
     * Get team productivity metric
     */
    private function getTeamProductivity(string $tenantId): float
    {
        $totalTasks = Task::where('tenant_id', $tenantId)->count();
        $completedTasks = Task::where('tenant_id', $tenantId)
            ->where('status', 'completed')->count();
        $teamMembers = User::where('tenant_id', $tenantId)->count();
        
        if ($teamMembers === 0) return 0;
        
        return round(($completedTasks / $teamMembers), 2);
    }

    /**
     * Get budget utilization
     */
    private function getBudgetUtilization(string $tenantId): float
    {
        // Skip budget query as column doesn't exist
        return 0;
    }

    /**
     * Get user dashboard
     */
    public function getUserDashboard(string $userId): ?\App\Models\UserDashboard
    {
        $dashboard = \App\Models\UserDashboard::where('user_id', $userId)
            ->where('is_default', true)
            ->where('is_active', true)
            ->first();
            
        if (!$dashboard) {
            // Create default dashboard if none exists
            $user = \App\Models\User::find($userId);
            if ($user && $user->tenant_id) {
                $dashboard = \App\Models\UserDashboard::create([
                    'user_id' => $userId,
                    'tenant_id' => $user->tenant_id,
                    'name' => 'My Dashboard',
                    'layout_config' => ['columns' => 3],
                    'widgets' => [],
                    'preferences' => [],
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }
        }
        
        return $dashboard;
    }

    /**
     * Clear dashboard cache
     */
    public function clearCache(string $userId = null, string $tenantId = null): void
    {
        if ($userId && $tenantId) {
            Cache::forget("dashboard_data_{$userId}_{$tenantId}");
        }
        
        if ($tenantId) {
            Cache::forget("dashboard_stats_{$tenantId}");
            Cache::forget("dashboard_metrics_{$tenantId}");
        }
    }
}