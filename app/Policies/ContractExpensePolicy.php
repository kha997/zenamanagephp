<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\ContractExpense;
use App\Models\Contract;

/**
 * ContractExpensePolicy
 * 
 * Round 44: Contract Expenses (Actual Costs) - Backend Only
 * 
 * Authorization policy for ContractExpense model operations.
 * Ensures multi-tenant isolation and proper access control.
 * Permission checks (tenant.view_contracts, tenant.manage_contracts) are handled by middleware.
 * This policy reuses contract permissions as specified in Round 44 requirements.
 */
class ContractExpensePolicy
{
    /**
     * Determine whether the user can view any contract expenses.
     * 
     * Note: There is no index route for expenses (they are nested under contracts).
     * This method returns false to be more restrictive, though it's not currently used.
     */
    public function viewAny(User $user): bool
    {
        // Expenses are always accessed via nested routes under contracts.
        // There is no standalone /expenses index endpoint.
        return false;
    }

    /**
     * Determine whether the user can view the contract expense.
     */
    public function view(User $user, ContractExpense $expense): bool
    {
        // Multi-tenant isolation
        // Convert both to string for comparison (tenant_id can be Ulid object or string)
        return (string) $user->tenant_id === (string) $expense->tenant_id;
    }

    /**
     * Determine whether the user can create contract expenses.
     * 
     * @param User $user
     * @param Contract|null $contract Optional contract context for nested routes
     */
    public function create(User $user, ?Contract $contract = null): bool
    {
        // User must belong to a tenant
        if ($user->tenant_id === null) {
            return false;
        }

        // If contract is provided, ensure it belongs to the same tenant
        if ($contract !== null) {
            return (string) $user->tenant_id === (string) $contract->tenant_id;
        }

        return true;
    }

    /**
     * Determine whether the user can update the contract expense.
     */
    public function update(User $user, ContractExpense $expense): bool
    {
        // Multi-tenant isolation
        // Convert both to string for comparison (tenant_id can be Ulid object or string)
        return (string) $user->tenant_id === (string) $expense->tenant_id;
    }

    /**
     * Determine whether the user can delete the contract expense.
     */
    public function delete(User $user, ContractExpense $expense): bool
    {
        // Multi-tenant isolation
        // Convert both to string for comparison (tenant_id can be Ulid object or string)
        return (string) $user->tenant_id === (string) $expense->tenant_id;
    }
}

