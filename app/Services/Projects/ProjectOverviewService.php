<?php declare(strict_types=1);

namespace App\Services\Projects;

use App\Models\Project;
use App\Models\Task;
use App\Services\ProjectManagementService;
use App\Services\Reports\PortfolioReportsService;
use Carbon\Carbon;

/**
 * Project Overview Service
 * 
 * Round 67: Project Overview Cockpit
 * 
 * Provides a unified overview combining project summary, financial data, and task metrics.
 */
class ProjectOverviewService
{
    public function __construct(
        private readonly ProjectManagementService $projectService,
        private readonly PortfolioReportsService $portfolioReports,
    ) {}

    /**
     * Build project overview
     * 
     * Round 70: Project Health Summary
     * 
     * @param string $tenantId Tenant ID
     * @param string $projectId Project ID
     * @return array Structure:
     *   - project: Project summary
     *   - financials: Financial summary
     *   - tasks: Task summary
     *   - health: Health summary
     */
    public function buildOverview(string $tenantId, string $projectId): array
    {
        // 1) Core project
        $project = $this->projectService->getProjectById($projectId, $tenantId);
        if (!$project) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException("Project not found");
        }

        $project->loadMissing(['client', 'owner']);

        $projectSummary = [
            'id' => (string) $project->id,
            'code' => $project->code,
            'name' => $project->name,
            'status' => $project->status,
            'priority' => $project->priority,
            'risk_level' => $project->risk_level ?? null,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'client' => $project->client ? [
                'id' => (string) $project->client->id,
                'name' => $project->client->name,
            ] : null,
            'owner' => $project->owner ? [
                'id' => (string) $project->owner->id,
                'name' => $project->owner->name,
            ] : null,
        ];

        // 2) Financial summary
        $financials = $this->portfolioReports->getProjectCostSummaryForTenant($tenantId, $projectId);

        $financialSummary = $financials
            ? [
                'has_financial_data' => true,
                'contracts_count' => $financials['contracts_count'],
                'contracts_value_total' => $financials['contracts_value_total'],
                'budget_total' => $financials['budget_total'],
                'actual_total' => $financials['actual_total'],
                'overrun_amount_total' => $financials['overrun_amount_total'],
                'over_budget_contracts_count' => $financials['over_budget_contracts_count'],
                'overrun_contracts_count' => $financials['overrun_contracts_count'],
                'currency' => $financials['currency'],
            ]
            : [
                'has_financial_data' => false,
                'contracts_count' => 0,
                'contracts_value_total' => null,
                'budget_total' => null,
                'actual_total' => null,
                'overrun_amount_total' => null,
                'over_budget_contracts_count' => 0,
                'overrun_contracts_count' => 0,
                'currency' => null,
            ];

        // 3) Task summary
        $taskSummary = $this->buildTaskSummary($tenantId, $projectId);

        // 4) Health summary
        $health = $this->buildHealthSummary($financialSummary, $taskSummary);

