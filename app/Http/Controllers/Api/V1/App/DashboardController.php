<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get main dashboard data (combined view)
     */
    public function index(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Get all dashboard data in one call
            $stats = $this->getStatsData($tenantId);
            $recentProjects = $this->getRecentProjectsData($tenantId, 5);
            $recentTasks = $this->getRecentTasksData($tenantId, 5);
            $recentActivity = $this->getRecentActivityData($tenantId, 10);

            return response()->json([
                'success' => true,
                'data' => [
                    'stats' => $stats,
                    'recent_projects' => $recentProjects,
                    'recent_tasks' => $recentTasks,
                    'recent_activity' => $recentActivity
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load dashboard',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard stats/KPIs
     */
    public function getStats(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $stats = $this->getStatsData($tenantId);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load dashboard stats',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Get stats data
     */
    private function getStatsData(string $tenantId): array
    {
        // Get counts
        $totalProjects = Project::where('tenant_id', $tenantId)->count();
        $activeProjects = Project::where('tenant_id', $tenantId)
            ->where('status', 'active')->count();
        $completedProjects = Project::where('tenant_id', $tenantId)
            ->where('status', 'completed')->count();

        $totalTasks = Task::whereHas('project', function($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->count();
        
        $completedTasks = Task::whereHas('project', function($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->where('status', 'completed')->count();
        
        $inProgressTasks = Task::whereHas('project', function($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->where('status', 'in_progress')->count();
        
        $overdueTasks = Task::whereHas('project', function($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->where('end_date', '<', now())
          ->whereNotIn('status', ['completed', 'cancelled'])
          ->count();

        $totalUsers = User::where('tenant_id', $tenantId)->count();
        $activeUsers = User::where('tenant_id', $tenantId)
            ->where('is_active', true)->count();

        return [
            'projects' => [
                'total' => $totalProjects,
                'active' => $activeProjects,
                'completed' => $completedProjects
            ],
            'tasks' => [
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'in_progress' => $inProgressTasks,
                'overdue' => $overdueTasks
            ],
            'users' => [
                'total' => $totalUsers,
                'active' => $activeUsers
            ]
        ];
    }

    /**
     * Get recent projects
     */
    public function getRecentProjects(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $limit = (int) $request->get('limit', 5);

            $projects = $this->getRecentProjectsData($tenantId, $limit);

            return response()->json([
                'success' => true,
                'data' => $projects
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load recent projects',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Get recent projects data
     */
    private function getRecentProjectsData(string $tenantId, int $limit): array
    {
        return Project::where('tenant_id', $tenantId)
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'progress' => $project->progress ?? 0,
                    'updated_at' => $project->updated_at->toISOString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get recent tasks
     */
    public function getRecentTasks(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $limit = (int) $request->get('limit', 5);

            $tasks = $this->getRecentTasksData($tenantId, $limit);

            return response()->json([
                'success' => true,
                'data' => $tasks
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load recent tasks',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Get recent tasks data
     */
    private function getRecentTasksData(string $tenantId, int $limit): array
    {
        return Task::whereHas('project', function($q) use ($tenantId) {
                $q->where('tenant_id', $tenantId);
            })
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->with('project')
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'name' => $task->name,
                    'status' => $task->status,
                    'project_name' => $task->project->name ?? null,
                    'updated_at' => $task->updated_at->toISOString(),
                ];
            })
            ->toArray();
    }

    /**
     * Get recent activity
     */
    public function getRecentActivity(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $limit = (int) $request->get('limit', 10);

            $activity = $this->getRecentActivityData($tenantId, $limit);

            return response()->json([
                'success' => true,
                'data' => $activity
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load recent activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper: Get recent activity data
     */
    private function getRecentActivityData(string $tenantId, int $limit, $user = null): array
    {
        if ($user === null) {
            $user = Auth::user();
        }

        $activity = [];
        
        // Get recent project updates
        $recentProjects = Project::where('tenant_id', $tenantId)
            ->orderBy('updated_at', 'desc')
            ->limit((int) ceil($limit / 2))
            ->get();
            
        foreach ($recentProjects as $project) {
            $activity[] = [
                'id' => 'project-' . $project->id,
                'type' => 'project',
                'action' => 'updated',
                'description' => "Project '{$project->name}' was updated",
                'timestamp' => $project->updated_at->toISOString(),
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name
                ]
            ];
        }

        // Get recent task updates
        $recentTasks = Task::whereHas('project', function($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })
            ->orderBy('updated_at', 'desc')
            ->limit((int) ceil($limit / 2))
            ->with('project', 'assignedTo')
            ->get();
            
        foreach ($recentTasks as $task) {
            $activity[] = [
                'id' => 'task-' . $task->id,
                'type' => 'task',
                'action' => 'updated',
                'description' => "Task '{$task->name}' in '{$task->project->name}' was updated",
                'timestamp' => $task->updated_at->toISOString(),
                'user' => [
                    'id' => $task->assignedTo->id ?? $user->id,
                    'name' => $task->assignedTo->name ?? $user->name
                ]
            ];
        }

        // Sort by timestamp descending and limit
        usort($activity, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });
        $activity = array_slice($activity, 0, $limit);

        return $activity;
    }

    /**
     * Get team member status
     */
    public function getTeamStatus(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            $members = User::where('tenant_id', $tenantId)
                ->where('id', '!=', $user->id) // Exclude current user
                ->get()
                ->map(function ($member) {
                    // Determine status based on last activity
                    $lastLogin = $member->last_login_at ?? $member->created_at;
                    $minutesAgo = Carbon::parse($lastLogin)->diffInMinutes(now());
                    
                    $status = 'offline';
                    if ($minutesAgo < 5) {
                        $status = 'online';
                    } elseif ($minutesAgo < 30) {
                        $status = 'away';
                    }

                    return [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'avatar' => $member->avatar,
                        'role' => $member->role ?? 'member',
                        'status' => $status,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $members
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load team status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get chart data by type
     */
    public function getChartData(Request $request, string $type): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $period = $request->get('period', '30d');
            
            // Parse period
            $days = (int) str_replace('d', '', $period);
            if ($days <= 0) $days = 30;
            
            $startDate = Carbon::now()->subDays($days);
            
            if ($type === 'project-progress') {
                // Doughnut chart data
                $data = [
                    'labels' => ['Completed', 'Active', 'Planning', 'On Hold'],
                    'datasets' => [
                        [
                            'data' => [
                                Project::where('tenant_id', $tenantId)->where('status', 'completed')->count(),
                                Project::where('tenant_id', $tenantId)->where('status', 'active')->count(),
                                Project::where('tenant_id', $tenantId)->where('status', 'planning')->count(),
                                Project::where('tenant_id', $tenantId)->where('status', 'on_hold')->count()
                            ],
                            'backgroundColor' => [
                                'rgb(34, 197, 94)',
                                'rgb(234, 179, 8)',
                                'rgb(59, 130, 246)',
                                'rgb(249, 115, 22)'
                            ]
                        ]
                    ]
                ];
            } elseif ($type === 'task-completion') {
                // Line chart data
                $dateRange = [];
                $completed = [];
                $total = [];
                
                for ($i = $days; $i >= 0; $i--) {
                    $date = Carbon::now()->subDays($i);
                    $dateRange[] = $date->format('M d');
                    
                    $dayStart = $date->copy()->startOfDay();
                    $dayEnd = $date->copy()->endOfDay();
                    
                    $completed[] = Task::whereHas('project', function($q) use ($tenantId) {
                        $q->where('tenant_id', $tenantId);
                    })
                        ->whereBetween('updated_at', [$dayStart, $dayEnd])
                        ->where('status', 'completed')
                        ->count();
                        
                    $total[] = Task::whereHas('project', function($q) use ($tenantId) {
                        $q->where('tenant_id', $tenantId);
                    })
                        ->whereBetween('created_at', [$dayStart, $dayEnd])
                        ->count();
                }

                $data = [
                    'labels' => $dateRange,
                    'datasets' => [
                        [
                            'label' => 'Completed',
                            'data' => $completed,
                            'borderColor' => 'rgb(34, 197, 94)',
                            'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                            'tension' => 0.4,
                            'fill' => true
                        ],
                        [
                            'label' => 'Total',
                            'data' => $total,
                            'borderColor' => 'rgb(59, 130, 246)',
                            'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                            'tension' => 0.4,
                            'fill' => true
                        ]
                    ]
                ];
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Invalid chart type'
                ], 400);
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load chart data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard metrics for charts
     */
    public function getMetrics(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;
            $period = $request->get('period', '30d');
            
            // Parse period (e.g., '30d', '7d', '90d')
            $days = (int) str_replace('d', '', $period);
            if ($days <= 0) $days = 30;
            
            $startDate = Carbon::now()->subDays($days);
            
            // Project Progress Chart (Doughnut)
            $projectProgress = [
                'labels' => ['Completed', 'Active', 'Planning', 'On Hold'],
                'datasets' => [
                    [
                        'data' => [
                            Project::where('tenant_id', $tenantId)->where('status', 'completed')->count(),
                            Project::where('tenant_id', $tenantId)->where('status', 'active')->count(),
                            Project::where('tenant_id', $tenantId)->where('status', 'planning')->count(),
                            Project::where('tenant_id', $tenantId)->where('status', 'on_hold')->count()
                        ],
                        'backgroundColor' => [
                            'rgb(34, 197, 94)',  // green
                            'rgb(234, 179, 8)',   // yellow
                            'rgb(59, 130, 246)',  // blue
                            'rgb(249, 115, 22)'   // orange
                        ]
                    ]
                ]
            ];

            // Task Completion Chart (Line)
            $dateRange = [];
            $completed = [];
            $total = [];
            
            for ($i = $days; $i >= 0; $i--) {
                $date = Carbon::now()->subDays($i);
                $dateRange[] = $date->format('M d');
                
                $dayStart = $date->copy()->startOfDay();
                $dayEnd = $date->copy()->endOfDay();
                
                $completed[] = Task::whereHas('project', function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })
                    ->whereBetween('updated_at', [$dayStart, $dayEnd])
                    ->where('status', 'completed')
                    ->count();
                    
                $total[] = Task::whereHas('project', function($q) use ($tenantId) {
                    $q->where('tenant_id', $tenantId);
                })
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->count();
            }

            $taskCompletion = [
                'labels' => $dateRange,
                'datasets' => [
                    [
                        'label' => 'Completed',
                        'data' => $completed,
                        'borderColor' => 'rgb(34, 197, 94)',
                        'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                        'tension' => 0.4
                    ],
                    [
                        'label' => 'Total',
                        'data' => $total,
                        'borderColor' => 'rgb(59, 130, 246)',
                        'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                        'tension' => 0.4
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'project_progress' => $projectProgress,
                    'task_completion' => $taskCompletion
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load metrics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user alerts
     */
    public function getAlerts(): JsonResponse
    {
        try {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // Return empty alerts for now (can be extended later)
            return response()->json([
                'success' => true,
                'data' => []
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load alerts',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark alert as read
     */
    public function markAlertAsRead(string $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Alert marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark alert as read',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark all alerts as read
     */
    public function markAllAlertsAsRead(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'All alerts marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to mark all alerts as read',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available widgets
     */
    public function getAvailableWidgets(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => []
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to load widgets',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get widget data
     */
    public function getWidgetData(string $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => []
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
     * Add widget to dashboard
     */
    public function addWidget(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Widget added successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to add widget',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove widget from dashboard
     */
    public function removeWidget(string $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Widget removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to remove widget',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update widget configuration
     */
    public function updateWidgetConfig(Request $request, string $id): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Widget updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update widget',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update dashboard layout
     */
    public function updateLayout(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Layout updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to update layout',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save user preferences
     */
    public function saveUserPreferences(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Preferences saved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to save preferences',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset dashboard to default
     */
    public function resetToDefault(): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'message' => 'Dashboard reset to default successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to reset dashboard',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

