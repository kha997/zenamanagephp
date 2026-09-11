<?php

namespace App\Services\Dashboard;

use App\Models\Inspection;
use App\Models\NCR;
use App\Models\Project;
use App\Models\Rfi;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

final class RoleBasedWidgetDataCalculator
{
    public function projectOverview(User $user, ?string $projectId = null): array
    {
        $query = Project::where('tenant_id', $user->tenant_id);
        if ($projectId) {
            $query->whereKey($projectId);
        } elseif ($user->role !== 'system_admin') {
            $query->whereHas('projectUsers', fn ($q) => $q->where('user_id', $user->id));
        }

        $projects = $query->get();
        $overview = [
            'total_projects' => $projects->count(),
            'active_projects' => $projects->where('status', 'active')->count(),
            'completed_projects' => $projects->where('status', 'completed')->count(),
            'total_budget' => $projects->sum('budget'),
            'spent_budget' => $projects->sum('spent_amount'),
            'recent_projects' => $projects->take(5)->map(fn ($project) => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'progress' => $project->progress_percentage,
                'budget' => $project->budget,
                'spent' => $project->spent_amount,
            ]),
        ];

        if ($user->role === 'project_manager') {
            $overview['overdue_tasks'] = Task::whereIn('project_id', $projects->pluck('id'))
                ->where('due_date', '<', now())->where('status', '!=', 'completed')->count();
        } elseif ($user->role === 'site_engineer') {
            $overview['daily_inspections'] = Inspection::whereIn('project_id', $projects->pluck('id'))
                ->whereDate('scheduled_date', today())->count();
        } elseif ($user->role === 'qc_inspector') {
            $overview['pending_ncrs'] = NCR::whereIn('project_id', $projects->pluck('id'))
                ->where('status', 'open')->count();
        }

