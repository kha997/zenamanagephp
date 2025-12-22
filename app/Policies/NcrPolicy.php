<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Ncr;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * NcrPolicy
 * 
 * Authorization policy for NCR (Non-Conformance Report) model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class NcrPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any NCRs.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the NCR.
     */
    public function view(User $user, Ncr $ncr): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $ncr->tenant_id) {
            return false;
        }

        // Creator can view
        if ($ncr->created_by === $user->id) {
            return true;
        }

        // Project members can view
        if ($ncr->project_id) {
            return $user->can('view', $ncr->project);
        }

        return false;
    }

    /**
     * Determine whether the user can create NCRs.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can update the NCR.
     */
    public function update(User $user, Ncr $ncr): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $ncr->tenant_id) {
            return false;
        }

        // Creator can update if not closed
        if ($ncr->created_by === $user->id && $ncr->status !== 'closed') {
            return true;
        }

        // Project managers can update
        if ($ncr->project_id) {
            return $user->can('update', $ncr->project);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the NCR.
     */
    public function delete(User $user, Ncr $ncr): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $ncr->tenant_id) {
            return false;
        }

        // Creator can delete if not closed
        if ($ncr->created_by === $user->id && $ncr->status !== 'closed') {
            return true;
        }

        // Project managers can delete
        if ($ncr->project_id) {
            return $user->can('delete', $ncr->project);
        }

        return false;
    }

    /**
     * Determine whether the user can restore the NCR.
     */
    public function restore(User $user, Ncr $ncr): bool
    {
        return $this->update($user, $ncr);
    }

    /**
     * Determine whether the user can permanently delete the NCR.
     */
    public function forceDelete(User $user, Ncr $ncr): bool
    {
        return $this->delete($user, $ncr);
    }

    /**
     * Determine whether the user can approve the NCR.
     */
    public function approve(User $user, Ncr $ncr): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $ncr->tenant_id) {
            return false;
        }

        // Project managers can approve
        if ($ncr->project_id) {
            return $user->can('update', $ncr->project);
        }

        return false;
    }

    /**
     * Determine whether the user can close the NCR.
     */
    public function close(User $user, Ncr $ncr): bool
    {
        return $this->approve($user, $ncr);
    }

    /**
     * Determine whether the user can reopen the NCR.
     */
    public function reopen(User $user, Ncr $ncr): bool
    {
        return $this->approve($user, $ncr);
    }

    /**
     * Determine whether the user can assign corrective actions.
     */
    public function assignCorrectiveAction(User $user, Ncr $ncr): bool
    {
        return $this->update($user, $ncr);
    }
}