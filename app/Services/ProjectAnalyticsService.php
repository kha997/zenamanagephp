<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Carbon\Carbon;

/**
 * ProjectAnalyticsService - Advanced analytics for projects
 */
class ProjectAnalyticsService
{
    /**
     * Get comprehensive project analytics
     */
    public function getProjectAnalytics(string $projectId): array
    {
        $project = Project::with(['tasks', 'milestones', 'teamMembers'])->find($projectId);
        
        if (!$project) {
            throw new \InvalidArgumentException('Project not found');
        }

        return [
            'overview' => $this->getProjectOverview($project),
            'performance' => $this->getProjectPerformance($project),
            'timeline' => $this->getTimelineAnalytics($project),
            'team' => $this->getTeamAnalytics($project),
            'budget' => $this->getBudgetAnalytics($project),
            'risks' => $this->getRiskAssessment($project),
            'milestones' => $this->getMilestoneAnalytics($project),
            'tasks' => $this->getTaskAnalytics($project)
        ];
    }

    /**
     * Get project overview metrics
     */
    private function getProjectOverview(Project $project): array
    {
        $tasks = $project->tasks;
        $milestones = $project->milestones;
        
        return [
            'progress' => $project->progress,
            'status' => $project->status,
            'priority' => $project->priority,
            'is_overdue' => $project->isOverdue(),
            'days_remaining' => $project->getDaysRemaining(),
            'duration' => $project->duration,
            'team_size' => $project->teamMembers->count(),
            'total_tasks' => $tasks->count(),
            'completed_tasks' => $tasks->where('status', 'completed')->count(),
            'total_milestones' => $milestones->count(),
            'completed_milestones' => $milestones->where('status', 'completed')->count(),
            'budget_utilization' => $project->getBudgetUtilization()
        ];
    }

    /**
     * Get project performance metrics
     */
    private function getProjectPerformance(Project $project): array
    {
        $tasks = $project->tasks;
        $milestones = $project->milestones;
        
        // Calculate velocity (tasks completed per week)
        $velocity = $this->calculateVelocity($project);
        
        // Calculate efficiency
        $efficiency = $this->calculateEfficiency($project);
        
        // Calculate quality score
        $qualityScore = $this->calculateQualityScore($project);
        
        return [
            'velocity' => $velocity,
            'efficiency' => $efficiency,
            'quality_score' => $qualityScore,
            'task_completion_rate' => $tasks->count() > 0 
                ? round(($tasks->where('status', 'completed')->count() / $tasks->count()) * 100, 2)
                : 0,
            'milestone_completion_rate' => $milestones->count() > 0 
                ? round(($milestones->where('status', 'completed')->count() / $milestones->count()) * 100, 2)
                : 0,
            'on_time_delivery' => $this->calculateOnTimeDelivery($project)
        ];
    }

    /**
     * Get timeline analytics
     */
    private function getTimelineAnalytics(Project $project): array
    {
        $tasks = $project->tasks;
        $milestones = $project->milestones;
        
        return [
            'project_duration' => $project->duration,
            'elapsed_time' => $this->calculateElapsedTime($project),
            'remaining_time' => $this->calculateRemainingTime($project),
            'timeline_status' => $this->getTimelineStatus($project),
            'critical_path' => $this->identifyCriticalPath($tasks),
            'schedule_variance' => $this->calculateScheduleVariance($project),
            'milestone_timeline' => $this->getMilestoneTimeline($milestones)
        ];
    }

    /**
     * Get team analytics
     */
    private function getTeamAnalytics(Project $project): array
    {
        $teamMembers = $project->teamMembers;
        $tasks = $project->tasks;
        
        $workloadDistribution = $this->calculateWorkloadDistribution($tasks);
        $teamProductivity = $this->calculateTeamProductivity($project);
        
        return [
            'team_size' => $teamMembers->count(),
            'workload_distribution' => $workloadDistribution,
            'team_productivity' => $teamProductivity,
            'collaboration_index' => $this->calculateCollaborationIndex($project),
            'skill_utilization' => $this->calculateSkillUtilization($teamMembers),
            'team_satisfaction' => $this->calculateTeamSatisfaction($project)
        ];
    }

