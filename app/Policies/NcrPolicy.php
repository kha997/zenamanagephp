<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Ncr;
use Illuminate\Auth\Access\HandlesAuthorization;

class NcrPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function view(User $user, Ncr $ncr)
    {
        if ($user->tenant_id !== $ncr->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function update(User $user, Ncr $ncr)
    {
        if ($user->tenant_id !== $ncr->tenant_id) return false;
        return $user->id === $ncr->created_by || $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function delete(User $user, Ncr $ncr)
    {
        if ($user->tenant_id !== $ncr->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin']);
    }

    public function assign(User $user, Ncr $ncr)
    {
        if ($user->tenant_id !== $ncr->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function resolve(User $user, Ncr $ncr)
    {
        if ($user->tenant_id !== $ncr->tenant_id) return false;
        return $user->id === $ncr->assigned_to || $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function close(User $user, Ncr $ncr)
    {
        if ($user->tenant_id !== $ncr->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }
}
