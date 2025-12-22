<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\ChangeRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ChangeRequestPolicy
 * 
 * Authorization policy for ChangeRequest model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class ChangeRequestPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any change requests.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the change request.
     */
    public function view(User $user, ChangeRequest $changeRequest): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $changeRequest->tenant_id) {
            return false;
        }

        // Creator can view
        if ($changeRequest->created_by === $user->id) {
            return true;
        }

        // Project members can view
        if ($changeRequest->project_id) {
            return $user->can('view', $changeRequest->project);
        }

        return false;
    }

    /**
     * Determine whether the user can create change requests.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can update the change request.
     */
    public function update(User $user, ChangeRequest $changeRequest): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $changeRequest->tenant_id) {
            return false;
        }

        // Creator can update if not approved
        if ($changeRequest->created_by === $user->id && $changeRequest->status !== 'approved') {
            return true;
        }

        // Project managers can update
        if ($changeRequest->project_id) {
            return $user->can('update', $changeRequest->project);
        }

        return false;
    }

    /**
     * Determine whether the user can delete the change request.
     */
    public function delete(User $user, ChangeRequest $changeRequest): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $changeRequest->tenant_id) {
            return false;
        }

        // Creator can delete if not approved
        if ($changeRequest->created_by === $user->id && $changeRequest->status !== 'approved') {
            return true;
        }

        // Project managers can delete
        if ($changeRequest->project_id) {
            return $user->can('delete', $changeRequest->project);
        }

        return false;
    }

    /**
     * Determine whether the user can restore the change request.
     */
    public function restore(User $user, ChangeRequest $changeRequest): bool
    {
        return $this->update($user, $changeRequest);
    }

    /**
     * Determine whether the user can permanently delete the change request.
     */
    public function forceDelete(User $user, ChangeRequest $changeRequest): bool
    {
        return $this->delete($user, $changeRequest);
    }

    /**
     * Determine whether the user can approve the change request.
     */
    public function approve(User $user, ChangeRequest $changeRequest): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $changeRequest->tenant_id) {
            return false;
        }

        // Project managers and admins can approve
        return $user->hasAnyRole(['super_admin', 'admin', 'project_manager']);
    }

    /**
     * Determine whether the user can reject the change request.
     */
    public function reject(User $user, ChangeRequest $changeRequest): bool
    {
        return $this->approve($user, $changeRequest);
    }

    /**
     * Determine whether the user can comment on the change request.
     */
    public function comment(User $user, ChangeRequest $changeRequest): bool
    {
        return $this->view($user, $changeRequest);
    }
}