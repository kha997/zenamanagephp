<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Models\ChangeRequest;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PmDashboardController extends Controller
{
    use ZenaContractResponseTrait;

    /**
     * Get PM dashboard overview.
     */
    public function getOverview(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        
        // Get PM's projects
        $projects = Project::whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });

        if ($projectId) {
            $projects->where('id', $projectId);
        }

        $projects = $projects->get();

        $overview = [
            'projects' => $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'progress' => $this->calculateProjectProgress($project->id),
                    'budget' => $project->budget,
                    'start_date' => $project->start_date,
                    'end_date' => $project->end_date,
                ];
            }),
            'summary' => [
                'total_projects' => $projects->count(),
                'active_projects' => $projects->where('status', 'active')->count(),
                'completed_projects' => $projects->where('status', 'completed')->count(),
                'total_tasks' => Task::whereIn('project_id', $projects->pluck('id'))->count(),
                'completed_tasks' => Task::whereIn('project_id', $projects->pluck('id'))
                    ->where('status', 'completed')->count(),
                'pending_rfis' => Rfi::whereIn('project_id', $projects->pluck('id'))
                    ->where('status', 'pending')->count(),
                'overdue_tasks' => Task::whereIn('project_id', $projects->pluck('id'))
                    ->where('due_date', '<', now())
                    ->where('status', '!=', 'completed')->count(),
            ],
            'recent_activities' => $this->getRecentActivities($projects->pluck('id')->toArray()),
            'upcoming_milestones' => $this->getUpcomingMilestones($projects->pluck('id')->toArray()),
        ];

        return $this->zenaSuccessResponse($overview);
    }

    /**
     * Get project progress data.
     */
    public function getProjectProgress(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        
        if (!$projectId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project ID is required',
            ], 400);
        }

        // Verify PM has access to this project
        $project = Project::where('id', $projectId)
            ->whereHas('users', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->first();

        if (!$project) {
            return response()->json([
                'status' => 'error',
                'message' => 'Project not found or access denied',
            ], 404);
        }

        $progress = [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
            ],
            'overall_progress' => $this->calculateProjectProgress($projectId),
            'task_progress' => $this->getTaskProgress($projectId),
            'milestone_progress' => $this->getMilestoneProgress($projectId),
            'budget_progress' => $this->getBudgetProgress($projectId),
            'timeline_progress' => $this->getTimelineProgress($projectId),
        ];

        return $this->zenaSuccessResponse($progress);
    }

    /**
     * Get risk assessment data.
     */
    public function getRiskAssessment(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        
        // Get PM's projects
        $projects = Project::whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });

        if ($projectId) {
            $projects->where('id', $projectId);
        }

        $projects = $projects->get();

        $risks = [
            'high_risk_tasks' => $this->getHighRiskTasks($projects->pluck('id')->toArray()),
            'overdue_items' => $this->getOverdueItems($projects->pluck('id')->toArray()),
            'budget_risks' => $this->getBudgetRisks($projects->pluck('id')->toArray()),
            'resource_conflicts' => $this->getResourceConflicts($projects->pluck('id')->toArray()),
            'quality_issues' => $this->getQualityIssues($projects->pluck('id')->toArray()),
        ];

        return $this->zenaSuccessResponse($risks);
    }

    /**
     * Get weekly report data.
     */
    public function getWeeklyReport(Request $request): JsonResponse
    {
        $user = Auth::guard('sanctum')->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized',
            ], 401);
        }

        $projectId = $request->input('project_id');
        $weekStart = $request->input('week_start', now()->startOfWeek());
        
        // Get PM's projects
        $projects = Project::whereHas('users', function ($q) use ($user) {
            $q->where('user_id', $user->id);
        });

        if ($projectId) {
            $projects->where('id', $projectId);
        }

        $projects = $projects->get();

        $report = [
            'week_period' => [
                'start' => $weekStart,
                'end' => now()->endOfWeek(),
            ],
            'projects_summary' => $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'name' => $project->name,
                    'status' => $project->status,
                    'progress' => $project->progress_percentage ?? 0,
                    'tasks_completed' => $project->tasks()->where('status', 'completed')->count(),
                    'total_tasks' => $project->tasks()->count(),
                ];
            }),
            'overall_metrics' => [
                'total_tasks_completed' => Task::whereIn('project_id', $projects->pluck('id'))
                    ->where('status', 'completed')
                    ->whereBetween('updated_at', [$weekStart, now()->endOfWeek()])
                    ->count(),
                'total_rfis_resolved' => Rfi::whereIn('project_id', $projects->pluck('id'))
                    ->where('status', 'resolved')
                    ->whereBetween('updated_at', [$weekStart, now()->endOfWeek()])
                    ->count(),
                'total_change_requests' => ChangeRequest::whereIn('project_id', $projects->pluck('id'))
                    ->whereBetween('created_at', [$weekStart, now()->endOfWeek()])
                    ->count(),
            ],
        ];

        return $this->zenaSuccessResponse($report);
    }

    /**
     * Calculate project progress percentage.
     */
    private function calculateProjectProgress(string $projectId): float
    {
        $totalTasks = Task::where('project_id', $projectId)->count();
        
        if ($totalTasks === 0) {
            return 0.0;
        }

        $completedTasks = Task::where('project_id', $projectId)
            ->where('status', 'completed')
            ->count();

        return round(($completedTasks / $totalTasks) * 100, 2);
    }

    /**
     * Get task progress breakdown.
     */
    private function getTaskProgress(string $projectId): array
    {
        $tasks = Task::where('project_id', $projectId)->get();
        
        return [
            'total' => $tasks->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'pending' => $tasks->where('status', 'pending')->count(),
            'overdue' => $tasks->where('due_date', '<', now())
                ->where('status', '!=', 'completed')->count(),
        ];
    }

    /**
     * Get milestone progress.
     */
    private function getMilestoneProgress(string $projectId): array
    {
        // Sample data - in real implementation, this would come from milestones table
        return [
            'total_milestones' => 5,
            'completed_milestones' => 2,
            'upcoming_milestones' => [
                [
                    'name' => 'Foundation Complete',
                    'due_date' => now()->addDays(7),
                    'progress' => 85,
                ],
                [
                    'name' => 'Structural Frame',
                    'due_date' => now()->addDays(14),
                    'progress' => 45,
                ],
            ],
        ];
    }

    /**
     * Get budget progress.
     */
    private function getBudgetProgress(string $projectId): array
    {
        $project = Project::find($projectId);
        
        // Sample data - in real implementation, this would calculate from actual costs
        return [
            'total_budget' => $project->budget ?? 0,
            'spent_amount' => ($project->budget ?? 0) * 0.65, // 65% spent
            'remaining_amount' => ($project->budget ?? 0) * 0.35,
            'percentage_spent' => 65,
        ];
    }

    /**
     * Get timeline progress.
     */
    private function getTimelineProgress(string $projectId): array
    {
        $project = Project::find($projectId);
        
        if (!$project || !$project->start_date || !$project->end_date) {
            return [
                'start_date' => null,
                'end_date' => null,
                'days_elapsed' => 0,
                'total_days' => 0,
                'percentage_elapsed' => 0,
            ];
        }

        $startDate = $project->start_date;
        $endDate = $project->end_date;
        $now = now();
        
        $totalDays = $startDate->diffInDays($endDate);
        $elapsedDays = $startDate->diffInDays($now);
        $percentageElapsed = $totalDays > 0 ? round(($elapsedDays / $totalDays) * 100, 2) : 0;

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'days_elapsed' => $elapsedDays,
            'total_days' => $totalDays,
            'percentage_elapsed' => min($percentageElapsed, 100),
        ];
    }

    /**
     * Get recent activities.
     */
    private function getRecentActivities(array $projectIds): array
    {
        // Sample data - in real implementation, this would come from activity log
        return [
            [
                'id' => '1',
                'type' => 'task_completed',
                'description' => 'Task "Foundation Design" completed',
                'project_id' => $projectIds[0] ?? null,
                'user' => 'John Doe',
                'timestamp' => now()->subHours(2),
            ],
            [
                'id' => '2',
                'type' => 'rfi_created',
                'description' => 'New RFI created for Project Alpha',
                'project_id' => $projectIds[0] ?? null,
                'user' => 'Jane Smith',
                'timestamp' => now()->subHours(4),
            ],
        ];
    }

    /**
     * Get upcoming milestones.
     */
    private function getUpcomingMilestones(array $projectIds): array
    {
        // Sample data - in real implementation, this would come from milestones table
        return [
            [
                'id' => '1',
                'name' => 'Foundation Complete',
                'project_id' => $projectIds[0] ?? null,
                'due_date' => now()->addDays(7),
                'progress' => 85,
            ],
            [
                'id' => '2',
                'name' => 'Structural Frame',
                'project_id' => $projectIds[0] ?? null,
                'due_date' => now()->addDays(14),
                'progress' => 45,
            ],
        ];
    }

    /**
     * Get high risk tasks.
     */
    private function getHighRiskTasks(array $projectIds): array
    {
        return Task::whereIn('project_id', $projectIds)
            ->where('priority', 'high')
            ->where('status', '!=', 'completed')
            ->where('due_date', '<', now()->addDays(3))
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'project_id' => $task->project_id,
                    'due_date' => $task->due_date,
                    'priority' => $task->priority,
                    'status' => $task->status,
                ];
            })
            ->toArray();
    }

    /**
     * Get overdue items.
     */
    private function getOverdueItems(array $projectIds): array
    {
        return Task::whereIn('project_id', $projectIds)
            ->where('due_date', '<', now())
            ->where('status', '!=', 'completed')
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->id,
                    'title' => $task->title,
                    'project_id' => $task->project_id,
                    'due_date' => $task->due_date,
                    'days_overdue' => now()->diffInDays($task->due_date),
                ];
            })
            ->toArray();
    }

    /**
     * Get budget risks.
     */
    private function getBudgetRisks(array $projectIds): array
    {
        // Sample data - in real implementation, this would analyze actual vs budgeted costs
        return [
            [
                'project_id' => $projectIds[0] ?? null,
                'risk_type' => 'budget_overrun',
                'description' => 'Project Alpha is 15% over budget',
                'severity' => 'medium',
            ],
        ];
    }

    /**
     * Get resource conflicts.
     */
    private function getResourceConflicts(array $projectIds): array
    {
        // Sample data - in real implementation, this would analyze resource allocation
        return [
            [
                'resource' => 'John Doe',
                'conflict_type' => 'double_assignment',
                'projects' => $projectIds,
                'description' => 'Resource assigned to multiple projects simultaneously',
            ],
        ];
    }

    /**
     * Get quality issues.
     */
    private function getQualityIssues(array $projectIds): array
    {
        // Sample data - in real implementation, this would come from QC inspections
        return [
            [
                'project_id' => $projectIds[0] ?? null,
                'issue_type' => 'quality_failure',
                'description' => 'Concrete strength below specification',
                'severity' => 'high',
            ],
        ];
    }
}
