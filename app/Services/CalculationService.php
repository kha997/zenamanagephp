<?php

namespace App\Services;

use App\Models\ChangeRequest;
use App\Models\Component;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculationService
{
    /**
     * Calculate project progress
     */
    public function calculateProjectProgress(string $projectId): float
    {
        $project = Project::findOrFail($projectId);
        
        // Get all tasks for the project
        $tasks = Task::where('project_id', $projectId)->get();
        
        if ($tasks->isEmpty()) {
            $progress = 0;
        } else {
            $totalWeight = 0;
            $completedWeight = 0;
            
            foreach ($tasks as $task) {
                $weight = $task->weight ?? 1;
                $totalWeight += $weight;
                
                if ($task->status === 'completed') {
                    $completedWeight += $weight;
                } elseif ($task->status === 'in_progress') {
                    $completedWeight += $weight * ($task->progress / 100);
                }
            }
            
            $progress = $totalWeight > 0 ? ($completedWeight / $totalWeight) * 100 : 0;
        }
        
        // Update project progress
        $project->update(['progress' => round($progress, 2)]);
        
        return $progress;
    }

    /**
     * Calculate project budget utilization
     */
    public function calculateProjectBudgetUtilization(string $projectId): array
    {
        $project = Project::findOrFail($projectId);
        
        $totalBudget = $project->budget;
        $actualCost = $project->actual_cost;
        
        // Calculate from tasks
        $taskCosts = Task::where('project_id', $projectId)
            ->where('status', 'completed')
            ->sum('actual_cost');
        
        $totalActualCost = $actualCost + $taskCosts;
        
        $utilization = $totalBudget > 0 ? ($totalActualCost / $totalBudget) * 100 : 0;
        
        return [
            'total_budget' => $totalBudget,
            'actual_cost' => $totalActualCost,
            'remaining_budget' => $totalBudget - $totalActualCost,
            'utilization_percentage' => round($utilization, 2),
            'is_over_budget' => $totalActualCost > $totalBudget,
        ];
    }

    /**
     * Calculate project timeline metrics
     */
    public function calculateProjectTimeline(string $projectId): array
    {
        $project = Project::findOrFail($projectId);
        
        $startDate = $project->start_date;
        $endDate = $project->end_date;
        $now = now();
        
        if (!$startDate || !$endDate) {
            return [
                'total_duration' => null,
                'elapsed_duration' => null,
                'remaining_duration' => null,
                'progress_percentage' => null,
                'is_overdue' => false,
                'estimated_completion' => null,
            ];
        }
        
        $totalDuration = $startDate->diffInDays($endDate);
        $elapsedDuration = $startDate->diffInDays($now);
        $remainingDuration = $now->diffInDays($endDate);
        
        $progressPercentage = $totalDuration > 0 ? ($elapsedDuration / $totalDuration) * 100 : 0;
        $isOverdue = $now->isAfter($endDate);
        
        // Estimate completion based on current progress
        $estimatedCompletion = null;
        if ($project->progress > 0 && $progressPercentage > 0) {
            $estimatedDays = ($elapsedDuration / $project->progress) * 100;
            $estimatedCompletion = $startDate->addDays($estimatedDays);
        }
        
        return [
            'total_duration' => $totalDuration,
            'elapsed_duration' => $elapsedDuration,
            'remaining_duration' => $remainingDuration,
            'progress_percentage' => round($progressPercentage, 2),
            'is_overdue' => $isOverdue,
            'estimated_completion' => $estimatedCompletion?->format('Y-m-d'),
        ];
    }

    /**
     * Calculate task dependencies impact
     */
    public function calculateTaskDependenciesImpact(string $taskId): array
    {
        $task = Task::findOrFail($taskId);
        $dependencies = $task->dependencies_json ?? [];
        
        if (empty($dependencies)) {
            return [
                'blocked_by' => [],
                'blocking' => [],
                'can_start' => true,
                'critical_path' => false,
            ];
        }
        
        $blockedBy = Task::whereIn('id', $dependencies)
            ->where('status', '!=', 'completed')
            ->get();
        
        $blocking = Task::whereJsonContains('dependencies_json', $taskId)
            ->get();
        
        $canStart = $blockedBy->isEmpty();
        
        return [
            'blocked_by' => $blockedBy->map(function($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'status' => $t->status,
                    'end_date' => $t->end_date?->format('Y-m-d'),
                ];
            }),
            'blocking' => $blocking->map(function($t) {
                return [
                    'id' => $t->id,
                    'name' => $t->name,
                    'status' => $t->status,
                ];
            }),
            'can_start' => $canStart,
            'critical_path' => $blocking->isNotEmpty(),
        ];
    }

    /**
     * Calculate team workload
     */
    public function calculateTeamWorkload(string $projectId): array
    {
        $tasks = Task::where('project_id', $projectId)
            ->whereNotNull('user_id')
            ->get();
        
        $workload = [];
        
        foreach ($tasks as $task) {
            $userId = $task->user_id;
            $userName = $task->user?->name ?? 'Unknown';
            
            if (!isset($workload[$userId])) {
                $workload[$userId] = [
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'total_tasks' => 0,
                    'completed_tasks' => 0,
                    'in_progress_tasks' => 0,
                    'pending_tasks' => 0,
                    'overdue_tasks' => 0,
                    'total_weight' => 0,
                    'completed_weight' => 0,
                ];
            }
            
            $workload[$userId]['total_tasks']++;
            $workload[$userId]['total_weight'] += $task->weight ?? 1;
            
            switch ($task->status) {
                case 'completed':
                    $workload[$userId]['completed_tasks']++;
                    $workload[$userId]['completed_weight'] += $task->weight ?? 1;
                    break;
                case 'in_progress':
                    $workload[$userId]['in_progress_tasks']++;
                    break;
                case 'pending':
                    $workload[$userId]['pending_tasks']++;
                    break;
            }
            
            if ($task->end_date && $task->end_date->isPast() && $task->status !== 'completed') {
                $workload[$userId]['overdue_tasks']++;
            }
        }
        
        // Calculate workload percentage
        foreach ($workload as &$userWorkload) {
            $userWorkload['workload_percentage'] = $userWorkload['total_weight'] > 0 
                ? ($userWorkload['completed_weight'] / $userWorkload['total_weight']) * 100 
                : 0;
        }
        
        return array_values($workload);
    }

    /**
     * Calculate change request impact
     */
    public function calculateChangeRequestImpact(string $changeRequestId): array
    {
        $changeRequest = ChangeRequest::findOrFail($changeRequestId);
        
        $impact = [
            'cost_impact' => $changeRequest->cost_impact,
            'time_impact' => $changeRequest->time_impact,
            'risk_level' => $this->calculateRiskLevel($changeRequest),
            'affected_tasks' => [],
            'affected_components' => [],
            'overall_impact' => 'low',
        ];
        
        // Find affected tasks
        if ($changeRequest->task_id) {
            $task = Task::find($changeRequest->task_id);
            if ($task) {
                $impact['affected_tasks'][] = [
                    'id' => $task->id,
                    'name' => $task->name,
                    'status' => $task->status,
                    'end_date' => $task->end_date?->format('Y-m-d'),
                ];
            }
        }
        
        // Find affected components
        if ($changeRequest->component_id) {
            $component = Component::find($changeRequest->component_id);
            if ($component) {
                $impact['affected_components'][] = [
                    'id' => $component->id,
                    'name' => $component->name,
                    'status' => $component->status,
                    'progress' => $component->progress,
                ];
            }
        }
        
        // Calculate overall impact
        $impact['overall_impact'] = $this->calculateOverallImpact($impact);
        
        return $impact;
    }

    /**
     * Calculate risk level for change request
     */
    private function calculateRiskLevel(ChangeRequest $changeRequest): string
    {
        $riskScore = 0;
        
        // Cost impact
        if ($changeRequest->cost_impact > 10000) {
            $riskScore += 3;
        } elseif ($changeRequest->cost_impact > 5000) {
            $riskScore += 2;
        } elseif ($changeRequest->cost_impact > 1000) {
            $riskScore += 1;
        }
        
        // Time impact
        if ($changeRequest->time_impact > 30) {
            $riskScore += 3;
        } elseif ($changeRequest->time_impact > 14) {
            $riskScore += 2;
        } elseif ($changeRequest->time_impact > 7) {
            $riskScore += 1;
        }
        
        // Priority
        switch ($changeRequest->priority) {
            case 'critical':
                $riskScore += 3;
                break;
            case 'high':
                $riskScore += 2;
                break;
            case 'medium':
                $riskScore += 1;
                break;
        }
        
        if ($riskScore >= 6) {
            return 'high';
        } elseif ($riskScore >= 3) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate overall impact
     */
    private function calculateOverallImpact(array $impact): string
    {
        $score = 0;
        
        if ($impact['cost_impact'] > 10000) $score += 2;
        elseif ($impact['cost_impact'] > 5000) $score += 1;
        
        if ($impact['time_impact'] > 30) $score += 2;
        elseif ($impact['time_impact'] > 14) $score += 1;
        
        if ($impact['risk_level'] === 'high') $score += 2;
        elseif ($impact['risk_level'] === 'medium') $score += 1;
        
        if (count($impact['affected_tasks']) > 5) $score += 1;
        if (count($impact['affected_components']) > 3) $score += 1;
        
        if ($score >= 5) {
            return 'high';
        } elseif ($score >= 3) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate project KPIs
     */
    public function calculateProjectKPIs(string $projectId): array
    {
        $project = Project::findOrFail($projectId);
        
        $tasks = Task::where('project_id', $projectId)->get();
        $components = Component::where('project_id', $projectId)->get();
        $changeRequests = ChangeRequest::where('project_id', $projectId)->get();
        
        $kpis = [
            'project_progress' => $project->progress,
            'budget_utilization' => $this->calculateProjectBudgetUtilization($projectId),
            'timeline_metrics' => $this->calculateProjectTimeline($projectId),
            'task_metrics' => [
                'total_tasks' => $tasks->count(),
                'completed_tasks' => $tasks->where('status', 'completed')->count(),
                'in_progress_tasks' => $tasks->where('status', 'in_progress')->count(),
                'pending_tasks' => $tasks->where('status', 'pending')->count(),
                'overdue_tasks' => $tasks->where('end_date', '<', now())->where('status', '!=', 'completed')->count(),
                'completion_rate' => $tasks->count() > 0 ? ($tasks->where('status', 'completed')->count() / $tasks->count()) * 100 : 0,
            ],
            'component_metrics' => [
                'total_components' => $components->count(),
                'completed_components' => $components->where('status', 'completed')->count(),
                'in_progress_components' => $components->where('status', 'in_progress')->count(),
                'pending_components' => $components->where('status', 'pending')->count(),
                'average_progress' => $components->avg('progress') ?? 0,
            ],
            'change_request_metrics' => [
                'total_change_requests' => $changeRequests->count(),
                'pending_change_requests' => $changeRequests->where('status', 'pending')->count(),
                'approved_change_requests' => $changeRequests->where('status', 'approved')->count(),
                'rejected_change_requests' => $changeRequests->where('status', 'rejected')->count(),
                'total_cost_impact' => $changeRequests->sum('cost_impact'),
                'total_time_impact' => $changeRequests->sum('time_impact'),
            ],
            'team_workload' => $this->calculateTeamWorkload($projectId),
        ];
        
        return $kpis;
    }

    /**
     * Recalculate all project metrics
     */
    public function recalculateProjectMetrics(string $projectId): array
    {
        DB::beginTransaction();
        
        try {
            // Recalculate progress
            $this->calculateProjectProgress($projectId);
            
            // Get updated KPIs
            $kpis = $this->calculateProjectKPIs($projectId);
            
            DB::commit();
            
            Log::info("Recalculated metrics for project: {$projectId}");
            
            return $kpis;
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to recalculate project metrics: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate resource utilization
     */
    public function calculateResourceUtilization(string $projectId): array
    {
        $tasks = Task::where('project_id', $projectId)
            ->whereNotNull('user_id')
            ->get();
        
        $utilization = [];
        
        foreach ($tasks as $task) {
            $userId = $task->user_id;
            $userName = $task->user?->name ?? 'Unknown';
            
            if (!isset($utilization[$userId])) {
                $utilization[$userId] = [
                    'user_id' => $userId,
                    'user_name' => $userName,
                    'total_hours' => 0,
                    'completed_hours' => 0,
                    'estimated_hours' => 0,
                    'utilization_percentage' => 0,
                ];
            }
            
            $estimatedHours = $task->estimated_hours ?? 0;
            $actualHours = $task->actual_hours ?? 0;
            
            $utilization[$userId]['total_hours'] += $actualHours;
            $utilization[$userId]['estimated_hours'] += $estimatedHours;
            
            if ($task->status === 'completed') {
                $utilization[$userId]['completed_hours'] += $actualHours;
            }
        }
        
        // Calculate utilization percentage
        foreach ($utilization as &$userUtilization) {
            if ($userUtilization['estimated_hours'] > 0) {
                $userUtilization['utilization_percentage'] = 
                    ($userUtilization['total_hours'] / $userUtilization['estimated_hours']) * 100;
            }
        }
        
        return array_values($utilization);
    }
}
