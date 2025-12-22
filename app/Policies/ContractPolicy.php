<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Contract;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ContractPolicy
 * 
 * Round 229: Cost Vertical Permissions
 * 
 * Authorization policy for Contract model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class ContractPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any contracts.
     */
    public function viewAny(User $user): bool
    {
        // Must have cost view permission
        return $user->tenant_id !== null && $user->hasPermission('projects.cost.view');
    }

    /**
     * Determine whether the user can view the contract.
     */
    public function view(User $user, Contract $contract): bool
    {
        // Multi-tenant isolation
        if ((string) $user->tenant_id !== (string) $contract->tenant_id) {
            return false;
        }

        // Must have cost view permission
        return $user->hasPermission('projects.cost.view');
    }

    /**
     * Determine whether the user can create contracts.
     */
    public function create(User $user): bool
    {
        // Multi-tenant check
        if ($user->tenant_id === null) {
            return false;
        }

        // Must have cost edit permission
        return $user->hasPermission('projects.cost.edit');
    }

    /**
     * Determine whether the user can update the contract.
     */
    public function update(User $user, Contract $contract): bool
    {
        // Multi-tenant isolation
        if ((string) $user->tenant_id !== (string) $contract->tenant_id) {
            return false;
        }

        // Must have cost edit permission
        return $user->hasPermission('projects.cost.edit');
    }

    /**
     * Determine whether the user can delete the contract.
     */
    public function delete(User $user, Contract $contract): bool
    {
        // Multi-tenant isolation
        if ((string) $user->tenant_id !== (string) $contract->tenant_id) {
            return false;
        }

        // Must have cost edit permission
        return $user->hasPermission('projects.cost.edit');
    }
}
