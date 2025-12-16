<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\ChangeOrder;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ChangeOrderPolicy
 * 
 * Round 229: Cost Vertical Permissions
 * 
 * Authorization policy for ChangeOrder model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class ChangeOrderPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any change orders.
     */
    public function viewAny(User $user): bool
    {
        // Must have cost view permission
        return $user->tenant_id !== null && $user->hasPermission('projects.cost.view');
    }

    /**
     * Determine whether the user can view the change order.
     */
    public function view(User $user, ChangeOrder $changeOrder): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $changeOrder->tenant_id) {
            return false;
        }

        // Must have cost view permission
        return $user->hasPermission('projects.cost.view');
    }

    /**
     * Determine whether the user can create change orders.
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
     * Determine whether the user can update the change order.
     */
    public function update(User $user, ChangeOrder $changeOrder): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $changeOrder->tenant_id) {
            return false;
        }

        // Must have cost edit permission
        return $user->hasPermission('projects.cost.edit');
    }

    /**
     * Determine whether the user can delete the change order.
     */
    public function delete(User $user, ChangeOrder $changeOrder): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $changeOrder->tenant_id) {
            return false;
        }

        // Must have cost edit permission
        return $user->hasPermission('projects.cost.edit');
    }

    /**
     * Determine whether the user can approve/reject the change order.
     * 
     * Round 230: Workflow/Approval for Change Orders
     */
    public function approve(User $user, ChangeOrder $changeOrder): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $changeOrder->tenant_id) {
            return false;
        }

        // Must have cost approve permission
        return $user->hasPermission('projects.cost.approve');
    }
}