    /**
     * Get budget analytics
     */
    private function getBudgetAnalytics(Project $project): array
    {
        return [
            'budget_planned' => $project->budget_planned,
            'budget_actual' => $project->budget_actual,
            'budget_remaining' => $project->budget_planned - $project->budget_actual,
            'budget_utilization' => $project->getBudgetUtilization(),
            'cost_per_task' => $this->calculateCostPerTask($project),
            'cost_variance' => $this->calculateCostVariance($project),
            'budget_trend' => $this->getBudgetTrend($project),
            'forecasted_cost' => $this->forecastFinalCost($project)
        ];
    }

    /**
     * Get risk assessment
     */
    private function getRiskAssessment(Project $project): array
    {
        $risks = [];
        
        // Schedule risks
        if ($project->isOverdue()) {
            $risks[] = [
                'type' => 'schedule',
                'severity' => 'high',
                'description' => 'Project is overdue',
                'impact' => 'Delayed delivery'
            ];
        }
        
        // Budget risks
        if ($project->getBudgetUtilization() > 90) {
            $risks[] = [
                'type' => 'budget',
                'severity' => 'high',
                'description' => 'Budget utilization exceeds 90%',
                'impact' => 'Potential cost overrun'
            ];
        }
        
        // Resource risks
        if ($project->teamMembers->count() < 2) {
            $risks[] = [
                'type' => 'resource',
                'severity' => 'medium',
                'description' => 'Limited team size',
                'impact' => 'Reduced capacity'
            ];
        }
        
        // Progress risks
        if ($project->progress < 25 && $project->getDaysRemaining() < 30) {
            $risks[] = [
                'type' => 'progress',
                'severity' => 'high',
                'description' => 'Low progress with limited time remaining',
                'impact' => 'Risk of incomplete delivery'
            ];
        }
        
        return [
            'total_risks' => count($risks),
            'high_risks' => count(array_filter($risks, fn($r) => $r['severity'] === 'high')),
            'medium_risks' => count(array_filter($risks, fn($r) => $r['severity'] === 'medium')),
            'low_risks' => count(array_filter($risks, fn($r) => $r['severity'] === 'low')),
            'risks' => $risks,
            'risk_score' => $this->calculateRiskScore($risks)
        ];
    }

    /**
     * Get milestone analytics
     */
    private function getMilestoneAnalytics(Project $project): array
    {
        $milestones = $project->milestones;
        
        return [
            'total_milestones' => $milestones->count(),
            'completed_milestones' => $milestones->where('status', 'completed')->count(),
            'pending_milestones' => $milestones->where('status', 'pending')->count(),
            'overdue_milestones' => $milestones->where('status', 'overdue')->count(),
            'completion_rate' => $milestones->count() > 0 
                ? round(($milestones->where('status', 'completed')->count() / $milestones->count()) * 100, 2)
                : 0,
            'average_delay' => $this->calculateAverageMilestoneDelay($milestones),
            'upcoming_milestones' => $this->getUpcomingMilestones($milestones),
            'milestone_trend' => $this->getMilestoneTrend($milestones)
        ];
    }

    /**
     * Get task analytics
     */
    private function getTaskAnalytics(Project $project): array
    {
        $tasks = $project->tasks;
        
        return [
            'total_tasks' => $tasks->count(),
            'completed_tasks' => $tasks->where('status', 'completed')->count(),
            'in_progress_tasks' => $tasks->where('status', 'in_progress')->count(),
            'pending_tasks' => $tasks->where('status', 'pending')->count(),
            'overdue_tasks' => $tasks->where('due_date', '<', now())->where('status', '!=', 'completed')->count(),
            'completion_rate' => $tasks->count() > 0 
                ? round(($tasks->where('status', 'completed')->count() / $tasks->count()) * 100, 2)
                : 0,
            'average_task_duration' => $this->calculateAverageTaskDuration($tasks),
            'task_distribution' => $this->getTaskDistribution($tasks),
            'task_trend' => $this->getTaskTrend($tasks)
        ];
    }

