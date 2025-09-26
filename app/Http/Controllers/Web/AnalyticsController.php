<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Analytics Controller
 * Handles analytics and reporting functionality
 */
class AnalyticsController extends Controller
{
    /**
     * Get task analytics
     */
    public function taskAnalytics(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
            $projectId = $request->get('project_id');

            $query = Task::query();

            if ($projectId) {
                $query->select(['id', 'name', 'status'])->where('project_id', $projectId);
            }

            $query->whereBetween('tasks.created_at', [$dateFrom, $dateTo]);

            // Basic statistics
            $totalTasks = $query->count();
            $completedTasks = (clone $query)->select(['id', 'name', 'status'])->where('status', 'completed')->count();
            $inProgressTasks = (clone $query)->select(['id', 'name', 'status'])->where('status', 'in_progress')->count();
            $pendingTasks = (clone $query)->select(['id', 'name', 'status'])->where('status', 'pending')->count();
            $overdueTasks = (clone $query)->select(['id', 'name', 'status'])->where('end_date', '<', now())->select(['id', 'name', 'status'])->where('status', '!=', 'completed')->count();

            // Time tracking statistics
            $totalEstimatedHours = (clone $query)->sum('estimated_hours');
            $totalActualHours = (clone $query)->sum('actual_hours');
            $averageProgress = (clone $query)->avg('progress_percent');

            // Priority distribution
            $priorityStats = (clone $query)
                ->select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray();

            // Status distribution
            $statusStats = (clone $query)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // Project distribution
            $projectStats = (clone $query)
                ->join('projects', 'tasks.project_id', '=', 'projects.id')
                ->select('projects.name', DB::raw('count(*) as count'))
                ->groupBy('projects.id', 'projects.name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->pluck('count', 'name')
                ->toArray();

            // Assignee performance
            $assigneeStats = (clone $query)
                ->join('users', 'tasks.assignee_id', '=', 'users.id')
                ->select('users.name', DB::raw('count(*) as total_tasks'), DB::raw('avg(progress_percent) as avg_progress'))
                ->groupBy('users.id', 'users.name')
                ->orderBy('total_tasks', 'desc')
                ->limit(10)
                ->with(['user', 'project'])->get()
                ->map(function($item) {
                    return [
                        'name' => $item->name,
                        'total_tasks' => $item->total_tasks,
                        'avg_progress' => round((float)$item->avg_progress, 2)
                    ];
                });

            // Weekly progress
            $weeklyProgress = (clone $query)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('count(*) as tasks_created'),
                    DB::raw('avg(progress_percent) as avg_progress')
                )
                ->whereBetween('tasks.created_at', [$dateFrom, $dateTo])
                ->groupBy(DB::raw('DATE(created_at)'))
                ->orderBy('date')
                ->with(['user', 'project'])->get()
                ->map(function($item) {
                    return [
                        'date' => $item->date,
                        'tasks_created' => $item->tasks_created,
                        'avg_progress' => round((float)$item->avg_progress, 2)
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_tasks' => $totalTasks,
                        'completed_tasks' => $completedTasks,
                        'in_progress_tasks' => $inProgressTasks,
                        'pending_tasks' => $pendingTasks,
                        'overdue_tasks' => $overdueTasks,
                        'completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0
                    ],
                    'time_tracking' => [
                        'total_estimated_hours' => $totalEstimatedHours,
                        'total_actual_hours' => $totalActualHours,
                        'efficiency_rate' => $totalEstimatedHours > 0 ? round(($totalActualHours / $totalEstimatedHours) * 100, 2) : 0,
                        'average_progress' => round((float)$averageProgress, 2)
                    ],
                    'distributions' => [
                        'priority' => $priorityStats,
                        'status' => $statusStats,
                        'project' => $projectStats
                    ],
                    'performance' => [
                        'assignees' => $assigneeStats,
                        'weekly_progress' => $weeklyProgress
                    ],
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get task analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get project analytics
     */
    public function projectAnalytics(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));

            $query = Project::query()->whereBetween('created_at', [$dateFrom, $dateTo]);

            // Basic statistics
            $totalProjects = $query->count();
            $activeProjects = (clone $query)->select(['id', 'name', 'status'])->where('status', 'active')->count();
            $completedProjects = (clone $query)->select(['id', 'name', 'status'])->where('status', 'completed')->count();
            $onHoldProjects = (clone $query)->select(['id', 'name', 'status'])->where('status', 'on_hold')->count();
            $draftProjects = (clone $query)->select(['id', 'name', 'status'])->where('status', 'draft')->count();

            // Budget statistics
            $totalBudget = (clone $query)->sum('budget_total');
            $averageBudget = (clone $query)->avg('budget_total');
            $averageProgress = (clone $query)->avg('progress');

            // Status distribution
            $statusStats = (clone $query)
                ->select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();

            // PM performance
            $pmStats = (clone $query)
                ->join('users', 'projects.pm_id', '=', 'users.id')
                ->select('users.name', DB::raw('count(*) as total_projects'), DB::raw('avg(progress) as avg_progress'))
                ->groupBy('users.id', 'users.name')
                ->orderBy('total_projects', 'desc')
                ->limit(10)
                ->with(['user', 'project'])->get()
                ->map(function($item) {
                    return [
                        'name' => $item->name,
                        'total_projects' => $item->total_projects,
                        'avg_progress' => round((float)$item->avg_progress, 2)
                    ];
                });

            // Client distribution
            $clientStats = (clone $query)
                ->join('users', 'projects.client_id', '=', 'users.id')
                ->select('users.name', DB::raw('count(*) as count'), DB::raw('sum(budget_total) as total_budget'))
                ->groupBy('users.id', 'users.name')
                ->orderBy('count', 'desc')
                ->limit(10)
                ->with(['user', 'project'])->get()
                ->map(function($item) {
                    return [
                        'name' => $item->name,
                        'project_count' => $item->count,
                        'total_budget' => $item->total_budget
                    ];
                });

            // Monthly project creation
            $monthlyStats = (clone $query)
                ->select(
                    DB::raw('YEAR(created_at) as year'),
                    DB::raw('MONTH(created_at) as month'),
                    DB::raw('count(*) as projects_created'),
                    DB::raw('sum(budget_total) as total_budget')
                )
                ->whereBetween('tasks.created_at', [$dateFrom, $dateTo])
                ->groupBy(DB::raw('YEAR(created_at)'), DB::raw('MONTH(created_at)'))
                ->orderBy('year')
                ->orderBy('month')
                ->with(['user', 'project'])->get()
                ->map(function($item) {
                    return [
                        'year' => $item->year,
                        'month' => $item->month,
                        'projects_created' => $item->projects_created,
                        'total_budget' => $item->total_budget
                    ];
                });

            // Project completion timeline
            $completionStats = (clone $query)
                ->select(['id', 'name', 'status'])->where('status', 'completed')
                ->select(
                    DB::raw('YEAR(updated_at) as year'),
                    DB::raw('MONTH(updated_at) as month'),
                    DB::raw('count(*) as projects_completed')
                )
                ->groupBy(DB::raw('YEAR(updated_at)'), DB::raw('MONTH(updated_at)'))
                ->orderBy('year')
                ->orderBy('month')
                ->with(['user', 'project'])->get()
                ->map(function($item) {
                    return [
                        'year' => $item->year,
                        'month' => $item->month,
                        'projects_completed' => $item->projects_completed
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_projects' => $totalProjects,
                        'active_projects' => $activeProjects,
                        'completed_projects' => $completedProjects,
                        'on_hold_projects' => $onHoldProjects,
                        'draft_projects' => $draftProjects,
                        'completion_rate' => $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100, 2) : 0
                    ],
                    'budget' => [
                        'total_budget' => $totalBudget,
                        'average_budget' => round((float)$averageBudget, 2),
                        'average_progress' => round((float)$averageProgress, 2)
                    ],
                    'distributions' => [
                        'status' => $statusStats
                    ],
                    'performance' => [
                        'project_managers' => $pmStats,
                        'clients' => $clientStats
                    ],
                    'timeline' => [
                        'monthly_creation' => $monthlyStats,
                        'monthly_completion' => $completionStats
                    ],
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get project analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard analytics
     */
    public function dashboardAnalytics(Request $request): JsonResponse
    {
        try {
            $userId = $request->get('user_id');
            $role = $request->get('role', 'all');

            // Get user-specific data if user_id provided
            $userQuery = $userId ? function($query) use ($userId) {
                $query->where('assigned_to', $userId);
            } : null;

            // Task statistics
            $taskStats = Task::query();
            if ($userQuery) {
                $taskStats->where($userQuery);
            }

            $totalTasks = $taskStats->count();
            $myTasks = $userId ? Task::where('assignee_id', $userId)->count() : 0;
            $completedTasks = (clone $taskStats)->select(['id', 'name', 'status'])->where('status', 'completed')->count();
            $overdueTasks = (clone $taskStats)->select(['id', 'name', 'status'])->where('end_date', '<', now())->select(['id', 'name', 'status'])->where('status', '!=', 'completed')->count();

            // Project statistics
            $projectStats = Project::query();
            if ($userId && $role === 'project_manager') {
                $projectStats->select(['id', 'name', 'status'])->where('pm_id', $userId);
            }

            $totalProjects = $projectStats->count();
            $activeProjects = (clone $projectStats)->select(['id', 'name', 'status'])->where('status', 'active')->count();
            $completedProjects = (clone $projectStats)->select(['id', 'name', 'status'])->where('status', 'completed')->count();

            // Recent activities
            $recentTasks = Task::with(['project', 'assignee'])
                ->orderBy('updated_at', 'desc')
                ->limit(10)
                ->with(['user', 'project'])->get()
                ->map(function($task) {
                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'status' => $task->status,
                        'project_name' => $task->project->name ?? 'N/A',
                        'assignee_name' => $task->assignee->name ?? 'Unassigned',
                        'updated_at' => $task->updated_at->format('Y-m-d H:i:s')
                    ];
                });

            $recentProjects = Project::with(['client', 'pm'])
                ->orderBy('updated_at', 'desc')
                ->limit(10)
                ->with(['user', 'project'])->get()
                ->map(function($project) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'status' => $project->status,
                        'client_name' => $project->client->name ?? 'N/A',
                        'pm_name' => $project->pm->name ?? 'N/A',
                        'progress' => $project->progress,
                        'updated_at' => $project->updated_at->format('Y-m-d H:i:s')
                    ];
                });

            // Performance metrics
            $performanceMetrics = [
                'task_completion_rate' => $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 2) : 0,
                'project_completion_rate' => $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100, 2) : 0,
                'overdue_rate' => $totalTasks > 0 ? round(($overdueTasks / $totalTasks) * 100, 2) : 0
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_tasks' => $totalTasks,
                        'my_tasks' => $myTasks,
                        'completed_tasks' => $completedTasks,
                        'overdue_tasks' => $overdueTasks,
                        'total_projects' => $totalProjects,
                        'active_projects' => $activeProjects,
                        'completed_projects' => $completedProjects
                    ],
                    'performance' => $performanceMetrics,
                    'recent_activities' => [
                        'tasks' => $recentTasks,
                        'projects' => $recentProjects
                    ],
                    'user_context' => [
                        'user_id' => $userId,
                        'role' => $role
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get dashboard analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get productivity metrics
     */
    public function productivityMetrics(Request $request): JsonResponse
    {
        try {
            $dateFrom = $request->get('date_from', Carbon::now()->subDays(30)->format('Y-m-d'));
            $dateTo = $request->get('date_to', Carbon::now()->format('Y-m-d'));
            $userId = $request->get('user_id');

            $query = Task::query()->whereBetween('tasks.created_at', [$dateFrom, $dateTo]);
            
            if ($userId) {
                $query->select(['id', 'name', 'status'])->where('assignee_id', $userId);
            }

            // Time-based productivity
            $totalEstimatedHours = $query->sum('estimated_hours');
            $totalActualHours = $query->sum('actual_hours');
            $averageTaskDuration = $query->avg(DB::raw('TIMESTAMPDIFF(HOUR, created_at, updated_at)'));

            // Completion metrics
            $tasksCompleted = (clone $query)->select(['id', 'name', 'status'])->where('status', 'completed')->count();
            $totalTasks = $query->count();
            $completionRate = $totalTasks > 0 ? round(($tasksCompleted / $totalTasks) * 100, 2) : 0;

            // Quality metrics
            $tasksOnTime = (clone $query)
                ->select(['id', 'name', 'status'])->where('status', 'completed')
                ->whereRaw('updated_at <= due_date')
                ->count();
            
            $onTimeRate = $tasksCompleted > 0 ? round(($tasksOnTime / $tasksCompleted) * 100, 2) : 0;

            // Daily productivity
            $dailyProductivity = (clone $query)
                ->select(
                    DB::raw('DATE(updated_at) as date'),
                    DB::raw('count(*) as tasks_completed'),
                    DB::raw('sum(actual_hours) as hours_logged')
                )
                ->select(['id', 'name', 'status'])->where('status', 'completed')
                ->groupBy(DB::raw('DATE(updated_at)'))
                ->orderBy('date')
                ->with(['user', 'project'])->get()
                ->map(function($item) {
                    return [
                        'date' => $item->date,
                        'tasks_completed' => $item->tasks_completed,
                        'hours_logged' => $item->hours_logged
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'time_tracking' => [
                        'total_estimated_hours' => $totalEstimatedHours,
                        'total_actual_hours' => $totalActualHours,
                        'efficiency_rate' => $totalEstimatedHours > 0 ? round(($totalActualHours / $totalEstimatedHours) * 100, 2) : 0,
                        'average_task_duration_hours' => round((float)$averageTaskDuration, 2)
                    ],
                    'completion' => [
                        'tasks_completed' => $tasksCompleted,
                        'total_tasks' => $totalTasks,
                        'completion_rate' => $completionRate
                    ],
                    'quality' => [
                        'tasks_on_time' => $tasksOnTime,
                        'on_time_rate' => $onTimeRate
                    ],
                    'daily_productivity' => $dailyProductivity,
                    'date_range' => [
                        'from' => $dateFrom,
                        'to' => $dateTo
                    ],
                    'user_context' => [
                        'user_id' => $userId
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get productivity metrics: ' . $e->getMessage()
            ], 500);
        }
    }
}