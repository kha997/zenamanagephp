<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\ContractActualPayment;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ContractActualPaymentPolicy
 * 
 * Round 229: Cost Vertical Permissions
 * 
 * Authorization policy for ContractActualPayment model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class ContractActualPaymentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any payments.
     */
    public function viewAny(User $user): bool
    {
        // Must have cost view permission
        return $user->tenant_id !== null && $user->hasPermission('projects.cost.view');
    }

    /**
     * Determine whether the user can view the payment.
     */
    public function view(User $user, ContractActualPayment $payment): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $payment->tenant_id) {
            return false;
        }

        // Must have cost view permission
        return $user->hasPermission('projects.cost.view');
    }

    /**
     * Determine whether the user can create payments.
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
     * Determine whether the user can update the payment.
     */
    public function update(User $user, ContractActualPayment $payment): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $payment->tenant_id) {
            return false;
        }

        // Must have cost edit permission
        return $user->hasPermission('projects.cost.edit');
    }

    /**
     * Determine whether the user can delete the payment.
     */
    public function delete(User $user, ContractActualPayment $payment): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $payment->tenant_id) {
            return false;
        }

        // Must have cost edit permission
        return $user->hasPermission('projects.cost.edit');
    }

    /**
     * Determine whether the user can mark the payment as paid.
     * 
     * Round 230: Workflow/Approval for Payments
     */
    public function approve(User $user, ContractActualPayment $payment): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $payment->tenant_id) {
            return false;
        }

        // Must have cost approve permission
        return $user->hasPermission('projects.cost.approve');
    }
}
