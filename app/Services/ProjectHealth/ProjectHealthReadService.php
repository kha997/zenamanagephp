<?php

declare(strict_types=1);

namespace App\Services\ProjectHealth;

use App\Models\Baseline;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\Project;
use App\Models\ProjectMilestone;
use App\Models\Task;
use App\Models\Treasury\TreasuryWallet;
use App\Services\ProjectDelayStatus;
use App\Services\Treasury\TreasuryBalanceService;
use App\Services\Treasury\TreasuryDashboardService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GAP-103: the one shared read of a project's health (OWN-2026-009 §4) — progress with its basis and reliability, schedule
 * state, attention flags and money measures that stay separate (§8). Every measure says whether it has data; missing data is
 * never reported as 0, 0% or on track. Read-only, tenant- and project-scoped, and a fixed number of aggregate queries.
 */
class ProjectHealthReadService
{
    public const AVAILABLE = 'available';
    public const NO_DATA = 'no_data';
    public const NOT_APPLICABLE = 'not_applicable';

    public const BASIS_COMPONENT_COST_WEIGHTED = 'component_cost_weighted';
    public const BASIS_TASK_AVERAGE = 'task_average';

    private const CLOSED_TASK_STATUSES = [Task::STATUS_COMPLETED, Task::STATUS_CANCELLED];

    public function __construct(
        private readonly TreasuryBalanceService $treasuryBalances,
        private readonly TreasuryDashboardService $treasuryDashboard,
    ) {
    }

    /**
     * @return array{progress: array<string, mixed>, schedule: array<string, mixed>, attention: array<string, mixed>, finance: array<string, mixed>}
     */
    public function forProject(Project $project): array
    {
        // One clock for every section: ProjectDelayStatus reads Carbon::now() itself, so no caller-supplied date here.
        $today = Carbon::now()->startOfDay();
        $hasTreasury = TreasuryWallet::query()->withoutGlobalScopes()
            ->where('tenant_id', $this->tenantId($project))
            ->where('project_id', $this->projectId($project))
            ->exists();
        $schedule = $this->schedule($project, $today);

        return [
            'progress' => $this->progress($project),
            'schedule' => $schedule,
            'attention' => $this->attention($project, $today, $schedule, $hasTreasury),
            'finance' => $this->finance($project, $hasTreasury),
        ];
    }

    /**
     * Gate 2 P-A: root components weighted by planned cost; otherwise the average of active tasks (limited reliability).
     * Public so a progress-only consumer (the project page, C-1) does not pay for the Treasury alert read.
     *
     * @return array{value: float|null, availability: string, basis: string|null, reliability: string|null}
     */
    public function progress(Project $project): array
    {
        $components = DB::table('components')
            ->where('tenant_id', $this->tenantId($project))
            ->where('project_id', $this->projectId($project))
            ->whereNull('parent_component_id')
            ->where('planned_cost', '>', 0)
            ->selectRaw('COALESCE(SUM(progress_percent * planned_cost), 0) AS weighted, COALESCE(SUM(planned_cost), 0) AS total')
            ->first();
        $componentTotal = (float) data_get($components, 'total', 0);

        if ($componentTotal > 0) {
            return [
                'value' => round((float) data_get($components, 'weighted', 0) / $componentTotal, 2),
                'availability' => self::AVAILABLE,
                'basis' => self::BASIS_COMPONENT_COST_WEIGHTED,
                'reliability' => 'normal',
            ];
        }

        $tasks = $this->tasks($project)
            ->where('status', '!=', Task::STATUS_CANCELLED)
            ->selectRaw('COUNT(*) AS task_count, COALESCE(AVG(progress_percent), 0) AS average')
            ->first();

        if ((int) data_get($tasks, 'task_count', 0) > 0) {
            return [
                'value' => round((float) data_get($tasks, 'average', 0), 2),
                'availability' => self::AVAILABLE,
                'basis' => self::BASIS_TASK_AVERAGE,
                'reliability' => 'limited',
            ];
        }

        return ['value' => null, 'availability' => self::NO_DATA, 'basis' => null, 'reliability' => null];
    }

    /**
     * @return array{delay: array{state: string, days_late: int|null, baseline: Baseline|null}, milestones: array{availability: string, total: int, completed: int, overdue: int}}
     */
    private function schedule(Project $project, Carbon $today): array
    {
        /** @var Baseline|null $baseline */
        $baseline = Baseline::query()
            ->where('project_id', $this->projectId($project))
            ->orderByDesc('created_at')
            ->first();

        // project_milestones has no tenant_id: scoped through the already tenant-checked project.
        $milestones = DB::table('project_milestones')
            ->where('project_id', $this->projectId($project))
            ->where('status', '!=', ProjectMilestone::STATUS_CANCELLED)
            ->selectRaw(
                'COUNT(*) AS total, '
                . 'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed, '
                . 'SUM(CASE WHEN status = ? OR (status = ? AND target_date < ?) THEN 1 ELSE 0 END) AS overdue',
                [ProjectMilestone::STATUS_COMPLETED, ProjectMilestone::STATUS_OVERDUE, ProjectMilestone::STATUS_PENDING, $today->toDateString()]
            )
            ->first();
        $total = (int) data_get($milestones, 'total', 0);

        return [
            'delay' => ProjectDelayStatus::evaluate($project, $baseline),
            'milestones' => [
                'availability' => $total > 0 ? self::AVAILABLE : self::NO_DATA,
                'total' => $total,
                'completed' => (int) data_get($milestones, 'completed', 0),
                'overdue' => (int) data_get($milestones, 'overdue', 0),
            ],
        ];
    }

