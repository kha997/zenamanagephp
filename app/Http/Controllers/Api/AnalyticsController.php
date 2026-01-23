<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;

class AnalyticsController extends Controller
{
    /**
     * Get tasks analytics
     */
    public function getTasksAnalytics(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', '30d'); // 7d, 30d, 90d, 1y
            
            // Calculate date range
            $dateRange = $this->getDateRange($period);
            
            // Basic statistics
            $totalTasks = Task::count();
            $completedTasks = Task::where('status', 'completed')->count();
            $inProgressTasks = Task::where('status', 'in_progress')->count();
            $pendingTasks = Task::where('status', 'pending')->count();
            $overdueTasks = Task::where('end_date', '<', now())->where('status', '!=', 'completed')->count();
            
            // Progress statistics
            $avgProgress = Task::avg('progress_percent') ?? 0;
            $totalEstimatedHours = Task::sum('estimated_hours') ?? 0;
            $totalActualHours = Task::sum('actual_hours') ?? 0;
            $efficiencyRate = $totalEstimatedHours > 0 ? ($totalActualHours / $totalEstimatedHours) * 100 : 0;
            
            // Time-based statistics
            $tasksCreatedInPeriod = Task::whereBetween('created_at', $dateRange)->count();
            $tasksCompletedInPeriod = Task::where('status', 'completed')
                ->whereBetween('updated_at', $dateRange)
                ->count();
            
            // Priority distribution
            $priorityStats = Task::select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray();
            
            // Status distribution
            $statusStats = Task::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
            
            // Project statistics
            $totalProjects = Project::count();
            $activeProjects = Project::where('status', 'active')->count();
            $avgTasksPerProject = $totalProjects > 0 ? $totalTasks / $totalProjects : 0;
            
            // Recent activity
            $recentTasks = Task::with('project')
                ->orderBy('updated_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function($task) {
                    return [
                        'id' => $task->id,
                        'name' => $task->name,
                        'status' => $task->status,
                        'project_name' => $task->project->name ?? 'N/A',
                        'updated_at' => $task->updated_at
                    ];
                });
            
            // Performance metrics
            $onTrackTasks = Task::where('progress_percent', '>=', 75)->count();
            $behindScheduleTasks = Task::where('progress_percent', '<', 50)->count();
            $atRiskTasks = Task::where('end_date', '<', now()->addDays(7))
                ->where('status', '!=', 'completed')
                ->count();

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
                    'progress' => [
                        'average_progress' => round($avgProgress, 2),
                        'total_estimated_hours' => $totalEstimatedHours,
                        'total_actual_hours' => $totalActualHours,
                        'efficiency_rate' => round($efficiencyRate, 2),
                        'on_track_tasks' => $onTrackTasks,
                        'behind_schedule_tasks' => $behindScheduleTasks,
                        'at_risk_tasks' => $atRiskTasks
                    ],
                    'distribution' => [
                        'priority' => $priorityStats,
                        'status' => $statusStats
                    ],
                    'projects' => [
                        'total_projects' => $totalProjects,
                        'active_projects' => $activeProjects,
                        'average_tasks_per_project' => round($avgTasksPerProject, 2)
                    ],
                    'period_stats' => [
                        'period' => $period,
                        'tasks_created' => $tasksCreatedInPeriod,
                        'tasks_completed' => $tasksCompletedInPeriod
                    ],
                    'recent_activity' => $recentTasks
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Analytics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get projects analytics
     */
    public function getProjectsAnalytics(Request $request): JsonResponse
    {
        try {
            $period = $request->input('period', '30d');
            $dateRange = $this->getDateRange($period);
            
            // Basic statistics
            $totalProjects = Project::count();
            $activeProjects = Project::where('status', 'active')->count();
            $completedProjects = Project::where('status', 'completed')->count();
            $planningProjects = Project::where('status', 'planning')->count();
            
            // Progress statistics
            $avgProgress = Project::avg('progress') ?? 0;
            $totalBudget = Project::sum('budget_total') ?? 0;
            $totalBudgetPlanned = Project::sum('budget_planned') ?? 0;
            $totalBudgetActual = Project::sum('budget_actual') ?? 0;
            
            // Task statistics per project
            $projectTaskStats = Project::withCount(['tasks', 'tasks as completed_tasks_count' => function($query) {
                $query->where('status', 'completed');
            }])->get();
            
            $avgTasksPerProject = $projectTaskStats->avg('tasks_count') ?? 0;
            $avgCompletionRate = $projectTaskStats->avg(function($project) {
                return $project->tasks_count > 0 ? ($project->completed_tasks_count / $project->tasks_count) * 100 : 0;
            });
            
            // Status distribution
            $statusStats = Project::select('status', DB::raw('count(*) as count'))
                ->groupBy('status')
                ->pluck('count', 'status')
                ->toArray();
            
            // Priority distribution
            $priorityStats = Project::select('priority', DB::raw('count(*) as count'))
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray();
            
            // Recent projects
            $recentProjects = Project::withCount('tasks')
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get()
                ->map(function($project) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'code' => $project->code,
                        'status' => $project->status,
                        'progress' => $project->progress,
                        'tasks_count' => $project->tasks_count,
                        'created_at' => $project->created_at
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_projects' => $totalProjects,
                        'active_projects' => $activeProjects,
                        'completed_projects' => $completedProjects,
                        'planning_projects' => $planningProjects,
                        'completion_rate' => $totalProjects > 0 ? round(($completedProjects / $totalProjects) * 100, 2) : 0
                    ],
                    'progress' => [
                        'average_progress' => round($avgProgress, 2),
                        'total_budget' => $totalBudget,
                        'total_budget_planned' => $totalBudgetPlanned,
                        'total_budget_actual' => $totalBudgetActual,
                        'budget_utilization' => $totalBudgetPlanned > 0 ? round(($totalBudgetActual / $totalBudgetPlanned) * 100, 2) : 0
                    ],
                    'tasks' => [
                        'average_tasks_per_project' => round($avgTasksPerProject, 2),
                        'average_completion_rate' => round($avgCompletionRate, 2)
                    ],
                    'distribution' => [
                        'status' => $statusStats,
                        'priority' => $priorityStats
                    ],
                    'recent_projects' => $recentProjects
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Projects analytics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load projects analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get dashboard analytics (combined)
     */
    public function getDashboardAnalytics(Request $request): JsonResponse
    {
        try {
            $tasksAnalytics = $this->getTasksAnalytics($request);
            $projectsAnalytics = $this->getProjectsAnalytics($request);
            
            if (!$tasksAnalytics->getData()->success || !$projectsAnalytics->getData()->success) {
                throw new \Exception('Failed to load analytics data');
            }
            
            return response()->json([
                'success' => true,
                'data' => [
                    'tasks' => $tasksAnalytics->getData()->data,
                    'projects' => $projectsAnalytics->getData()->data,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            \Log::error('Dashboard analytics error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate date range based on period
     */
    private function getDateRange(string $period): array
    {
        $endDate = now();
        
        switch ($period) {
            case '7d':
                $startDate = $endDate->copy()->subDays(7);
                break;
            case '30d':
                $startDate = $endDate->copy()->subDays(30);
                break;
            case '90d':
                $startDate = $endDate->copy()->subDays(90);
                break;
            case '1y':
                $startDate = $endDate->copy()->subYear();
                break;
            default:
                $startDate = $endDate->copy()->subDays(30);
        }
        
        return [$startDate, $endDate];
    }
}