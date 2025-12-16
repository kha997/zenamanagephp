<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\ContractPayment;
use App\Models\Contract;

/**
 * ContractPaymentPolicy
 * 
 * Round 36: Contract Payment Schedule Backend
 * 
 * Authorization policy for ContractPayment model operations.
 * Ensures multi-tenant isolation and proper access control.
 * Permission checks (tenant.view_contracts, tenant.manage_contracts) are handled by middleware.
 * This policy reuses contract permissions as specified in Round 36 requirements.
 */
class ContractPaymentPolicy
{
    /**
     * Determine whether the user can view any contract payments.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the contract payment.
     */
    public function view(User $user, ContractPayment $payment): bool
    {
        // Multi-tenant isolation
        // Convert both to string for comparison (tenant_id can be Ulid object or string)
        return (string) $user->tenant_id === (string) $payment->tenant_id;
    }

    /**
     * Determine whether the user can create contract payments.
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
     * Determine whether the user can update the contract payment.
     */
    public function update(User $user, ContractPayment $payment): bool
    {
        // Multi-tenant isolation
        // Convert both to string for comparison (tenant_id can be Ulid object or string)
        return (string) $user->tenant_id === (string) $payment->tenant_id;
    }

    /**
     * Determine whether the user can delete the contract payment.
     */
    public function delete(User $user, ContractPayment $payment): bool
    {
        // Multi-tenant isolation
        // Convert both to string for comparison (tenant_id can be Ulid object or string)
        return (string) $user->tenant_id === (string) $payment->tenant_id;
    }
}