    /**
     * @param array{delay: array{state: string}, milestones: array{overdue: int}} $schedule
     * @return array<string, mixed>
     */
    private function attention(Project $project, Carbon $today, array $schedule, bool $hasTreasury): array
    {
        $tasks = $this->tasks($project)
            ->whereNotIn('status', self::CLOSED_TASK_STATUSES)
            ->selectRaw(
                'SUM(CASE WHEN blocked_at IS NOT NULL THEN 1 ELSE 0 END) AS blocked, '
                . 'SUM(CASE WHEN end_date IS NOT NULL AND end_date < ? THEN 1 ELSE 0 END) AS overdue',
                [$today->toDateTimeString()]
            )
            ->first();

        $blockedDesignItems = DB::table('design_items')
            ->where('tenant_id', $this->tenantId($project))
            ->where('project_id', $this->projectId($project))
            ->whereNotNull('blocked_at')
            ->count();

        $treasuryAlerts = ['availability' => self::NOT_APPLICABLE, 'count' => null];
        if ($hasTreasury) {
            $treasuryAlerts = ['availability' => self::AVAILABLE, 'count' => count($this->treasuryDashboard->dashboard($project, $today)['alerts'])];
        }

        return [
            'blocked_tasks' => (int) data_get($tasks, 'blocked', 0),
            'blocked_design_items' => $blockedDesignItems,
            'overdue_tasks' => (int) data_get($tasks, 'overdue', 0),
            'overdue_milestones' => $schedule['milestones']['overdue'],
            'schedule_late' => in_array($schedule['delay']['state'], [ProjectDelayStatus::STATE_LATE, ProjectDelayStatus::STATE_FORECAST_LATE], true),
            'treasury_alerts' => $treasuryAlerts,
        ];
    }

    /**
     * Separate money measures (OWN-2026-009 §8): never summed into a profit or net figure.
     *
     * @return array<string, mixed>
     */
    private function finance(Project $project, bool $hasTreasury): array
    {
        $contracts = DB::table('contracts')
            ->where('tenant_id', $this->tenantId($project))
            ->where('project_id', $this->projectId($project))
            ->selectRaw(
                'COUNT(*) AS contract_count, '
                . 'SUM(CASE WHEN status != ? THEN 1 ELSE 0 END) AS live_count, '
                . 'COALESCE(SUM(CASE WHEN status != ? THEN total_value ELSE 0 END), 0) AS live_value',
                [Contract::STATUS_CANCELLED, Contract::STATUS_CANCELLED]
            )
            ->first();
        $hasContracts = (int) data_get($contracts, 'contract_count', 0) > 0;
        $budget = (float) ($project->budget_total ?? 0);

        $finance = [
            'budget' => $budget > 0
                ? $this->measure(self::AVAILABLE, $budget, 'budget_total')
                : $this->measure(self::NO_DATA, null, 'budget_total'),
            'contract_value' => (int) data_get($contracts, 'live_count', 0) > 0
                ? $this->measure(self::AVAILABLE, (float) data_get($contracts, 'live_value', 0), 'contracts_not_cancelled')
                : $this->measure(self::NO_DATA, null, 'contracts_not_cancelled'),
            'cash_received' => $this->measure(self::NO_DATA, null, 'contract_payments_paid'),
            'cost_incurred' => $this->measure(self::NO_DATA, null, 'cost_recorded_not_cash_paid'),
            'treasury' => ['availability' => self::NOT_APPLICABLE, 'held_total' => null, 'posted_expenses' => null],
        ];

        if ($hasContracts) {
            $paid = DB::table('contract_payments')
                ->where('tenant_id', $this->tenantId($project))
                ->where('status', ContractPayment::STATUS_PAID)
                ->whereIn('contract_id', $this->contractIds($project))
                ->sum('amount');
            $cost = DB::table('contract_expenses')
                ->where('tenant_id', $this->tenantId($project))
                ->whereIn('contract_id', $this->contractIds($project))
                ->sum('amount');
            $finance['cash_received'] = $this->measure(self::AVAILABLE, (float) $paid, 'contract_payments_paid');
            $finance['cost_incurred'] = $this->measure(self::AVAILABLE, (float) $cost, 'cost_recorded_not_cash_paid');
        }

        if ($hasTreasury) {
            $summary = $this->treasuryBalances->projectSummary($project);
            $finance['treasury'] = [
                'availability' => self::AVAILABLE,
                'held_total' => $summary['held_total'],
                'posted_expenses' => $summary['expenses'],
            ];
        }

        return $finance;
    }

    /**
     * @return array{value: string|null, availability: string, basis: string}
     */
    private function measure(string $availability, ?float $value, string $basis): array
    {
        return [
            'value' => $value === null ? null : number_format($value, 2, '.', ''),
            'availability' => $availability,
            'basis' => $basis,
        ];
    }

    private function tasks(Project $project): Builder
    {
        return DB::table('tasks')
            ->whereNull('deleted_at')
            ->where('tenant_id', $this->tenantId($project))
            ->where('project_id', $this->projectId($project));
    }

    private function contractIds(Project $project): Builder
    {
        return DB::table('contracts')
            ->where('tenant_id', $this->tenantId($project))
            ->where('project_id', $this->projectId($project))
            ->select('id');
    }

    private function tenantId(Project $project): string
    {
        return (string) $project->getAttribute('tenant_id');
    }

    private function projectId(Project $project): string
    {
        return (string) $project->getKey();
    }
}
