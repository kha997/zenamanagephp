<?php declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractExpense;
use App\Models\ContractBudgetLine;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing contract expenses (actual costs)
 * 
 * Round 44: Contract Expenses (Actual Costs) - Backend Only
 * 
 * Handles creation, update, and summary calculation for contract expenses.
 * Expenses represent actual costs incurred for contracts, tracking against budget lines.
 */
class ContractExpenseService
{
    /**
     * Create a new expense for a contract
     * 
     * @param string $tenantId Tenant ID (for validation)
     * @param Contract $contract The contract to create expense for
     * @param array $data Expense data (name, amount, incurred_at, etc.)
     * @param User $user User creating the expense
     * @return ContractExpense Created expense
     * @throws \InvalidArgumentException If tenant mismatch
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If budget line not found or mismatch
     */
    public function createExpenseForContract(string $tenantId, Contract $contract, array $data, User $user): ContractExpense
    {
        // Assert tenant match
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            throw new \InvalidArgumentException('Contract tenant mismatch');
        }

        // Force scope: tenant_id and contract_id from contract
        $data['tenant_id'] = $contract->tenant_id;
        $data['contract_id'] = $contract->id;
        
        // Ignore any tenant_id/contract_id from $data if present
        unset($data['tenant_id'], $data['contract_id']);
        $data['tenant_id'] = $contract->tenant_id;
        $data['contract_id'] = $contract->id;

        // If budget_line_id is provided, validate it
        if (isset($data['budget_line_id']) && $data['budget_line_id'] !== null) {
            $budgetLine = ContractBudgetLine::find($data['budget_line_id']);
            
            if (!$budgetLine) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Budget line not found');
            }

            // Check tenant and contract match
            if ((string) $budgetLine->tenant_id !== (string) $tenantId) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Budget line tenant mismatch');
            }

            if ((string) $budgetLine->contract_id !== (string) $contract->id) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Budget line contract mismatch');
            }
        }
        
        // Set currency from contract if not provided
        if (!isset($data['currency']) || empty($data['currency'])) {
            $data['currency'] = $contract->currency ?: 'VND';
        }
        
        // Calculate amount if quantity and unit_cost are provided but amount is null
        if (!isset($data['amount']) && isset($data['quantity']) && isset($data['unit_cost'])) {
            $data['amount'] = (float) $data['quantity'] * (float) $data['unit_cost'];
        }
        
        // Set created_by_id and updated_by_id
        $data['created_by_id'] = $user->id;
        $data['updated_by_id'] = $user->id;
        
        // Create expense
        $expense = ContractExpense::create($data);
        $expense->refresh();
        
        Log::info('Contract expense created via service', [
            'expense_id' => $expense->id,
            'contract_id' => $contract->id,
            'tenant_id' => $expense->tenant_id,
            'amount' => $expense->amount,
            'created_by' => $user->id,
        ]);
        
        return $expense;
    }
    
    /**
     * Update an existing expense for a contract
     * 
     * @param string $tenantId Tenant ID (for validation)
     * @param Contract $contract The contract the expense belongs to
     * @param ContractExpense $expense The expense to update
     * @param array $data Updated expense data
     * @param User $user User updating the expense
     * @return ContractExpense Updated expense
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If expense doesn't belong to contract/tenant
     */
    public function updateExpenseForContract(string $tenantId, Contract $contract, ContractExpense $expense, array $data, User $user): ContractExpense
    {
        // Check tenant + contract match
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Contract tenant mismatch');
        }

        if ((string) $expense->contract_id !== (string) $contract->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Expense does not belong to this contract');
        }

        if ((string) $expense->tenant_id !== (string) $tenantId) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Expense tenant mismatch');
        }

        // Don't allow updating tenant_id or contract_id
        unset($data['tenant_id'], $data['contract_id']);

        // If budget_line_id is provided, validate it
        if (isset($data['budget_line_id'])) {
            if ($data['budget_line_id'] === null) {
                // Allow setting to null
                $data['budget_line_id'] = null;
            } else {
                $budgetLine = ContractBudgetLine::find($data['budget_line_id']);
                
                if (!$budgetLine) {
                    throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Budget line not found');
                }

                // Check tenant and contract match
                if ((string) $budgetLine->tenant_id !== (string) $tenantId) {
                    throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Budget line tenant mismatch');
                }

                if ((string) $budgetLine->contract_id !== (string) $contract->id) {
                    throw new \Illuminate\Database\Eloquent\ModelNotFoundException('Budget line contract mismatch');
                }
            }
        }
        
        // Recompute amount if quantity or unit_cost changed
        if (isset($data['quantity']) || isset($data['unit_cost'])) {
            $quantity = $data['quantity'] ?? $expense->quantity;
            $unitCost = $data['unit_cost'] ?? $expense->unit_cost;
            
            // Only auto-calculate if amount is not explicitly provided
            if (!isset($data['amount']) && $quantity !== null && $unitCost !== null) {
                $data['amount'] = (float) $quantity * (float) $unitCost;
            }
        }
        
        // Set updated_by_id
        $data['updated_by_id'] = $user->id;
        
        // Update expense
        $expense->update($data);
        $expense->refresh();
        
        Log::info('Contract expense updated via service', [
            'expense_id' => $expense->id,
            'contract_id' => $contract->id,
            'tenant_id' => $expense->tenant_id,
            'amount' => $expense->amount,
            'updated_by' => $user->id,
        ]);
        
        return $expense;
    }
    
    /**
     * Get actual cost summary for a contract
     * 
     * Calculates:
     * - actual_total: Sum of amount of active expenses (status != 'cancelled' && deleted_at null)
     * - contract_value: contract.total_value
     * - contract_vs_actual_diff: contract_value - actual_total (can be null if contract_value is null)
     * - line_count: Count of active expenses
     * 
     * @param string $tenantId Tenant ID (for validation)
     * @param Contract $contract The contract
     * @return array Summary data
     */
    public function getActualCostSummaryForContract(string $tenantId, Contract $contract): array
    {
        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            throw new \InvalidArgumentException('Contract does not belong to tenant');
        }
        
        // Calculate actual_total: sum of active expenses (not cancelled, not soft-deleted)
        $query = $contract->expenses()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->where('status', '!=', 'cancelled');
        
        $actualTotal = (float) $query->sum('amount');
        
        $contractValue = $contract->total_value;
        
        // Calculate difference (can be null if contract_value is null)
        $contractVsActualDiff = null;
        if ($contractValue !== null) {
            $contractVsActualDiff = (float) $contractValue - $actualTotal;
        }
        
        // Count active expenses
        $lineCount = $query->count();
        
        return [
            'actual_total' => $actualTotal,
            'contract_value' => $contractValue !== null ? (float) $contractValue : null,
            'contract_vs_actual_diff' => $contractVsActualDiff,
            'line_count' => $lineCount,
        ];
    }
}

