<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Contract;

/**
 * ContractPolicy
 * 
 * Round 33: MVP Contract Backend
 * 
 * Authorization policy for Contract model operations.
 * Ensures multi-tenant isolation and proper access control.
 * Permission checks (tenant.view_contracts, tenant.manage_contracts) are handled by middleware.
 */
class ContractPolicy
{
    /**
     * Determine whether the user can view any contracts.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the contract.
     */
    public function view(User $user, Contract $contract): bool
    {
        // Multi-tenant isolation
        // Convert both to string for comparison (tenant_id can be Ulid object or string)
        return (string) $user->tenant_id === (string) $contract->tenant_id;
    }

    /**
     * Determine whether the user can create contracts.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can update the contract.
     */
    public function update(User $user, Contract $contract): bool
    {
        // Multi-tenant isolation
        // Convert both to string for comparison (tenant_id can be Ulid object or string)
        return (string) $user->tenant_id === (string) $contract->tenant_id;
    }

    /**
     * Determine whether the user can delete the contract.
     */
    public function delete(User $user, Contract $contract): bool
    {
        // Multi-tenant isolation
        // Convert both to string for comparison (tenant_id can be Ulid object or string)
        return (string) $user->tenant_id === (string) $contract->tenant_id;
    }
}
