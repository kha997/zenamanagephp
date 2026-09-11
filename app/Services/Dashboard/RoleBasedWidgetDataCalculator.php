<?php

namespace App\Services\Dashboard;

use App\Models\Ncr;
use App\Models\Project;
use App\Models\QcInspection;
use App\Models\Rfi;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

final class RoleBasedWidgetDataCalculator
{
    /** @return array<string, mixed> */
    public function projectOverview(User $user, ?string $projectId = null): array
    {
        $role = (string) $user->getAttribute('role');
        $query = Project::query()->where('tenant_id', $user->tenant_id);
        if ($projectId) {
            $query->whereKey($projectId);
        } elseif ($role !== 'system_admin') {
            $query->whereHas('projectUsers', fn ($q) => $q->where('user_id', $user->id));
        }

        $projects = $query->get();
        $overview = [
            'total_projects' => $projects->count(),
            'active_projects' => $projects->where('status', 'active')->count(),
            'completed_projects' => $projects->where('status', 'completed')->count(),
            'total_budget' => $projects->sum('budget'),
            'spent_budget' => $projects->sum('spent_amount'),
            'recent_projects' => $projects->take(5)->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'status' => $project->status,
                'progress' => $project->getAttribute('progress_percentage'),
                'budget' => $project->getAttribute('budget'),
                'spent' => $project->getAttribute('spent_amount'),
            ]),
        ];

        if ($role === 'project_manager') {
            $overview['overdue_tasks'] = Task::query()->whereIn('project_id', $projects->pluck('id'))
                ->where('due_date', '<', now())->where('status', '!=', 'completed')->count();
        } elseif ($role === 'site_engineer') {
            $overview['daily_inspections'] = QcInspection::query()
                ->whereHas('qcPlan', fn ($query) => $query->whereIn('project_id', $projects->pluck('id')))
                ->whereDate('inspection_date', today())->count();
        } elseif ($role === 'qc_inspector') {
            $overview['pending_ncrs'] = Ncr::query()->whereIn('project_id', $projects->pluck('id'))
                ->where('status', 'open')->count();
        }

        return $overview;
    }

    /** @return array<string, mixed> */
    public function taskProgress(User $user, ?string $projectId = null): array
    {
        $role = (string) $user->getAttribute('role');
        $query = Task::query()->where('tenant_id', $user->tenant_id);
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        if ($role === 'project_manager') {
            $query->whereHas('project.projectUsers', fn ($q) => $q->where('user_id', $user->id));
        } elseif ($role === 'site_engineer') {
            $query->where('assigned_to', $user->id);
        } elseif ($role === 'design_lead') {
            $query->where('category', 'design');
        } elseif ($role === 'qc_inspector') {
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
            'recent_tasks' => $tasks->sortByDesc('created_at')->take(10)->map(fn (Task $task): array => [
                'id' => $task->id, 'title' => $task->getAttribute('title'), 'status' => $task->status,
                'priority' => $task->priority, 'due_date' => $task->getAttribute('due_date'),
                'assigned_to' => $task->getAttribute('assigned_to'),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public function rfiStatus(User $user, ?string $projectId = null): array
    {
        $role = (string) $user->getAttribute('role');
        $query = Rfi::query()->where('tenant_id', $user->tenant_id);
        if ($projectId) {
            $query->where('project_id', $projectId);
        }
        if ($role === 'project_manager') {
            $query->whereHas('project.projectUsers', fn ($q) => $q->where('user_id', $user->id));
        } elseif ($role === 'design_lead') {
            $query->where('discipline', 'design');
        } elseif ($role === 'site_engineer') {
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
            'recent_rfis' => $rfis->sortByDesc('created_at')->take(10)->map(fn (Rfi $rfi): array => [
                'id' => $rfi->id, 'subject' => $rfi->subject, 'status' => $rfi->status,
                'priority' => $rfi->priority, 'due_date' => $rfi->due_date,
                'discipline' => $rfi->getAttribute('discipline'),
            ]),
        ];
    }

    /** @return array<string, mixed> */
    public function budgetTracking(User $user, ?string $projectId = null): array
    {
        $role = (string) $user->getAttribute('role');
        $query = Project::query()->where('tenant_id', $user->tenant_id);
        if ($projectId) {
            $query->whereKey($projectId);
        } elseif ($role !== 'system_admin') {
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

    /** @return array<string, mixed> */
    public function scheduleTimeline(User $user, ?string $projectId = null): array
    {
        return ['milestones' => [], 'critical_path' => [], 'schedule_variance' => 0];
    }

    /** @return array<string, mixed> */
    public function teamPerformance(User $user, ?string $projectId = null): array
    {
        return ['team_members' => [], 'performance_metrics' => [], 'productivity_trend' => 'stable'];
    }

    /** @return array<string, mixed> */
    public function qualityMetrics(User $user, ?string $projectId = null): array
    {
        return ['quality_score' => 0, 'defect_rate' => 0, 'inspection_completion' => 0];
    }

    /** @return array<string, mixed> */
    public function safetySummary(User $user, ?string $projectId = null): array
    {
        return ['safety_score' => 0, 'incidents_count' => 0, 'days_since_last_incident' => 0];
    }

    /** @return array<string, mixed> */
    public function inspectionSchedule(User $user, ?string $projectId = null): array
    {
        return ['scheduled_inspections' => [], 'completed_inspections' => [], 'overdue_inspections' => []];
    }

    /** @return array<string, mixed> */
    public function ncrTracking(User $user, ?string $projectId = null): array
    {
        return ['open_ncrs' => [], 'closed_ncrs' => [], 'average_resolution_time' => 0];
    }

    /** @return array<string, mixed> */
    public function systemHealth(User $user): array
    {
        return ['system_uptime' => 99.9, 'active_users' => 0, 'storage_usage' => 0];
    }

    /** @return array<string, mixed> */
    public function userManagement(User $user): array
    {
        return ['total_users' => 0, 'active_users' => 0, 'new_users_this_month' => 0];
    }

    /** @param Collection<int, Rfi> $rfis */
    private function averageResponseTime(Collection $rfis): float
    {
        $times = $rfis->where('status', 'closed')->map(fn ($rfi) => $rfi->answered_at ? $rfi->answered_at->diffInHours($rfi->created_at) : 0)->filter()->values();
        return $times->count() > 0 ? $times->avg() : 0;
    }

    /**
     * @param Collection<int, Project> $projects
     * @return array<string, mixed>
     */
    private function budgetVariance(Collection $projects): array
    {
        $totalVariance = 0.0;
        $totalVariancePercentage = 0.0;
        $projectsOverBudget = 0;
        $projectsUnderBudget = 0;

        foreach ($projects as $project) {
            $planned = (float) $project->getAttribute('budget');
            $actual = (float) $project->getAttribute('spent_amount');
            $variance = $actual - $planned;
            $totalVariance += $variance;
            $totalVariancePercentage += $planned > 0 ? ($variance / $planned) * 100 : 0;
            $projectsOverBudget += $variance > 0 ? 1 : 0;
            $projectsUnderBudget += $variance < 0 ? 1 : 0;
        }

        return [
            'total_variance' => $totalVariance,
            'average_variance_percentage' => $projects->isEmpty()
                ? null
                : $totalVariancePercentage / $projects->count(),
            'projects_over_budget' => $projectsOverBudget,
            'projects_under_budget' => $projectsUnderBudget,
        ];
    }

    /**
     * @param Collection<int, Project> $projects
     * @return list<array<string, mixed>>
     */
    private function budgetAlerts(Collection $projects): array
    {
        $alerts = [];
        foreach ($projects as $project) {
            $budget = (float) $project->getAttribute('budget');
            $spentAmount = (float) $project->getAttribute('spent_amount');
            $utilization = $budget > 0 ? ($spentAmount / $budget) * 100 : 0;
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
