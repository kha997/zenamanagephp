<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any roles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('project_manager');
    }

    /**
     * Determine whether the user can view the role.
     */
    public function view(User $user, Role $role): bool
    {
        // Admin can view any role
        if ($user->hasRole('admin')) {
            return true;
        }

        // Project managers can view tenant-specific roles
        if ($user->hasRole('project_manager')) {
            return $role->tenant_id === $user->tenant_id;
        }

        return false;
    }

    /**
     * Determine whether the user can create roles.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the role.
     */
    public function update(User $user, Role $role): bool
    {
        // Admin can update any role
        if ($user->hasRole('admin')) {
            return true;
        }

        // Project managers can update tenant-specific roles
        if ($user->hasRole('project_manager')) {
            return $role->tenant_id === $user->tenant_id;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the role.
     */
    public function delete(User $user, Role $role): bool
    {
        // Only admin can delete roles
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the role.
     */
    public function restore(User $user, Role $role): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the role.
     */
    public function forceDelete(User $user, Role $role): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can assign roles to users.
     */
    public function assign(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('project_manager');
    }
}
