<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Invitation;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvitationPolicy
{
    use HandlesAuthorization;

    private function isInvitationOwner(User $user, Invitation $invitation): bool
    {
        if ($invitation->invited_by_user_id !== null && $invitation->invited_by_user_id !== '') {
            return $user->id === $invitation->invited_by_user_id;
        }

        return (string) $invitation->invited_by === (string) $user->id;
    }

    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function view(User $user, Invitation $invitation)
    {
        if ($user->tenant_id !== $invitation->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm']) || $this->isInvitationOwner($user, $invitation);
    }

    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function update(User $user, Invitation $invitation)
    {
        if ($user->tenant_id !== $invitation->tenant_id) return false;
        return $this->isInvitationOwner($user, $invitation) || $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function delete(User $user, Invitation $invitation)
    {
        if ($user->tenant_id !== $invitation->tenant_id) return false;
        return $this->isInvitationOwner($user, $invitation) || $user->hasRole(['super_admin', 'admin']);
    }

    public function accept(User $user, Invitation $invitation)
    {
        if ($user->tenant_id !== $invitation->tenant_id) return false;
        return $user->email === $invitation->email;
    }

    public function resend(User $user, Invitation $invitation)
    {
        if ($user->tenant_id !== $invitation->tenant_id) return false;
        return $this->isInvitationOwner($user, $invitation) || $user->hasRole(['super_admin', 'admin', 'pm']);
    }
}
