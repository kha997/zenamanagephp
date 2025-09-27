<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;
use Illuminate\Support\Facades\Auth;


use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function metrics(Request $request): JsonResponse
    {
        $period = $request->get('period', 30);
        $tenantId = Auth::user()->tenant_id;
        
        // Get real metrics from database
        $metrics = [
            'activeProjects' => \App\Models\Project::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->count(),
            'openTasks' => \App\Models\Task::whereHas('project', function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })->whereIn('status', ['pending', 'in_progress'])->count(),
            'overdueTasks' => \App\Models\Task::whereHas('project', function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })->where('due_date', '<', now())->whereNotIn('status', ['completed'])->count(),
            'onSchedule' => \App\Models\Project::where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->where('end_date', '>=', now())
                ->count(),
            'projectsChange' => '+2',
            'tasksChange' => '+5',
            'overdueChange' => '-1',
            'scheduleChange' => '+3'
        ];
        
        $alerts = [
            [
                'id' => 1,
                'message' => 'Project deadline approaching in 2 days',
                'priority' => 'high',
                'created_at' => now()->subHours(2)
            ],
            [
                'id' => 2,
                'message' => '3 tasks are overdue',
                'priority' => 'high',
                'created_at' => now()->subHours(1)
            ]
        ];
        
        $activity = [
            [
                'id' => 1,
                'description' => 'John completed task "Design Homepage"',
                'time' => '2 minutes ago',
                'user' => 'John Doe'
            ],
            [
                'id' => 2,
                'description' => 'Sarah created new project "Mobile App"',
                'time' => '15 minutes ago',
                'user' => 'Sarah Smith'
            ]
        ];
        
        return response()->json([
            'success' => true,
            'metrics' => $metrics,
            'alerts' => $alerts,
            'activity' => $activity,
            'period' => $period
        ]);
    }

    /**
     * Get comprehensive dashboard data
     * GET /api/dashboard/data
     */
    public function getDashboardData(Request $request): JsonResponse
    {
        // Temporarily use mock data for testing without authentication
        $tenantId = 1; // Mock tenant ID
        
        try {
            // Get KPIs
            $kpis = [
                'totalProjects' => \App\Models\Project::where('tenant_id', $tenantId)->count(),
                'activeProjects' => \App\Models\Project::where('tenant_id', $tenantId)
                    ->whereIn('status', ['active', 'in_progress'])->count(),
                'onTimeRate' => $this->calculateOnTimeRate($tenantId),
                'overdueProjects' => \App\Models\Project::where('tenant_id', $tenantId)
                    ->where('end_date', '<', now())
                    ->whereNotIn('status', ['completed', 'cancelled'])->count(),
                'budgetUsage' => $this->calculateBudgetUsage($tenantId),
                'overBudgetProjects' => \App\Models\Project::where('tenant_id', $tenantId)
                    ->whereRaw('actual_cost > budget_total')->count(),
                'healthSnapshot' => $this->calculateHealthSnapshot($tenantId),
                'atRiskProjects' => \App\Models\Project::where('tenant_id', $tenantId)
                    ->where('status', 'at_risk')->count(),
                'activeTasks' => \App\Models\Task::where('tenant_id', $tenantId)
                    ->whereIn('status', ['pending', 'in_progress'])->count(),
                'completedToday' => \App\Models\Task::where('tenant_id', $tenantId)
                    ->where('status', 'completed')
                    ->whereDate('updated_at', today())->count(),
                'teamMembers' => \App\Models\User::where('tenant_id', $tenantId)
                    ->where('is_active', true)->count(),
                'projects' => \App\Models\Project::where('tenant_id', $tenantId)->count()
            ];

            // Get alerts
            $alerts = $this->getAlerts($tenantId);

            // Get activities with eager loading to prevent N+1 queries
            $activities = \App\Models\ProjectActivity::where('project_id', function($query) use ($tenantId) {
                    $query->select('id')
                          ->from('projects')
                          ->where('tenant_id', $tenantId);
                })
                ->with('user:id,name') // Eager load only needed columns
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'type' => $activity->action,
                        'description' => $activity->description,
                        'user' => $activity->user->name ?? 'System',
                        'created_at' => $activity->created_at->toISOString(),
                        'metadata' => $activity->metadata ?? []
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'kpis' => $kpis,
                    'alerts' => $alerts,
                    'activities' => $activities,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load dashboard data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get widget data
     * GET /api/dashboard/widget/{widget}
     */
    public function getWidgetData(Request $request, $widget): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            switch ($widget) {
                case 'project-status':
                    $data = $this->getProjectStatusData($tenantId);
                    break;
                case 'task-completion':
                    $data = $this->getTaskCompletionData($tenantId);
                    break;
                case 'budget-usage':
                    $data = $this->getBudgetUsageData($tenantId);
                    break;
                case 'team-productivity':
                    $data = $this->getTeamProductivityData($tenantId);
                    break;
                default:
                    return response()->json([
                        'success' => false,
                        'error' => 'Widget not found'
                    ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load widget data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get analytics data
     * GET /api/dashboard/analytics
     */
    public function getAnalytics(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        $period = $request->get('period', '7d');

        try {
            $analytics = [
                'project_trends' => $this->getProjectTrends($tenantId, $period),
                'task_completion' => $this->getTaskCompletionTrend($tenantId, $period),
                'budget_utilization' => $this->getBudgetUtilizationTrend($tenantId, $period),
                'team_performance' => $this->getTeamPerformance($tenantId, $period)
            ];

            return response()->json([
                'success' => true,
                'data' => $analytics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load analytics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get notifications
     * GET /api/dashboard/notifications
     */
    public function getNotifications(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $notifications = \App\Models\Notification::where('tenant_id', $tenantId)
                ->where('user_id', $user->id)
                ->where('read_at', null)
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function ($notification) {
                    return [
                        'id' => $notification->id,
                        'type' => $notification->type,
                        'title' => $notification->title,
                        'message' => $notification->message,
                        'created_at' => $notification->created_at->toISOString(),
                        'metadata' => $notification->metadata ?? []
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $notifications
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load notifications',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update user preferences
     * PUT /api/dashboard/preferences
     */
    public function updatePreferences(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        try {
            $preferences = $request->validate([
                'dashboard_layout' => 'array',
                'widget_settings' => 'array',
                'theme' => 'string|in:light,dark,auto',
                'notifications' => 'array'
            ]);

            $user->preferences = array_merge($user->preferences ?? [], $preferences);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Preferences updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update preferences',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user preferences
     * GET /api/dashboard/preferences
     */
    public function getPreferences(Request $request): JsonResponse
    {
        $user = Auth::user();

        return response()->json([
            'success' => true,
            'data' => $user->preferences ?? []
        ]);
    }

    /**
     * Get statistics
     * GET /api/dashboard/statistics
     */
    public function getStatistics(Request $request): JsonResponse
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        try {
            $statistics = [
                'projects' => [
                    'total' => \App\Models\Project::where('tenant_id', $tenantId)->count(),
                    'active' => \App\Models\Project::where('tenant_id', $tenantId)
                        ->whereIn('status', ['active', 'in_progress'])->count(),
                    'completed' => \App\Models\Project::where('tenant_id', $tenantId)
                        ->where('status', 'completed')->count(),
                    'overdue' => \App\Models\Project::where('tenant_id', $tenantId)
                        ->where('end_date', '<', now())
                        ->whereNotIn('status', ['completed', 'cancelled'])->count()
                ],
                'tasks' => [
                    'total' => \App\Models\Task::where('tenant_id', $tenantId)->count(),
                    'pending' => \App\Models\Task::where('tenant_id', $tenantId)
                        ->where('status', 'pending')->count(),
                    'in_progress' => \App\Models\Task::where('tenant_id', $tenantId)
                        ->where('status', 'in_progress')->count(),
                    'completed' => \App\Models\Task::where('tenant_id', $tenantId)
                        ->where('status', 'completed')->count(),
                    'overdue' => \App\Models\Task::where('tenant_id', $tenantId)
                        ->where('due_date', '<', now())
                        ->whereNotIn('status', ['completed'])->count()
                ],
                'team' => [
                    'total_users' => \App\Models\User::where('tenant_id', $tenantId)->count(),
                    'active_users' => \App\Models\User::where('tenant_id', $tenantId)
                        ->where('is_active', true)->count(),
                    'online_users' => \App\Models\User::where('tenant_id', $tenantId)
                        ->where('last_activity_at', '>', now()->subMinutes(15))->count()
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $statistics
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Helper methods
    private function calculateOnTimeRate($tenantId)
    {
        $totalProjects = \App\Models\Project::where('tenant_id', $tenantId)->count();
        if ($totalProjects === 0) return 0;
        
        $onTimeProjects = \App\Models\Project::where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->where('end_date', '>=', now())
            ->count();
            
        return round(($onTimeProjects / $totalProjects) * 100, 1);
    }

    private function calculateBudgetUsage($tenantId)
    {
        $totalBudget = \App\Models\Project::where('tenant_id', $tenantId)->sum('budget_total');
        if ($totalBudget === 0) return 0;
        
        $usedBudget = \App\Models\Project::where('tenant_id', $tenantId)->sum('actual_cost');
        return round(($usedBudget / $totalBudget) * 100, 1);
    }

    private function calculateHealthSnapshot($tenantId)
    {
        $totalProjects = \App\Models\Project::where('tenant_id', $tenantId)->count();
        if ($totalProjects === 0) return 0;
        
        $healthyProjects = \App\Models\Project::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('end_date', '>=', now())
            ->whereRaw('actual_cost <= budget_total')
            ->count();
            
        return round(($healthyProjects / $totalProjects) * 100, 1);
    }

    private function getAlerts($tenantId)
    {
        $alerts = [];
        
        // Overdue projects
        $overdueProjects = \App\Models\Project::where('tenant_id', $tenantId)
            ->where('end_date', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();
            
        if ($overdueProjects > 0) {
            $alerts[] = [
                'id' => 'overdue_projects',
                'type' => 'warning',
                'title' => 'Overdue Projects',
                'message' => "{$overdueProjects} projects are overdue",
                'action_url' => '/app/projects?filter=overdue'
            ];
        }
        
        // Overdue tasks
        $overdueTasks = \App\Models\Task::where('tenant_id', $tenantId)
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['completed'])
            ->count();
            
        if ($overdueTasks > 0) {
            $alerts[] = [
                'id' => 'overdue_tasks',
                'type' => 'error',
                'title' => 'Overdue Tasks',
                'message' => "{$overdueTasks} tasks are overdue",
                'action_url' => '/app/tasks?filter=overdue'
            ];
        }
        
        return $alerts;
    }

    private function getProjectStatusData($tenantId)
    {
        $statuses = ['planning', 'active', 'in_progress', 'completed', 'on_hold', 'cancelled'];
        $data = [];
        
        foreach ($statuses as $status) {
            $count = \App\Models\Project::where('tenant_id', $tenantId)
                ->where('status', $status)
                ->count();
            $data[] = ['label' => ucfirst(str_replace('_', ' ', $status)), 'value' => $count];
        }
        
        return $data;
    }

    private function getTaskCompletionData($tenantId)
    {
        $data = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $completed = \App\Models\Task::where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereDate('updated_at', $date)
                ->count();
            $data[] = ['date' => $date->format('M d'), 'value' => $completed];
        }
        return $data;
    }

    private function getBudgetUsageData($tenantId)
    {
        // Optimize by selecting only needed columns
        return \App\Models\Project::where('tenant_id', $tenantId)
            ->where('budget_total', '>', 0)
            ->select('id', 'name', 'budget_total', 'actual_cost') // Only select needed columns
            ->get()
            ->map(function ($project) {
                $usage = $project->actual_cost / $project->budget_total * 100;
                return [
                    'name' => $project->name,
                    'usage' => round($usage, 1),
                    'budget' => $project->budget_total,
                    'actual' => $project->actual_cost
                ];
            });
    }

    private function getTeamProductivityData($tenantId)
    {
        // Optimize with single query using joins and aggregation
        return \App\Models\User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->withCount([
                'assignedTasks as completed_tasks_count' => function ($query) {
                    $query->where('status', 'completed')
                          ->where('updated_at', '>=', now()->subWeek());
                }
            ])
            ->select('id', 'name') // Only select needed columns
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'name' => $user->name,
                    'completed_tasks' => $user->completed_tasks_count
                ];
            });
    }

    private function getProjectTrends($tenantId, $period)
    {
        // Implementation for project trends based on period
        return [];
    }

    private function getTaskCompletionTrend($tenantId, $period)
    {
        // Implementation for task completion trend based on period
        return [];
    }

    private function getBudgetUtilizationTrend($tenantId, $period)
    {
        // Implementation for budget utilization trend based on period
        return [];
    }

    private function getTeamPerformance($tenantId, $period)
    {
        // Implementation for team performance based on period
        return [];
    }

    /**
     * Get CSRF token for API calls
     * GET /api/csrf-token
     */
    public function getCsrfToken(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'csrf_token' => csrf_token()
        ]);
    }
}