    /**
     * Calculate project velocity
     */
    private function calculateVelocity(Project $project): float
    {
        $tasks = $project->tasks()->where('status', 'completed')->get();
        
        if ($tasks->isEmpty()) {
            return 0.0;
        }
        
        $firstCompletion = $tasks->min('updated_at');
        $lastCompletion = $tasks->max('updated_at');
        
        if (!$firstCompletion || !$lastCompletion) {
            return 0.0;
        }
        
        $weeks = $firstCompletion->diffInWeeks($lastCompletion);
        if ($weeks <= 0) {
            return $tasks->count();
        }
        
        return round($tasks->count() / $weeks, 2);
    }

    /**
     * Calculate project efficiency
     */
    private function calculateEfficiency(Project $project): float
    {
        $plannedDuration = $project->duration;
        $actualDuration = $this->calculateElapsedTime($project);
        
        if ($plannedDuration <= 0) {
            return 0.0;
        }
        
        return round(($plannedDuration / $actualDuration) * 100, 2);
    }

    /**
     * Calculate quality score
     */
    private function calculateQualityScore(Project $project): float
    {
        $tasks = $project->tasks;
        $milestones = $project->milestones;
        
        $taskQuality = $tasks->count() > 0 
            ? ($tasks->where('status', 'completed')->count() / $tasks->count()) * 100
            : 100;
            
        $milestoneQuality = $milestones->count() > 0 
            ? ($milestones->where('status', 'completed')->count() / $milestones->count()) * 100
            : 100;
        
        return round(($taskQuality + $milestoneQuality) / 2, 2);
    }

    /**
     * Calculate on-time delivery percentage
     */
    private function calculateOnTimeDelivery(Project $project): float
    {
        $milestones = $project->milestones()->where('status', 'completed')->get();
        
        if ($milestones->isEmpty()) {
            return 0.0;
        }
        
        $onTimeCount = $milestones->filter(function ($milestone) {
            return $milestone->completed_date <= $milestone->target_date;
        })->count();
        
        return round(($onTimeCount / $milestones->count()) * 100, 2);
    }

    /**
     * Calculate elapsed time
     */
    private function calculateElapsedTime(Project $project): int
    {
        if (!$project->start_date) {
            return 0;
        }
        
        return $project->start_date->diffInDays(now());
    }

    /**
     * Calculate remaining time
     */
    private function calculateRemainingTime(Project $project): ?int
    {
        if (!$project->end_date) {
            return null;
        }
        
        return max(0, now()->diffInDays($project->end_date));
    }

    /**
     * Get timeline status
     */
    private function getTimelineStatus(Project $project): string
    {
        if (!$project->start_date || !$project->end_date) {
            return 'no_timeline';
        }
        
        $now = now();
        
        if ($now->lt($project->start_date)) {
            return 'not_started';
        } elseif ($now->gt($project->end_date)) {
            return 'overdue';
        } else {
            $totalDays = $project->start_date->diffInDays($project->end_date);
            $elapsedDays = $project->start_date->diffInDays($now);
            $progress = $totalDays > 0 ? ($elapsedDays / $totalDays) * 100 : 0;
            
            if ($progress < 25) return 'early';
            if ($progress < 75) return 'on_track';
            return 'late';
        }
    }

    /**
     * Identify critical path
     */
    private function identifyCriticalPath(Collection $tasks): array
    {
        // Simplified critical path calculation
        // In a real implementation, this would use proper CPM algorithm
        return $tasks->where('priority', 'high')
                    ->where('status', '!=', 'completed')
                    ->sortBy('due_date')
                    ->take(5)
                    ->map(function ($task) {
                        return [
                            'id' => $task->id,
                            'name' => $task->name,
                            'due_date' => $task->due_date,
                            'priority' => $task->priority
                        ];
                    })
                    ->toArray();
    }

