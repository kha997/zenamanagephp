<?php declare(strict_types=1);

namespace App\Services\Reports;

use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\ContractBudgetLine;
use App\Models\ContractExpense;
use Carbon\Carbon;

/**
 * Service for computing Contracts and Payments KPIs for Reports
 * 
 * Round 38: Contracts & Payments KPIs Integration
 * 
 * Provides aggregated financial metrics for contracts and payment schedules
 * scoped by tenant_id for multi-tenant isolation.
 */
class ContractsReportsService
{
    /**
     * Get Contracts and Payments KPIs for a tenant
     * 
     * Round 45: Added budget and actual blocks
     * 
     * @param string $tenantId Tenant ID to scope queries
     * @return array KPI data structure:
     *   - total_count: Total number of contracts
     *   - active_count: Contracts with status 'active'
     *   - completed_count: Contracts with status 'completed'
     *   - cancelled_count: Contracts with status 'cancelled'
     *   - total_value: Sum of all contract total_value (null treated as 0)
     *   - payments: {
     *       - scheduled_total: Sum of all active payment amounts
     *       - paid_total: Sum of payments with status 'paid'
     *       - overdue_total: Sum of overdue payments (status != 'paid' && due_date < today)
     *       - overdue_count: Count of overdue payments
     *       - remaining_to_schedule: max(0, total_value - scheduled_total) or null
     *       - remaining_to_pay: max(0, scheduled_total - paid_total)
     *     }
     *   - budget: {
     *       - budget_total: Sum of all active budget lines (exclude cancelled + soft-deleted)
     *       - active_line_count: Count of active budget lines
     *       - over_budget_contracts_count: Count of contracts where budget_total > contract_value (only when contract_value != null)
     *     }
     *   - actual: {
     *       - actual_total: Sum of all active expenses (exclude cancelled + soft-deleted)
     *       - line_count: Count of active expenses
     *       - contract_vs_actual_diff_total: Sum of (contract_value - actual_total) for contracts with total_value != null
     *       - overrun_contracts_count: Count of contracts where actual_total > contract_value (only when total_value != null)
     *     }
     */
    public function getContractsKpisForTenant(string $tenantId): array
    {
        // Contract counts by status
        $totalCount = Contract::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->count();
        
        $activeCount = Contract::where('tenant_id', $tenantId)
            ->where('status', Contract::STATUS_ACTIVE)
            ->whereNull('deleted_at')
            ->count();
        
        $completedCount = Contract::where('tenant_id', $tenantId)
            ->where('status', Contract::STATUS_COMPLETED)
            ->whereNull('deleted_at')
            ->count();
        
        $cancelledCount = Contract::where('tenant_id', $tenantId)
            ->where('status', Contract::STATUS_CANCELLED)
            ->whereNull('deleted_at')
            ->count();
        
        // Total value: sum of all contract total_value (treat null as 0)
        $totalValue = (float) Contract::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->sum('total_value') ?? 0.0;
        
        // Payment aggregates (only active payments, not soft-deleted)
        $scheduledTotal = (float) ContractPayment::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->sum('amount') ?? 0.0;
        
        $paidTotal = (float) ContractPayment::where('tenant_id', $tenantId)
            ->where('status', 'paid')
            ->whereNull('deleted_at')
            ->sum('amount') ?? 0.0;
        
        // Overdue payments: use centralized scope
        $overduePayments = ContractPayment::where('tenant_id', $tenantId)
            ->overdue()
            ->get();
        
        $overdueTotal = (float) $overduePayments->sum('amount') ?? 0.0;
        $overdueCount = $overduePayments->count();
        
        // Remaining calculations
        // remaining_to_schedule: max(0, total_value - scheduled_total) if total_value is known
        $remainingToSchedule = null;
        if ($totalValue > 0) {
            $remainingToSchedule = max(0, $totalValue - $scheduledTotal);
        }
        
        // remaining_to_pay: max(0, scheduled_total - paid_total)
        $remainingToPay = max(0, $scheduledTotal - $paidTotal);
        
        // Round 45: Budget block
        // Calculate budget_total: sum of active budget lines (not cancelled, not soft-deleted)
        $budgetTotal = (float) ContractBudgetLine::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->sum('total_amount') ?? 0.0;
        
        $activeLineCount = ContractBudgetLine::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->count();
        
        // Calculate over_budget_contracts_count
        // Get all contracts with total_value != null, then check which ones have budget_total > contract_value
        $contractsWithValue = Contract::where('tenant_id', $tenantId)
            ->whereNotNull('total_value')
            ->whereNull('deleted_at')
            ->get();
        
        $overBudgetCount = 0;
        foreach ($contractsWithValue as $contract) {
            $contractBudgetTotal = (float) $contract->budgetLines()
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled')
                ->sum('total_amount') ?? 0.0;
            
            if ($contractBudgetTotal > (float) $contract->total_value) {
                $overBudgetCount++;
            }
        }
        
        // Round 45: Actual block
        // Calculate actual_total: sum of active expenses (not cancelled, not soft-deleted)
        $actualTotal = (float) ContractExpense::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->sum('amount') ?? 0.0;
        
        $actualLineCount = ContractExpense::where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled')
            ->count();
        
        // Calculate contract_vs_actual_diff_total and overrun_contracts_count
        $contractVsActualDiffTotal = 0.0;
        $overrunCount = 0;
        
        foreach ($contractsWithValue as $contract) {
            $contractActualTotal = (float) $contract->expenses()
                ->whereNull('deleted_at')
                ->where('status', '!=', 'cancelled')
                ->sum('amount') ?? 0.0;
            
            $contractValue = (float) $contract->total_value;
            $contractVsActualDiffTotal += ($contractValue - $contractActualTotal);
            
            if ($contractActualTotal > $contractValue) {
                $overrunCount++;
            }
        }
        
        return [
            'total_count' => $totalCount,
            'active_count' => $activeCount,
            'completed_count' => $completedCount,
            'cancelled_count' => $cancelledCount,
            'total_value' => $totalValue,
            'payments' => [
                'scheduled_total' => $scheduledTotal,
                'paid_total' => $paidTotal,
                'overdue_total' => $overdueTotal,
                'overdue_count' => $overdueCount,
                'remaining_to_schedule' => $remainingToSchedule,
                'remaining_to_pay' => $remainingToPay,
            ],
            'budget' => [
                'budget_total' => $budgetTotal,
                'active_line_count' => $activeLineCount,
                'over_budget_contracts_count' => $overBudgetCount,
            ],
            'actual' => [
                'actual_total' => $actualTotal,
                'line_count' => $actualLineCount,
                'contract_vs_actual_diff_total' => $contractVsActualDiffTotal,
                'overrun_contracts_count' => $overrunCount,
            ],
        ];
    }

