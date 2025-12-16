<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Contract;
use App\Models\ContractLine;
use App\Models\Project;
use App\Models\ProjectBudgetLine;
use App\Traits\ServiceBaseTrait;
use Illuminate\Support\Facades\DB;

/**
 * ProjectCostSummaryService
 * 
 * Round 222: Project Cost Summary API (Budget vs Contract vs Actual)
 * 
 * Aggregates project-level cost information including:
 * - Overall totals (budget, contract base/current, certified, paid, outstanding)
 * - Per-category breakdown (budget vs contract base)
 */
class ProjectCostSummaryService
{
    use ServiceBaseTrait;

    /**
     * Get project cost summary
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return array Cost summary data
     */
    public function getProjectCostSummary(string $tenantId, Project $project): array
    {
        // Verify project belongs to tenant
        if ($project->tenant_id !== $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Project not found');
        }

        // Compute overall totals
        $totals = $this->computeOverallTotals($tenantId, $project);

        // Build per-category breakdown
        $categories = $this->computePerCategoryBreakdown($tenantId, $project);

        return [
            'project_id' => $project->id,
            'currency' => 'VND', // Default currency, can be enhanced later
            'totals' => $totals,
            'categories' => $categories,
        ];
    }

    /**
     * Compute overall totals for the project
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return array Totals array
     */
    private function computeOverallTotals(string $tenantId, Project $project): array
    {
        // Budget total: sum of amount_budget from project_budget_lines
        $budgetTotal = (float) ProjectBudgetLine::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->sum('amount_budget') ?? 0.0;

        // Get all contracts for this project (not soft-deleted)
        $contracts = Contract::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->whereNull('deleted_at')
            ->get();

        // Contract base total: sum of base_amount
        $contractBaseTotal = (float) $contracts->sum('base_amount') ?? 0.0;

        // Contract current total: sum of current_amount (using accessor)
        $contractCurrentTotal = 0.0;
        foreach ($contracts as $contract) {
            $contractCurrentTotal += $contract->current_amount ?? 0.0;
        }

        // Total certified amount: sum of total_certified_amount (using accessor)
        $totalCertifiedAmount = 0.0;
        foreach ($contracts as $contract) {
            $totalCertifiedAmount += $contract->total_certified_amount ?? 0.0;
        }

        // Total paid amount: sum of total_paid_amount (using accessor)
        $totalPaidAmount = 0.0;
        foreach ($contracts as $contract) {
            $totalPaidAmount += $contract->total_paid_amount ?? 0.0;
        }

        // Outstanding amount: sum of outstanding_amount (using accessor)
        $outstandingAmount = 0.0;
        foreach ($contracts as $contract) {
            $outstandingAmount += $contract->outstanding_amount ?? 0.0;
        }

        return [
            'budget_total' => $budgetTotal,
            'contract_base_total' => $contractBaseTotal,
            'contract_current_total' => $contractCurrentTotal,
            'total_certified_amount' => $totalCertifiedAmount,
            'total_paid_amount' => $totalPaidAmount,
            'outstanding_amount' => $outstandingAmount,
        ];
    }

    /**
     * Compute per-category breakdown (Budget vs Contract Base)
     * 
     * @param string $tenantId Tenant ID
     * @param Project $project Project model
     * @return array Categories array
     */
    private function computePerCategoryBreakdown(string $tenantId, Project $project): array
    {
        // Get distinct non-null cost categories from budget lines
        $categories = ProjectBudgetLine::where('tenant_id', $tenantId)
            ->where('project_id', $project->id)
            ->whereNotNull('cost_category')
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('cost_category')
            ->toArray();

        $result = [];

        foreach ($categories as $category) {
            // Budget total for this category
            $budgetTotal = (float) ProjectBudgetLine::where('tenant_id', $tenantId)
                ->where('project_id', $project->id)
                ->where('cost_category', $category)
                ->whereNull('deleted_at')
                ->sum('amount_budget') ?? 0.0;

            // Contract base total for this category
            // Sum of contract_lines.amount where:
            // - contract_line's budget_line_id references a budget line with this cost_category
            // - contract belongs to this project & tenant
            $contractBaseTotal = (float) ContractLine::where('contract_lines.tenant_id', $tenantId)
                ->where('contract_lines.project_id', $project->id)
                ->whereNull('contract_lines.deleted_at')
                ->join('project_budget_lines', function ($join) use ($tenantId, $category) {
                    $join->on('contract_lines.budget_line_id', '=', 'project_budget_lines.id')
                        ->where('project_budget_lines.tenant_id', $tenantId)
                        ->where('project_budget_lines.cost_category', $category)
                        ->whereNull('project_budget_lines.deleted_at');
                })
                ->join('contracts', function ($join) use ($tenantId) {
                    $join->on('contract_lines.contract_id', '=', 'contracts.id')
                        ->where('contracts.tenant_id', $tenantId)
                        ->whereNull('contracts.deleted_at');
                })
                ->sum('contract_lines.amount') ?? 0.0;

            $result[] = [
                'cost_category' => $category,
                'budget_total' => $budgetTotal,
                'contract_base_total' => $contractBaseTotal,
            ];
        }

        return $result;
    }
}
