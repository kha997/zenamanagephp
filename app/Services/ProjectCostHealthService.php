<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Traits\ServiceBaseTrait;

/**
 * ProjectCostHealthService
 * 
 * Round 226: Project Cost Health Status + Alert Indicators
 * 
 * Computes cost health status for projects based on:
 * - forecast_final_cost vs budget_total
 * - pending_change_orders_total
 * - variance_vs_budget
 * 
 * Health statuses:
 * - OVER_BUDGET (RED): forecast_final_cost > budget_total
 * - AT_RISK (ORANGE): forecast_final_cost <= budget_total AND pending_change_orders_total > 0 AND variance_vs_budget > -5% of budget_total
 * - ON_BUDGET (BLUE): variance_vs_budget between -5% and 0 AND pending_change_orders_total == 0
 * - UNDER_BUDGET (GREEN): variance_vs_budget < -5% of budget_total
 */
class ProjectCostHealthService
{
    use ServiceBaseTrait;

    public function __construct(
        private ProjectCostDashboardService $costDashboardService
    ) {}

    /**
     * Get project cost health
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return array Cost health data
     */
    public function getCostHealth(string $tenantId, Project $project): array
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Reuse Round 223 dashboard service to get variance data
        $dashboard = $this->costDashboardService->getProjectCostDashboard($tenantId, $project);
        
        $budgetTotal = (float) ($dashboard['summary']['budget_total'] ?? 0.0);
        $forecastFinalCost = (float) ($dashboard['variance']['forecast_final_cost'] ?? 0.0);
        $varianceVsBudget = (float) ($dashboard['variance']['variance_vs_budget'] ?? 0.0);
        $pendingChangeOrdersTotal = (float) ($dashboard['variance']['pending_change_orders_total'] ?? 0.0);

        // Compute health status
        $healthStatus = $this->computeHealthStatus(
            $budgetTotal,
            $forecastFinalCost,
            $varianceVsBudget,
            $pendingChangeOrdersTotal
        );

        return [
            'project_id' => $project->id,
            'cost_health_status' => $healthStatus,
            'stats' => [
                'budget_total' => $budgetTotal,
                'forecast_final_cost' => $forecastFinalCost,
                'variance_vs_budget' => $varianceVsBudget,
                'pending_change_orders_total' => $pendingChangeOrdersTotal,
            ],
        ];
    }

    /**
     * Compute health status based on cost metrics
     * 
     * Rules (in order):
     * 1. OVER_BUDGET: forecast_final_cost > budget_total
     * 2. AT_RISK: forecast_final_cost <= budget_total AND pending_change_orders_total > 0 AND variance_vs_budget > -5% of budget_total
     * 3. ON_BUDGET: variance_vs_budget between -5% and 0 AND pending_change_orders_total == 0
     * 4. UNDER_BUDGET: variance_vs_budget < -5% of budget_total
     * 5. Default: ON_BUDGET if budget_total = 0
     * 
     * @param float $budgetTotal Budget total
     * @param float $forecastFinalCost Forecast final cost
     * @param float $varianceVsBudget Variance vs budget
     * @param float $pendingChangeOrdersTotal Pending change orders total
     * @return string Health status
     */
    private function computeHealthStatus(
        float $budgetTotal,
        float $forecastFinalCost,
        float $varianceVsBudget,
        float $pendingChangeOrdersTotal
    ): string {
        // If budget_total = 0, default to ON_BUDGET
        if ($budgetTotal == 0.0) {
            return 'ON_BUDGET';
        }

        // Rule 1: OVER_BUDGET (RED)
        if ($forecastFinalCost > $budgetTotal) {
            return 'OVER_BUDGET';
        }

        // Rule 2: AT_RISK (ORANGE)
        // forecast_final_cost <= budget_total AND pending_change_orders_total > 0 AND variance_vs_budget > -5% of budget_total
        $fivePercentBuffer = -0.05 * $budgetTotal;
        if (
            $forecastFinalCost <= $budgetTotal &&
            $pendingChangeOrdersTotal > 0 &&
            $varianceVsBudget > $fivePercentBuffer
        ) {
            return 'AT_RISK';
        }

        // Rule 3: ON_BUDGET (BLUE)
        // variance_vs_budget between -5% and 0 AND pending_change_orders_total == 0
        if (
            $varianceVsBudget >= $fivePercentBuffer &&
            $varianceVsBudget <= 0 &&
            $pendingChangeOrdersTotal == 0
        ) {
            return 'ON_BUDGET';
        }

        // Rule 4: UNDER_BUDGET (GREEN)
        // variance_vs_budget < -5% of budget_total
        if ($varianceVsBudget < $fivePercentBuffer) {
            return 'UNDER_BUDGET';
        }

        // Default fallback (should not reach here, but safety net)
        return 'ON_BUDGET';
    }
}