        return [
            'project' => $projectSummary,
            'financials' => $financialSummary,
            'tasks' => $taskSummary,
            'health' => $health,
        ];
    }

    /**
     * Build task summary for a project
     * 
     * @param string $tenantId Tenant ID
     * @param string $projectId Project ID
     * @return array Structure:
     *   - total: Total task count
     *   - by_status: Array of counts by status
     *   - overdue: Count of overdue tasks
     *   - due_soon: Count of tasks due within 3 days
     *   - key_tasks: Array of key tasks (overdue, due_soon, blocked)
     */
    private function buildTaskSummary(string $tenantId, string $projectId): array
    {
        $today = Carbon::today(config('app.timezone'));

        $query = Task::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->whereNull('deleted_at');

        $total = (clone $query)->count();

        $byStatus = [];
        foreach (Task::VALID_STATUSES as $status) {
            $byStatus[$status] = (clone $query)->where('status', $status)->count();
        }

        $overdueCount = (clone $query)
            ->whereDate('end_date', '<', $today)
            ->whereNotIn('status', ['done', 'canceled'])
            ->count();

        $dueSoonCount = (clone $query)
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $today->copy()->addDays(3))
            ->whereNotIn('status', ['done', 'canceled'])
            ->count();

        $keyTasks = $this->buildKeyTasksSummary($tenantId, $projectId);

        return [
            'total' => $total,
            'by_status' => $byStatus,
            'overdue' => $overdueCount,
            'due_soon' => $dueSoonCount,
            'key_tasks' => $keyTasks,
        ];
    }

    /**
     * Build key tasks (overdue / due soon / blocked) for a project.
     *
     * @param string $tenantId Tenant ID
     * @param string $projectId Project ID
     * @return array{
     *   overdue: array<int, array{id:string,name:string,status:string,priority:?string,end_date:?string,assignee:?array{id:string,name:string}}>,
     *   due_soon: array<int, array{...same shape...}>,
     *   blocked: array<int, array{...same shape...}>
     * }
     */
    private function buildKeyTasksSummary(string $tenantId, string $projectId): array
    {
        $today = Carbon::today(config('app.timezone'));
        $dueSoonEnd = Carbon::today(config('app.timezone'))->addDays(3);

        $baseQuery = Task::query()
            ->where('tenant_id', $tenantId)
            ->where('project_id', $projectId)
            ->whereNull('deleted_at')
            ->with(['assignee']);

        // Overdue tasks: end_date < today, status NOT IN ['done', 'canceled']
        $overdueTasks = (clone $baseQuery)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '<', $today)
            ->whereNotIn('status', ['done', 'canceled'])
            ->orderBy('end_date', 'ASC')
            ->limit(5)
            ->get()
            ->map(fn(Task $task) => $this->mapTaskToSummary($task))
            ->values()
            ->all();

        // Due soon tasks: end_date >= today AND end_date <= today+3, status NOT IN ['done', 'canceled']
        $dueSoonTasks = (clone $baseQuery)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $dueSoonEnd)
            ->whereNotIn('status', ['done', 'canceled'])
            ->orderBy('end_date', 'ASC')
            ->limit(5)
            ->get()
            ->map(fn(Task $task) => $this->mapTaskToSummary($task))
            ->values()
            ->all();

        // Blocked tasks: status = 'blocked'
        // Order: priority (urgent/high first via CASE mapping), then end_date ASC (nulls last)
        $blockedTasks = (clone $baseQuery)
            ->where('status', 'blocked')
            ->orderByRaw("CASE priority 
                WHEN 'urgent' THEN 1 
                WHEN 'high' THEN 2 
                WHEN 'normal' THEN 3 
                WHEN 'low' THEN 4 
                ELSE 5 
            END ASC")
            ->orderByRaw("CASE WHEN end_date IS NULL THEN 1 ELSE 0 END ASC")
            ->orderBy('end_date', 'ASC')
            ->limit(5)
            ->get()
            ->map(fn(Task $task) => $this->mapTaskToSummary($task))
            ->values()
            ->all();

        return [
            'overdue' => $overdueTasks,
            'due_soon' => $dueSoonTasks,
            'blocked' => $blockedTasks,
        ];
    }

    /**
     * Map Task model to summary DTO
     *
     * @param Task $task Task model
     * @return array{id:string,name:string,status:string,priority:?string,end_date:?string,assignee:?array{id:string,name:string}}
     */
    private function mapTaskToSummary(Task $task): array
    {
        return [
            'id' => (string) $task->id,
            'name' => (string) ($task->title ?? $task->name),
            'status' => (string) $task->status,
            'priority' => $task->priority ?: null,
            'end_date' => $task->end_date?->toDateString(),
            'assignee' => $task->assignee
                ? [
                    'id' => (string) $task->assignee->id,
                    'name' => (string) $task->assignee->name,
                ]
                : null,
        ];
    }

    /**
     * Build health summary for a project
     * 
     * Round 70: Project Health Summary
     * 
     * Calculates project health based on task completion, schedule status, and cost status.
     * Uses existing data from financials and tasks without additional DB queries.
     * 
     * @param array $financials Financial summary data
     * @param array $tasks Task summary data
     * @return array{
     *   tasks_completion_rate: float|null,
     *   blocked_tasks_ratio: float|null,
     *   overdue_tasks: int,
     *   schedule_status: 'on_track'|'at_risk'|'delayed'|'no_tasks',
     *   cost_status: 'on_budget'|'over_budget'|'at_risk'|'no_data',
     *   cost_overrun_percent: float|null,
     *   overall_status: 'good'|'warning'|'critical'
     * }
     */
    private function buildHealthSummary(array $financials, array $tasks): array
    {
        // Extract task data
        $total = (int) ($tasks['total'] ?? 0);
        $byStatus = $tasks['by_status'] ?? [];
        $overdue = (int) ($tasks['overdue'] ?? 0);
        $blocked = (int) ($byStatus['blocked'] ?? 0);
        $done = (int) ($byStatus['done'] ?? 0);
        $canceled = (int) ($byStatus['canceled'] ?? 0);

        // Extract financial data
        $hasFinancialData = (bool) ($financials['has_financial_data'] ?? false);
        $budgetTotal = (float) ($financials['budget_total'] ?? 0);
        $contractsValueTotal = (float) ($financials['contracts_value_total'] ?? 0);
        $overrunAmountTotal = (float) ($financials['overrun_amount_total'] ?? 0);

        // Calculate tasks completion & blocked ratios
        $effectiveTotal = max($total - $canceled, 0);

        $tasksCompletionRate = null;
        if ($effectiveTotal > 0) {
            $tasksCompletionRate = round($done / $effectiveTotal, 4);
        }

        $blockedTasksRatio = null;
        if ($effectiveTotal > 0) {
            $blockedTasksRatio = round($blocked / $effectiveTotal, 4);
        }

        // Calculate schedule status
        $scheduleStatus = 'no_tasks';
        if ($total === 0) {
            $scheduleStatus = 'no_tasks';
        } elseif ($overdue === 0) {
            $scheduleStatus = 'on_track';
        } elseif ($overdue <= 3) {
            $scheduleStatus = 'at_risk';
        } else {
            $scheduleStatus = 'delayed';
        }

        // Calculate cost status
        $base = $budgetTotal > 0 ? $budgetTotal : $contractsValueTotal;
        $costStatus = 'no_data';
        $costOverrunPercent = null;

        if ($hasFinancialData && $base > 0) {
            $costOverrunPercent = ($overrunAmountTotal / $base) * 100;
            $costOverrunPercent = round($costOverrunPercent, 2);

            if ($overrunAmountTotal <= 0) {
                $costStatus = 'on_budget';
            } elseif ($costOverrunPercent <= 10) {
                $costStatus = 'at_risk';
            } else {
                $costStatus = 'over_budget';
            }
        }

        // Calculate overall status
        $overallStatus = 'good';

        if ($scheduleStatus === 'delayed' || $costStatus === 'over_budget') {
            $overallStatus = 'critical';
        } elseif (
            $scheduleStatus === 'at_risk'
            || $costStatus === 'at_risk'
            || $scheduleStatus === 'no_tasks'
            || $costStatus === 'no_data'
        ) {
            $overallStatus = 'warning';
        }

        return [
            'tasks_completion_rate' => $tasksCompletionRate,
            'blocked_tasks_ratio' => $blockedTasksRatio,
            'overdue_tasks' => $overdue,
            'schedule_status' => $scheduleStatus,
            'cost_status' => $costStatus,
            'cost_overrun_percent' => $costOverrunPercent,
            'overall_status' => $overallStatus,
        ];
    }
}

