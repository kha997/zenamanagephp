<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Invitation;
use Illuminate\Auth\Access\HandlesAuthorization;

class InvitationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function view(User $user, Invitation $invitation)
    {
        if ($user->tenant_id !== $invitation->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm']) || $user->id === $invitation->invited_by;
    }

    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function update(User $user, Invitation $invitation)
    {
        if ($user->tenant_id !== $invitation->tenant_id) return false;
        return $user->id === $invitation->invited_by || $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function delete(User $user, Invitation $invitation)
    {
        if ($user->tenant_id !== $invitation->tenant_id) return false;
        return $user->id === $invitation->invited_by || $user->hasRole(['super_admin', 'admin']);
    }

    public function accept(User $user, Invitation $invitation)
    {
        if ($user->tenant_id !== $invitation->tenant_id) return false;
        return $user->email === $invitation->email;
    }

    public function resend(User $user, Invitation $invitation)
    {
        if ($user->tenant_id !== $invitation->tenant_id) return false;
        return $user->id === $invitation->invited_by || $user->hasRole(['super_admin', 'admin', 'pm']);
    }
}