    /**
     * Calculate schedule variance
     */
    private function calculateScheduleVariance(Project $project): float
    {
        if (!$project->end_date) {
            return 0.0;
        }
        
        $plannedEnd = $project->end_date;
        $currentProgress = $project->progress;
        
        if ($currentProgress <= 0) {
            return 0.0;
        }
        
        $expectedEnd = $project->start_date->addDays(
            $project->start_date->diffInDays($plannedEnd) / ($currentProgress / 100)
        );
        
        return round($plannedEnd->diffInDays($expectedEnd), 1);
    }

    /**
     * Get milestone timeline
     */
    private function getMilestoneTimeline(Collection $milestones): array
    {
        return $milestones->sortBy('target_date')
                         ->map(function ($milestone) {
                             return [
                                 'id' => $milestone->id,
                                 'name' => $milestone->name,
                                 'target_date' => $milestone->target_date,
                                 'status' => $milestone->status,
                                 'is_overdue' => $milestone->isOverdue()
                             ];
                         })
                         ->toArray();
    }

    /**
     * Calculate workload distribution
     */
    private function calculateWorkloadDistribution(Collection $tasks): array
    {
        $distribution = $tasks->groupBy('assigned_to')
                             ->map(function ($userTasks) {
                                 return $userTasks->count();
                             })
                             ->toArray();
        
        $total = array_sum($distribution);
        
        return array_map(function ($count) use ($total) {
            return $total > 0 ? round(($count / $total) * 100, 2) : 0;
        }, $distribution);
    }

    /**
     * Calculate team productivity
     */
    private function calculateTeamProductivity(Project $project): float
    {
        $tasks = $project->tasks;
        $teamSize = $project->teamMembers->count();
        
        if ($teamSize === 0) {
            return 0.0;
        }
        
        $completedTasks = $tasks->where('status', 'completed')->count();
        $elapsedDays = $this->calculateElapsedTime($project);
        
        if ($elapsedDays <= 0) {
            return 0.0;
        }
        
        return round(($completedTasks / $teamSize) / $elapsedDays, 2);
    }

    /**
     * Calculate collaboration index
     */
    private function calculateCollaborationIndex(Project $project): float
    {
        // Simplified collaboration index based on task assignments
        $tasks = $project->tasks;
        $teamSize = $project->teamMembers->count();
        
        if ($teamSize <= 1) {
            return 0.0;
        }
        
        $assignedTasks = $tasks->whereNotNull('assigned_to')->count();
        $totalTasks = $tasks->count();
        
        if ($totalTasks === 0) {
            return 0.0;
        }
        
        return round(($assignedTasks / $totalTasks) * 100, 2);
    }

    /**
     * Calculate skill utilization
     */
    private function calculateSkillUtilization(Collection $teamMembers): float
    {
        // Simplified skill utilization calculation
        // In a real implementation, this would consider actual skills and task requirements
        return round(($teamMembers->count() / 10) * 100, 2); // Assuming optimal team size is 10
    }

    /**
     * Calculate team satisfaction
     */
    private function calculateTeamSatisfaction(Project $project): float
    {
        // Simplified satisfaction calculation based on project health
        $healthScore = 0;
        
        if ($project->progress > 50) $healthScore += 25;
        if (!$project->isOverdue()) $healthScore += 25;
        if ($project->getBudgetUtilization() < 80) $healthScore += 25;
        if ($project->teamMembers->count() > 1) $healthScore += 25;
        
        return $healthScore;
    }

    /**
     * Calculate cost per task
     */
    private function calculateCostPerTask(Project $project): float
    {
        $taskCount = $project->tasks->count();
        
        if ($taskCount === 0) {
            return 0.0;
        }
        
        return round($project->budget_actual / $taskCount, 2);
    }

    /**
     * Calculate cost variance
     */
    private function calculateCostVariance(Project $project): float
    {
        if ($project->budget_planned <= 0) {
            return 0.0;
        }
        
        return round((($project->budget_actual - $project->budget_planned) / $project->budget_planned) * 100, 2);
    }

