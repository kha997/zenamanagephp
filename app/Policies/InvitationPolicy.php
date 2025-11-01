<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Invitation;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * InvitationPolicy
 * 
 * Authorization policy for Invitation model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class InvitationPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any invitations.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the invitation.
     */
    public function view(User $user, Invitation $invitation): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $invitation->tenant_id) {
            return false;
        }

        // Inviter can view
        if ($invitation->invited_by === $user->id) {
            return true;
        }

        // Invitee can view
        if ($invitation->email === $user->email) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create invitations.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can update the invitation.
     */
    public function update(User $user, Invitation $invitation): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $invitation->tenant_id) {
            return false;
        }

        // Inviter can update if not accepted
        if ($invitation->invited_by === $user->id && $invitation->status !== 'accepted') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the invitation.
     */
    public function delete(User $user, Invitation $invitation): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $invitation->tenant_id) {
            return false;
        }

        // Inviter can delete
        if ($invitation->invited_by === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the invitation.
     */
    public function restore(User $user, Invitation $invitation): bool
    {
        return $this->update($user, $invitation);
    }

    /**
     * Determine whether the user can permanently delete the invitation.
     */
    public function forceDelete(User $user, Invitation $invitation): bool
    {
        return $this->delete($user, $invitation);
    }

    /**
     * Determine whether the user can accept the invitation.
     */
    public function accept(User $user, Invitation $invitation): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $invitation->tenant_id) {
            return false;
        }

        // Invitee can accept
        if ($invitation->email === $user->email && $invitation->status === 'pending') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can decline the invitation.
     */
    public function decline(User $user, Invitation $invitation): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $invitation->tenant_id) {
            return false;
        }

        // Invitee can decline
        if ($invitation->email === $user->email && $invitation->status === 'pending') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can resend the invitation.
     */
    public function resend(User $user, Invitation $invitation): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $invitation->tenant_id) {
            return false;
        }

        // Inviter can resend if not accepted
        if ($invitation->invited_by === $user->id && $invitation->status !== 'accepted') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can cancel the invitation.
     */
    public function cancel(User $user, Invitation $invitation): bool
    {
        return $this->delete($user, $invitation);
    }
}