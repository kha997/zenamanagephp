<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\ContractBudgetLine;
use App\Models\Contract;

/**
 * ContractBudgetLinePolicy
 * 
 * Round 43: Cost Control / Budget vs Actual (Backend-only Foundation)
 * 
 * Authorization policy for ContractBudgetLine model operations.
 * Ensures multi-tenant isolation and proper access control.
 * Permission checks (tenant.view_contracts, tenant.manage_contracts) are handled by middleware.
 * This policy reuses contract permissions as specified in Round 43 requirements.
 */
class ContractBudgetLinePolicy
{
    /**
     * Determine whether the user can view any contract budget lines.
     * 
     * Note: There is no index route for budget lines (they are nested under contracts).
     * This method returns false to be more restrictive, though it's not currently used.
     */
    public function viewAny(User $user): bool
    {
        // Budget lines are always accessed via nested routes under contracts.
        // There is no standalone /budget-lines index endpoint.
        return false;
    }

    /**
     * Determine whether the user can view the contract budget line.
     */
    public function view(User $user, ContractBudgetLine $line): bool
    {
        // Multi-tenant isolation
        // Convert both to string for comparison (tenant_id can be Ulid object or string)
        return (string) $user->tenant_id === (string) $line->tenant_id;
    }

    /**
     * Determine whether the user can create contract budget lines.
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
     * Determine whether the user can update the contract budget line.
     */
    public function update(User $user, ContractBudgetLine $line): bool
    {
        // Multi-tenant isolation
        // Convert both to string for comparison (tenant_id can be Ulid object or string)
        return (string) $user->tenant_id === (string) $line->tenant_id;
    }

    /**
     * Determine whether the user can delete the contract budget line.
     */
    public function delete(User $user, ContractBudgetLine $line): bool
    {
        // Multi-tenant isolation
        // Convert both to string for comparison (tenant_id can be Ulid object or string)
        return (string) $user->tenant_id === (string) $line->tenant_id;
    }
}