    /**
     * Get budget trend
     */
    private function getBudgetTrend(Project $project): array
    {
        // Simplified budget trend calculation
        // In a real implementation, this would track budget over time
        return [
            'planned' => $project->budget_planned,
            'actual' => $project->budget_actual,
            'forecasted' => $this->forecastFinalCost($project)
        ];
    }

    /**
     * Forecast final cost
     */
    private function forecastFinalCost(Project $project): float
    {
        if ($project->progress <= 0) {
            return $project->budget_planned;
        }
        
        return round(($project->budget_actual / $project->progress) * 100, 2);
    }

    /**
     * Calculate risk score
     */
    private function calculateRiskScore(array $risks): float
    {
        $score = 0;
        
        foreach ($risks as $risk) {
            switch ($risk['severity']) {
                case 'high':
                    $score += 3;
                    break;
                case 'medium':
                    $score += 2;
                    break;
                case 'low':
                    $score += 1;
                    break;
            }
        }
        
        return min(100, $score * 10); // Scale to 0-100
    }

    /**
     * Calculate average milestone delay
     */
    private function calculateAverageMilestoneDelay(Collection $milestones): float
    {
        $completedMilestones = $milestones->where('status', 'completed')
                                         ->whereNotNull('target_date')
                                         ->whereNotNull('completed_date');

        if ($completedMilestones->isEmpty()) {
            return 0.0;
        }

        $totalDelay = $completedMilestones->sum(function ($milestone) {
            return $milestone->target_date->diffInDays($milestone->completed_date, false);
        });

        return round($totalDelay / $completedMilestones->count(), 1);
    }

    /**
     * Get upcoming milestones
     */
    private function getUpcomingMilestones(Collection $milestones): array
    {
        return $milestones->where('status', 'pending')
                         ->where('target_date', '>=', now())
                         ->sortBy('target_date')
                         ->take(5)
                         ->map(function ($milestone) {
                             return [
                                 'id' => $milestone->id,
                                 'name' => $milestone->name,
                                 'target_date' => $milestone->target_date,
                                 'days_remaining' => $milestone->getDaysUntilTarget()
                             ];
                         })
                         ->toArray();
    }

    /**
     * Get milestone trend
     */
    private function getMilestoneTrend(Collection $milestones): array
    {
        // Simplified trend calculation
        return [
            'completed_this_week' => $milestones->where('status', 'completed')
                                               ->where('completed_date', '>=', now()->subWeek())
                                               ->count(),
            'completed_this_month' => $milestones->where('status', 'completed')
                                                ->where('completed_date', '>=', now()->subMonth())
                                                ->count()
        ];
    }

    /**
     * Calculate average task duration
     */
    private function calculateAverageTaskDuration(Collection $tasks): float
    {
        $completedTasks = $tasks->where('status', 'completed')
                               ->whereNotNull('created_at')
                               ->whereNotNull('updated_at');

        if ($completedTasks->isEmpty()) {
            return 0.0;
        }

        $totalDuration = $completedTasks->sum(function ($task) {
            return $task->created_at->diffInDays($task->updated_at);
        });

        return round($totalDuration / $completedTasks->count(), 1);
    }

    /**
     * Get task distribution
     */
    private function getTaskDistribution(Collection $tasks): array
    {
        return [
            'by_status' => $tasks->groupBy('status')->map->count()->toArray(),
            'by_priority' => $tasks->groupBy('priority')->map->count()->toArray(),
            'by_assignee' => $tasks->groupBy('assigned_to')->map->count()->toArray()
        ];
    }

    /**
     * Get task trend
     */
    private function getTaskTrend(Collection $tasks): array
    {
        return [
            'completed_this_week' => $tasks->where('status', 'completed')
                                          ->where('updated_at', '>=', now()->subWeek())
                                          ->count(),
            'completed_this_month' => $tasks->where('status', 'completed')
                                           ->where('updated_at', '>=', now()->subMonth())
                                           ->count()
        ];
    }
}
