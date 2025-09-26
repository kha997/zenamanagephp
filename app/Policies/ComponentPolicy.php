<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Component;
use Illuminate\Auth\Access\HandlesAuthorization;

class ComponentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any components.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can view the component.
     */
    public function view(User $user, Component $component)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $component->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can create components.
     */
    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer']);
    }

    /**
     * Determine whether the user can update the component.
     */
    public function update(User $user, Component $component)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $component->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer']);
    }

    /**
     * Determine whether the user can delete the component.
     */
    public function delete(User $user, Component $component)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $component->tenant_id) {
            return false;
        }

        // Only super_admin and admin can delete
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can restore the component.
     */
    public function restore(User $user, Component $component)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $component->tenant_id) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can permanently delete the component.
     */
    public function forceDelete(User $user, Component $component)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $component->tenant_id) {
            return false;
        }

        return $user->hasRole(['super_admin']);
    }

    /**
     * Determine whether the user can approve the component.
     */
    public function approve(User $user, Component $component)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $component->tenant_id) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can manage component hierarchy.
     */
    public function manageHierarchy(User $user, Component $component)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $component->tenant_id) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can assign components to projects.
     */
    public function assignToProject(User $user, Component $component)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $component->tenant_id) {
            return false;
        }

        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }
}