        return $overview;
    }

    public function taskProgress(User $user, ?string $projectId = null): array
    {
        $query = Task::where('tenant_id', $user->tenant_id);
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        if ($user->role === 'project_manager') {
            $query->whereHas('project.projectUsers', fn ($q) => $q->where('user_id', $user->id));
        } elseif ($user->role === 'site_engineer') {
            $query->where('assigned_to', $user->id);
        } elseif ($user->role === 'design_lead') {
            $query->where('category', 'design');
        } elseif ($user->role === 'qc_inspector') {
            $query->where('category', 'quality');
        }

        $tasks = $query->get();
        return [
            'total_tasks' => $tasks->count(),
            'completed_tasks' => $tasks->where('status', 'completed')->count(),
            'in_progress_tasks' => $tasks->where('status', 'in_progress')->count(),
            'pending_tasks' => $tasks->where('status', 'pending')->count(),
            'overdue_tasks' => $tasks->where('due_date', '<', now())->where('status', '!=', 'completed')->count(),
            'completion_rate' => $tasks->count() > 0 ? round(($tasks->where('status', 'completed')->count() / $tasks->count()) * 100, 2) : 0,
            'recent_tasks' => $tasks->sortByDesc('created_at')->take(10)->map(fn ($task) => [
                'id' => $task->id, 'title' => $task->title, 'status' => $task->status,
                'priority' => $task->priority, 'due_date' => $task->due_date, 'assigned_to' => $task->assigned_to,
            ]),
        ];
    }

    public function rfiStatus(User $user, ?string $projectId = null): array
    {
        $query = Rfi::where('tenant_id', $user->tenant_id);
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        if ($user->role === 'project_manager') {
            $query->whereHas('project.projectUsers', fn ($q) => $q->where('user_id', $user->id));
        } elseif ($user->role === 'design_lead') {
            $query->where('discipline', 'design');
        } elseif ($user->role === 'site_engineer') {
            $query->where('discipline', 'construction');
        }

        $rfis = $query->get();
        return [
            'total_rfis' => $rfis->count(),
            'open_rfis' => $rfis->where('status', 'open')->count(),
            'answered_rfis' => $rfis->where('status', 'answered')->count(),
            'closed_rfis' => $rfis->where('status', 'closed')->count(),
            'overdue_rfis' => $rfis->where('due_date', '<', now())->where('status', '!=', 'closed')->count(),
            'average_response_time' => $this->averageResponseTime($rfis),
            'recent_rfis' => $rfis->sortByDesc('created_at')->take(10)->map(fn ($rfi) => [
                'id' => $rfi->id, 'subject' => $rfi->subject, 'status' => $rfi->status,
                'priority' => $rfi->priority, 'due_date' => $rfi->due_date, 'discipline' => $rfi->discipline,
            ]),
        ];
    }

    public function budgetTracking(User $user, ?string $projectId = null): array
    {
        $query = Project::where('tenant_id', $user->tenant_id);
        if ($projectId) {
            $query->whereKey($projectId);
        } elseif ($user->role !== 'system_admin') {
            $query->whereHas('projectUsers', fn ($q) => $q->where('user_id', $user->id));
        }
        $projects = $query->get();
        $totalBudget = $projects->sum('budget');
        $totalSpent = $projects->sum('spent_amount');
        return [
            'total_budget' => $totalBudget,
            'total_spent' => $totalSpent,
            'remaining_budget' => $totalBudget - $totalSpent,
            'budget_utilization' => $totalBudget > 0 ? round(($totalSpent / $totalBudget) * 100, 2) : 0,
            'budget_variance' => $this->budgetVariance($projects),
            'monthly_spending' => ['current_month' => 0, 'previous_month' => 0, 'monthly_trend' => 'stable'],
            'top_expense_categories' => [
                ['category' => 'Labor', 'amount' => 0, 'percentage' => 0],
                ['category' => 'Materials', 'amount' => 0, 'percentage' => 0],
                ['category' => 'Equipment', 'amount' => 0, 'percentage' => 0],
            ],
            'budget_alerts' => $this->budgetAlerts($projects),
        ];
    }

    public function scheduleTimeline(User $user, ?string $projectId = null): array
    {
        return ['milestones' => [], 'critical_path' => [], 'schedule_variance' => 0];
    }

    public function teamPerformance(User $user, ?string $projectId = null): array
    {
        return ['team_members' => [], 'performance_metrics' => [], 'productivity_trend' => 'stable'];
    }

    public function qualityMetrics(User $user, ?string $projectId = null): array
    {
        return ['quality_score' => 0, 'defect_rate' => 0, 'inspection_completion' => 0];
    }

    public function safetySummary(User $user, ?string $projectId = null): array
    {
        return ['safety_score' => 0, 'incidents_count' => 0, 'days_since_last_incident' => 0];
    }

    public function inspectionSchedule(User $user, ?string $projectId = null): array
    {
        return ['scheduled_inspections' => [], 'completed_inspections' => [], 'overdue_inspections' => []];
    }

    public function ncrTracking(User $user, ?string $projectId = null): array
    {
        return ['open_ncrs' => [], 'closed_ncrs' => [], 'average_resolution_time' => 0];
    }

    public function systemHealth(User $user): array
    {
        return ['system_uptime' => 99.9, 'active_users' => 0, 'storage_usage' => 0];
    }

    public function userManagement(User $user): array
    {
        return ['total_users' => 0, 'active_users' => 0, 'new_users_this_month' => 0];
    }

    private function averageResponseTime(Collection $rfis): float
    {
        $times = $rfis->where('status', 'closed')->map(fn ($rfi) => $rfi->answered_at ? $rfi->answered_at->diffInHours($rfi->created_at) : 0)->filter()->values();
        return $times->count() > 0 ? $times->avg() : 0;
    }

    private function budgetVariance(Collection $projects): array
    {
        $variances = $projects->map(function ($project) {
            $planned = $project->budget;
            $actual = $project->spent_amount;
            $variance = $actual - $planned;
            return [
                'project_id' => $project->id, 'project_name' => $project->name,
                'planned' => $planned, 'actual' => $actual, 'variance' => $variance,
                'variance_percentage' => $planned > 0 ? ($variance / $planned) * 100 : 0,
            ];
        });
        return [
            'total_variance' => $variances->sum('variance'),
            'average_variance_percentage' => $variances->avg('variance_percentage'),
            'projects_over_budget' => $variances->where('variance', '>', 0)->count(),
            'projects_under_budget' => $variances->where('variance', '<', 0)->count(),
        ];
    }

    private function budgetAlerts(Collection $projects): array
    {
        $alerts = [];
        foreach ($projects as $project) {
            $utilization = $project->budget > 0 ? ($project->spent_amount / $project->budget) * 100 : 0;
            if ($utilization > 80) {
                $alerts[] = [
                    'project_id' => $project->id, 'project_name' => $project->name,
                    'type' => 'budget_warning', 'message' => 'Budget utilization exceeds 80%', 'severity' => 'medium',
                ];
            }
        }
        return $alerts;
    }
}
