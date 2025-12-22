<?php declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Contract;
use App\Models\ContractBudgetLine;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing contract budget lines
 * 
 * Round 43: Cost Control / Budget vs Actual (Backend-only Foundation)
 * 
 * Handles creation, update, and summary calculation for contract budget lines.
 * Budget lines represent planned costs for contracts, organized by category/cost type.
 */
class ContractBudgetService
{
    /**
     * Create a new budget line for a contract
     * 
     * @param Contract $contract The contract to create budget line for
     * @param array $data Budget line data (name, quantity, unit_price, etc.)
     * @param User $user User creating the budget line
     * @return ContractBudgetLine Created budget line
     */
    public function createBudgetLineForContract(Contract $contract, array $data, User $user): ContractBudgetLine
    {
        // Set tenant_id from contract
        $data['tenant_id'] = $contract->tenant_id;
        $data['contract_id'] = $contract->id;
        
        // Set currency from contract if not provided
        if (!isset($data['currency']) || empty($data['currency'])) {
            $data['currency'] = $contract->currency ?? 'USD';
        }
        
        // Calculate total_amount if quantity and unit_price are provided but total_amount is null
        if (!isset($data['total_amount']) && isset($data['quantity']) && isset($data['unit_price'])) {
            $data['total_amount'] = (float) $data['quantity'] * (float) $data['unit_price'];
        }
        
        // Set created_by_id and updated_by_id
        $data['created_by_id'] = $user->id;
        $data['updated_by_id'] = $user->id;
        
        // Create budget line
        $line = ContractBudgetLine::create($data);
        
        Log::info('Contract budget line created via service', [
            'budget_line_id' => $line->id,
            'contract_id' => $contract->id,
            'tenant_id' => $line->tenant_id,
            'total_amount' => $line->total_amount,
            'created_by' => $user->id,
        ]);
        
        return $line;
    }
    
    /**
     * Update an existing budget line for a contract
     * 
     * @param Contract $contract The contract the budget line belongs to
     * @param ContractBudgetLine $line The budget line to update
     * @param array $data Updated budget line data
     * @param User $user User updating the budget line
     * @return ContractBudgetLine Updated budget line
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException If line doesn't belong to contract
     */
    public function updateBudgetLineForContract(Contract $contract, ContractBudgetLine $line, array $data, User $user): ContractBudgetLine
    {
        // Check line belongs to contract
        if ((string) $line->contract_id !== (string) $contract->id) {
            throw new \Illuminate\Database\Eloquent\ModelNotFoundException(
                'Budget line does not belong to this contract'
            );
        }
        
        // Recalculate total_amount if quantity or unit_price changed
        if (isset($data['quantity']) || isset($data['unit_price'])) {
            $quantity = $data['quantity'] ?? $line->quantity;
            $unitPrice = $data['unit_price'] ?? $line->unit_price;
            
            if ($quantity !== null && $unitPrice !== null) {
                $data['total_amount'] = (float) $quantity * (float) $unitPrice;
            }
        }
        
        // Set updated_by_id
        $data['updated_by_id'] = $user->id;
        
        // Update budget line
        $line->update($data);
        $line->refresh();
        
        Log::info('Contract budget line updated via service', [
            'budget_line_id' => $line->id,
            'contract_id' => $contract->id,
            'tenant_id' => $line->tenant_id,
            'total_amount' => $line->total_amount,
            'updated_by' => $user->id,
        ]);
        
        return $line;
    }
    
    /**
     * Get budget summary for a contract
     * 
     * Calculates:
     * - budget_total: Sum of total_amount of active lines (status != 'cancelled' && deleted_at null)
     * - contract_value: contract.total_value
     * - budget_vs_contract_diff: budget_total - contract_value (can be null if contract_value is null)
     * 
     * @param string $tenantId Tenant ID (for validation)
     * @param Contract $contract The contract
     * @return array Summary data
     */
    public function getBudgetSummaryForContract(string $tenantId, Contract $contract): array
    {
        // Ensure contract belongs to tenant
        if ((string) $contract->tenant_id !== (string) $tenantId) {
            throw new \InvalidArgumentException('Contract does not belong to tenant');
        }
        
        // Calculate budget_total: sum of active lines (not cancelled, not soft-deleted)
        $budgetTotal = $contract->budgetLines()
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->sum('total_amount');
        
        $contractValue = $contract->total_value;
        
        // Calculate difference (can be null if contract_value is null)
        $budgetVsContractDiff = null;
        if ($contractValue !== null) {
            $budgetVsContractDiff = (float) $budgetTotal - (float) $contractValue;
        }
        
        // Count active lines
        $activeLineCount = $contract->budgetLines()
            ->where('status', '!=', 'cancelled')
            ->whereNull('deleted_at')
            ->count();
        
        return [
            'budget_total' => (float) $budgetTotal,
            'contract_value' => $contractValue !== null ? (float) $contractValue : null,
            'budget_vs_contract_diff' => $budgetVsContractDiff,
            'active_line_count' => $activeLineCount,
        ];
    }
}