    /**
     * Get cost summary for a single contract
     * 
     * Round 45: Contract Cost Control - Cost Summary API
     * 
     * @param string $tenantId Tenant ID (for validation)
     * @param Contract $contract The contract
     * @return array Cost summary data:
     *   - contract_value: contract.total_value (can be null)
     *   - budget_total: Sum of active budget lines
     *   - actual_total: Sum of active expenses
     *   - payments_scheduled_total: Sum of all active payment amounts
     *   - payments_paid_total: Sum of payments with status 'paid'
     *   - remaining_to_schedule: max(0, contract_value - scheduled_total) or null
     *   - remaining_to_pay: max(0, scheduled_total - paid_total)
     *   - budget_vs_contract_diff: budget_total - contract_value (can be null)
     *   - contract_vs_actual_diff: contract_value - actual_total (can be null)
     *   - overdue_payments_count: Count of overdue payments
     *   - overdue_payments_total: Sum of overdue payments
     * @throws \InvalidArgumentException If contract does not belong to tenant
     */
    public function getContractCostSummary(string $tenantId, Contract $contract): array
    {
        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            throw new \InvalidArgumentException('Contract does not belong to tenant');
        }

        // Get budget summary
        $budgetService = app(\App\Services\Contracts\ContractBudgetService::class);
        $budgetSummary = $budgetService->getBudgetSummaryForContract($tenantId, $contract);
        $budgetTotal = $budgetSummary['budget_total'];

        // Get actual summary
        $expenseService = app(\App\Services\Contracts\ContractExpenseService::class);
        $actualSummary = $expenseService->getActualCostSummaryForContract($tenantId, $contract);
        $actualTotal = $actualSummary['actual_total'];

        // Get payments summary
        $scheduledTotal = (float) $contract->payments()
            ->whereNull('deleted_at')
            ->sum('amount') ?? 0.0;

        $paidTotal = (float) $contract->payments()
            ->where('status', 'paid')
            ->whereNull('deleted_at')
            ->sum('amount') ?? 0.0;

        // Overdue payments: use centralized scope
        $overduePayments = $contract->payments()
            ->overdue()
            ->get();

        $overdueCount = $overduePayments->count();
        $overdueTotal = (float) $overduePayments->sum('amount') ?? 0.0;

        // Calculate remaining amounts
        $contractValue = $contract->total_value;
        $remainingToSchedule = null;
        if ($contractValue !== null) {
            $remainingToSchedule = max(0, (float) $contractValue - $scheduledTotal);
        }

        $remainingToPay = max(0, $scheduledTotal - $paidTotal);

        // Calculate differences
        $budgetVsContractDiff = null;
        if ($contractValue !== null) {
            $budgetVsContractDiff = $budgetTotal - (float) $contractValue;
        }

        $contractVsActualDiff = null;
        if ($contractValue !== null) {
            $contractVsActualDiff = (float) $contractValue - $actualTotal;
        }

        return [
            'contract_value' => $contractValue !== null ? (float) $contractValue : null,
            'budget_total' => $budgetTotal,
            'actual_total' => $actualTotal,
            'payments_scheduled_total' => $scheduledTotal,
            'payments_paid_total' => $paidTotal,
            'remaining_to_schedule' => $remainingToSchedule,
            'remaining_to_pay' => $remainingToPay,
            'budget_vs_contract_diff' => $budgetVsContractDiff,
            'contract_vs_actual_diff' => $contractVsActualDiff,
            'overdue_payments_count' => $overdueCount,
            'overdue_payments_total' => $overdueTotal,
        ];
    }
}

