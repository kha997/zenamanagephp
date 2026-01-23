<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DashboardAlert;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Models\UserDashboard;
use App\Services\DashboardService;
use App\Services\ErrorEnvelopeService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DashboardController extends Controller
{
    private DashboardService $dashboardService;

    public function __construct(DashboardService $dashboardService)
    {
        $this->dashboardService = $dashboardService;
    }

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

            // Get activities
            $activities = \App\Models\Activity::where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'type' => $activity->type,
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
     * Get user dashboard record
     * GET /api/dashboard
     */
    public function getUserDashboard(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        $dashboard = UserDashboard::forUser($user->id)
            ->active()
            ->orderByDesc('is_default')
            ->first();

        if (!$dashboard) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => null,
                    'name' => 'Default Dashboard',
                    'layout' => [],
                    'preferences' => [],
                    'is_default' => true,
                    'is_active' => true,
                    'widgets' => [],
                    'created_at' => now()->toISOString(),
                    'updated_at' => now()->toISOString()
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $dashboard->id,
                'name' => $dashboard->name,
                'layout' => $dashboard->layout_config ?? [],
                'preferences' => $dashboard->preferences ?? [],
                'is_default' => (bool) $dashboard->is_default,
                'is_active' => (bool) $dashboard->is_active,
                'widgets' => $dashboard->widgets ?? [],
                'created_at' => optional($dashboard->created_at)->toISOString(),
                'updated_at' => optional($dashboard->updated_at)->toISOString(),
            ]
        ]);
    }

    public function getAvailableWidgets(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        try {
            $widgets = $this->dashboardService->getAvailableWidgetsForUser($user);

            return response()->json([
                'success' => true,
                'data' => $widgets
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to load available widgets', [
                'user_id' => $user->id,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load available widgets'
            ], 500);
        }
    }

    public function addWidget(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $widgetId = $request->input('widget_id');

        if (!$widgetId) {
            return response()->json([
                'success' => false,
                'message' => 'widget_id is required'
            ], 422);
        }

        if (!Str::isUlid($widgetId)) {
            return ErrorEnvelopeService::error(
                'E422.VALIDATION',
                'Invalid widget_id',
                ['widget_id' => $widgetId],
                422
            );
        }

        try {
            $result = $this->dashboardService->addWidget($user, $widgetId, $request->input('config', []));
            $widgetInstance = $result['widget_instance'] ?? null;

            $response = $result;
            $response['widget_instance'] = $widgetInstance;
            $response['data'] = $response['data'] ?? [];
            $response['data']['widget_instance'] = $widgetInstance;

            return response()->json($response);
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Widget not found'
            ], 404);
        } catch (\Exception $exception) {
            Log::error('Failed to add widget to dashboard', [
                'user_id' => $user->id,
                'widget_id' => $widgetId,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add widget to dashboard'
            ], 500);
        }
    }

    public function removeWidget(string $widgetInstanceId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        try {
            $result = $this->dashboardService->removeWidget($user, $widgetInstanceId);

            return response()->json([
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? 'Widget removed from dashboard'
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to remove widget from dashboard', [
                'user_id' => $user->id,
                'widget_instance_id' => $widgetInstanceId,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove widget from dashboard'
            ], 500);
        }
    }

    public function updateWidgetConfig(Request $request, string $widgetInstanceId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $config = $request->input('config', []);

        if (!is_array($config) || empty($config)) {
            return response()->json([
                'success' => false,
                'message' => 'config payload is required'
            ], 422);
        }

        try {
            $result = $this->dashboardService->updateWidgetConfig($user->id, $widgetInstanceId, $config);

            return response()->json([
                'success' => $result['success'] ?? false,
                'message' => $result['message'] ?? 'Widget configuration updated successfully'
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to update widget config', [
                'user_id' => $user->id,
                'widget_instance_id' => $widgetInstanceId,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update widget configuration'
            ], 500);
        }
    }

    public function updateDashboardLayout(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $layout = $request->input('layout', []);
        $widgets = $request->input('widgets', null);

        if (!is_array($layout)) {
            return response()->json([
                'success' => false,
                'message' => 'layout payload is required'
            ], 422);
        }

        if ($widgets !== null && !is_array($widgets)) {
            return response()->json([
                'success' => false,
                'message' => 'widgets payload must be an array'
            ], 422);
        }

        try {
            $result = $this->dashboardService->updateDashboardLayout($user->id, $layout, $widgets);

            return response()->json([
                'success' => $result['success'] ?? true,
                'message' => 'Dashboard layout updated',
                'data' => [
                    'dashboard' => $result['dashboard'] ?? null
                ]
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to update dashboard layout', [
                'user_id' => $user->id,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update dashboard layout'
            ], 500);
        }
    }

    public function getUserAlerts(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        try {
            $alerts = $this->dashboardService->getUserAlerts($user);

            return response()->json([
                'success' => true,
                'data' => $alerts
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to load user alerts', [
                'user_id' => $user->id,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load alerts'
            ], 500);
        }
    }

    public function markAlertAsRead(string $alertId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        try {
            $result = $this->dashboardService->markAlertAsRead($user, $alertId);

            return response()->json([
                'success' => $result['success'] ?? false,
                'message' => 'Alert marked as read',
                'data' => [
                    'alert' => $result['alert'] ?? null
                ]
            ]);
        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Alert not found'
            ], 404);
        } catch (\Exception $exception) {
            Log::error('Failed to mark alert as read', [
                'user_id' => $user->id,
                'alert_id' => $alertId,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark alert as read'
            ], 500);
        }
    }

    public function markAllAlertsAsRead(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        try {
            $result = $this->dashboardService->markAllAlertsAsRead($user);

            return response()->json([
                'success' => $result['success'] ?? false,
                'message' => 'All alerts marked as read',
                'data' => [
                    'updated' => $result['updated'] ?? 0
                ]
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to mark all alerts as read', [
                'user_id' => $user->id,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark alerts as read'
            ], 500);
        }
    }

    public function getDashboardMetrics(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        try {
            $metrics = $this->dashboardService->getDashboardMetrics($user);

            return response()->json([
                'success' => true,
                'data' => $metrics
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to load dashboard metrics', [
                'user_id' => $user->id,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard metrics'
            ], 500);
        }
    }

    public function saveUserPreferences(Request $request): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $preferences = $request->input('preferences', []);

        if (!is_array($preferences)) {
            return response()->json([
                'success' => false,
                'message' => 'preferences must be an object'
            ], 422);
        }

        try {
            $result = $this->dashboardService->saveUserPreferences($user->id, $preferences);

            return response()->json([
                'success' => $result['success'] ?? true,
                'message' => 'Preferences saved successfully',
                'data' => [
                    'dashboard' => $result['dashboard'] ?? null
                ]
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to save user preferences', [
                'user_id' => $user->id,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save preferences'
            ], 500);
        }
    }

    public function resetDashboard(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        try {
            $result = $this->dashboardService->resetDashboard($user->id);

            return response()->json([
                'success' => $result['success'] ?? true,
                'message' => 'Dashboard reset to default',
                'data' => [
                    'dashboard' => $result['dashboard'] ?? null
                ]
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to reset dashboard', [
                'user_id' => $user->id,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to reset dashboard'
            ], 500);
        }
    }

    public function getDashboardTemplate(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        try {
            $template = $this->dashboardService->getDashboardTemplateForRole($user);

            return response()->json([
                'success' => true,
                'data' => $template
            ]);
        } catch (\Exception $exception) {
            Log::error('Failed to load dashboard template', [
                'user_id' => $user->id,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard template'
            ], 500);
        }
    }

    public function getStats(): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        $tenantId = $user->tenant_id;
        $projectsQuery = Project::where('tenant_id', $tenantId);
        $tasksQuery = Task::where('tenant_id', $tenantId);

        $totalProjects = (clone $projectsQuery)->count();
        $activeProjects = (clone $projectsQuery)->where('status', 'active')->count();
        $completedProjects = (clone $projectsQuery)->where('status', 'completed')->count();
        $overdueProjects = (clone $projectsQuery)
            ->where('end_date', '<', now())
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->count();
        $budgetTotal = (clone $projectsQuery)->sum('budget_total');
        $actualCost = (clone $projectsQuery)->sum('actual_cost');
        $budgetUtilization = $budgetTotal > 0 ? round(($actualCost / $budgetTotal) * 100, 1) : 0;
        $totalTasks = (clone $tasksQuery)->count();
        $completedTasks = (clone $tasksQuery)->where('status', 'completed')->count();
        $pendingTasks = (clone $tasksQuery)->where('status', 'pending')->count();
        $inProgressTasks = (clone $tasksQuery)->where('status', 'in_progress')->count();
        $overdueTasks = (clone $tasksQuery)
            ->where('due_date', '<', now())
            ->whereNotIn('status', ['completed'])
            ->count();
        $unreadAlerts = DashboardAlert::forUser($user->id)->unread()->count();

        return response()->json([
            'success' => true,
            'data' => [
                'projects' => [
                    'total' => $totalProjects,
                    'active' => $activeProjects,
                    'completed' => $completedProjects,
                    'overdue' => $overdueProjects,
                    'budget_total' => $budgetTotal,
                    'actual_cost' => $actualCost,
                    'budget_utilization' => $budgetUtilization
                ],
                'tasks' => [
                    'total' => $totalTasks,
                    'completed' => $completedTasks,
                    'pending' => $pendingTasks,
                    'in_progress' => $inProgressTasks,
                    'overdue' => $overdueTasks
                ],
                'alerts' => [
                    'unread' => $unreadAlerts
                ],
                'generated_at' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get widget data
     * GET /api/dashboard/widget/{widget}
     */
    public function getWidgetData(Request $request, string $widgetId): JsonResponse
    {
        $user = $this->getAuthenticatedUser();

        if (!$user) {
            return $this->unauthorizedResponse();
        }

        try {
            $data = $this->dashboardService->getWidgetData($widgetId, $user);

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (ModelNotFoundException $exception) {
            return response()->json([
                'success' => false,
                'message' => 'Widget not found'
            ], 404);
        } catch (\Exception $exception) {
            Log::error('Failed to load widget data', [
                'user_id' => $user->id,
                'widget_id' => $widgetId,
                'error' => $exception->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to load widget data'
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

    private function getAuthenticatedUser(): ?User
    {
        return Auth::user();
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Unauthenticated'
        ], 401);
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
        return \App\Models\Project::where('tenant_id', $tenantId)
            ->where('budget_total', '>', 0)
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
        return \App\Models\User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->limit(10)
            ->get()
            ->map(function ($user) {
                $completedTasks = \App\Models\Task::where('assigned_to', $user->id)
                    ->where('status', 'completed')
                    ->where('updated_at', '>=', now()->subWeek())
                    ->count();
                    
                return [
                    'name' => $user->name,
                    'completed_tasks' => $completedTasks
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
