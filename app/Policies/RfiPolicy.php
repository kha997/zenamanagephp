<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Rfi;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * RfiPolicy
 * 
 * Authorization policy for RFI (Request for Information) model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class RfiPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any RFIs.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the RFI.
     */
    public function view(User $user, Rfi $rfi): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $rfi->tenant_id) {
            return false;
        }

        // Creator can view
        if ($rfi->created_by === $user->id) {
            return true;
        }

        // Project members can view
        if ($rfi->project_id) {
            return $user->can('view', $rfi->project);
        }

        return false;
    }

    /**
     * Determine whether the user can create RFIs.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can update the RFI.
     */
    public function update(User $user, Rfi $rfi): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $rfi->tenant_id) {
            return false;
        }

        // Creator can update if not answered
        if ($rfi->created_by === $user->id && $rfi->status !== 'answered') {
            return true;
        }

        // Project managers can update
        if ($rfi->project_id) {
            return $user->can('update', $rfi->project);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the RFI.
     */
    public function delete(User $user, Rfi $rfi): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $rfi->tenant_id) {
            return false;
        }

        // Creator can delete if not answered
        if ($rfi->created_by === $user->id && $rfi->status !== 'answered') {
            return true;
        }

        // Project managers can delete
        if ($rfi->project_id) {
            return $user->can('delete', $rfi->project);
        }

        return false;
    }

    /**
     * Determine whether the user can restore the RFI.
     */
    public function restore(User $user, Rfi $rfi): bool
    {
        return $this->update($user, $rfi);
    }

    /**
     * Determine whether the user can permanently delete the RFI.
     */
    public function forceDelete(User $user, Rfi $rfi): bool
    {
        return $this->delete($user, $rfi);
    }

    /**
     * Determine whether the user can answer the RFI.
     */
    public function answer(User $user, Rfi $rfi): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $rfi->tenant_id) {
            return false;
        }

        // Project managers can answer
        if ($rfi->project_id) {
            return $user->can('update', $rfi->project);
        }

        return false;
    }

    /**
     * Determine whether the user can close the RFI.
     */
    public function close(User $user, Rfi $rfi): bool
    {
        return $this->answer($user, $rfi);
    }

    /**
     * Determine whether the user can reopen the RFI.
     */
    public function reopen(User $user, Rfi $rfi): bool
    {
        return $this->answer($user, $rfi);
    }
}