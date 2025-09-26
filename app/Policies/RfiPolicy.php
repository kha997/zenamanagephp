<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Rfi;
use Illuminate\Auth\Access\HandlesAuthorization;

class RfiPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function view(User $user, Rfi $rfi)
    {
        if ($user->tenant_id !== $rfi->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function update(User $user, Rfi $rfi)
    {
        if ($user->tenant_id !== $rfi->tenant_id) return false;
        return $user->id === $rfi->created_by || $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    public function delete(User $user, Rfi $rfi)
    {
        if ($user->tenant_id !== $rfi->tenant_id) return false;
        return $user->id === $rfi->created_by || $user->hasRole(['super_admin']);
    }

    public function respond(User $user, Rfi $rfi)
    {
        if ($user->tenant_id !== $rfi->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    public function approve(User $user, Rfi $rfi)
    {
        if ($user->tenant_id !== $rfi->tenant_id) return false;
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }
}
