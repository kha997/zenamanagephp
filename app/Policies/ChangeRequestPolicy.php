<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ChangeRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class ChangeRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any change requests.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can view the change request.
     */
    public function view(User $user, ChangeRequest $changeRequest)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $changeRequest->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can create change requests.
     */
    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can update the change request.
     */
    public function update(User $user, ChangeRequest $changeRequest)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $changeRequest->tenant_id) {
            return false;
        }

        // Only creator or management can update
        return $user->id === $changeRequest->created_by || 
               $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can delete the change request.
     */
    public function delete(User $user, ChangeRequest $changeRequest)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $changeRequest->tenant_id) {
            return false;
        }

        // Only creator or super_admin can delete
        return $user->id === $changeRequest->created_by || 
               $user->hasRole(['super_admin']);
    }

    /**
     * Determine whether the user can approve the change request.
     */
    public function approve(User $user, ChangeRequest $changeRequest)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $changeRequest->tenant_id) {
            return false;
        }

        // Only management can approve
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can reject the change request.
     */
    public function reject(User $user, ChangeRequest $changeRequest)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $changeRequest->tenant_id) {
            return false;
        }

        // Only management can reject
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can add comments to the change request.
     */
    public function comment(User $user, ChangeRequest $changeRequest)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $changeRequest->tenant_id) {
            return false;
        }

        // All team members can comment
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can assign the change request.
     */
    public function assign(User $user, ChangeRequest $changeRequest)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $changeRequest->tenant_id) {
            return false;
        }

        // Only management can assign
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can close the change request.
     */
    public function close(User $user, ChangeRequest $changeRequest)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $changeRequest->tenant_id) {
            return false;
        }

        // Only management can close
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }
}
