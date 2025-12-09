<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\ContractPaymentCertificate;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ContractPaymentCertificatePolicy
 * 
 * Round 229: Cost Vertical Permissions
 * 
 * Authorization policy for ContractPaymentCertificate model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class ContractPaymentCertificatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any payment certificates.
     */
    public function viewAny(User $user): bool
    {
        // Must have cost view permission
        return $user->tenant_id !== null && $user->hasPermission('projects.cost.view');
    }

    /**
     * Determine whether the user can view the payment certificate.
     */
    public function view(User $user, ContractPaymentCertificate $certificate): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $certificate->tenant_id) {
            return false;
        }

        // Must have cost view permission
        return $user->hasPermission('projects.cost.view');
    }

    /**
     * Determine whether the user can create payment certificates.
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
     * Determine whether the user can update the payment certificate.
     */
    public function update(User $user, ContractPaymentCertificate $certificate): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $certificate->tenant_id) {
            return false;
        }

        // Must have cost edit permission
        return $user->hasPermission('projects.cost.edit');
    }

    /**
     * Determine whether the user can delete the payment certificate.
     */
    public function delete(User $user, ContractPaymentCertificate $certificate): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $certificate->tenant_id) {
            return false;
        }

        // Must have cost edit permission
        return $user->hasPermission('projects.cost.edit');
    }

    /**
     * Determine whether the user can approve/submit the payment certificate.
     * 
     * Round 230: Workflow/Approval for Payment Certificates
     */
    public function approve(User $user, ContractPaymentCertificate $certificate): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $certificate->tenant_id) {
            return false;
        }

        // Must have cost approve permission
        return $user->hasPermission('projects.cost.approve');
    }
}
