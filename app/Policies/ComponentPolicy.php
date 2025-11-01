<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Component;
use Illuminate\Auth\Access\HandlesAuthorization;

/**
 * ComponentPolicy
 * 
 * Authorization policy for Component model operations.
 * Ensures multi-tenant isolation and proper access control.
 */
class ComponentPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any components.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the component.
     */
    public function view(User $user, Component $component): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $component->tenant_id) {
            return false;
        }

        // Users with proper roles can view
        return $user->hasAnyRole(['super_admin', 'admin', 'project_manager', 'designer', 'site_engineer', 'qc_engineer', 'procurement']);
    }

    /**
     * Determine whether the user can create components.
     */
    public function create(User $user): bool
    {
        // Multi-tenant check
        if ($user->tenant_id === null) {
            return false;
        }

        // Users with proper roles can create
        return $user->hasAnyRole(['super_admin', 'admin', 'project_manager', 'designer', 'site_engineer', 'qc_engineer', 'procurement']);
    }

    /**
     * Determine whether the user can update the component.
     */
    public function update(User $user, Component $component): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $component->tenant_id) {
            return false;
        }

        // Owner can always update
        if ($component->created_by === $user->id) {
            return true;
        }

        // Users with proper roles can update
        return $user->hasAnyRole(['super_admin', 'admin', 'project_manager', 'designer', 'site_engineer', 'qc_engineer', 'procurement']);
    }

    /**
     * Determine whether the user can delete the component.
     */
    public function delete(User $user, Component $component): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $component->tenant_id) {
            return false;
        }

        // Owner can always delete
        if ($component->created_by === $user->id) {
            return true;
        }

        // Only admins can delete
        return $user->hasAnyRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can restore the component.
     */
    public function restore(User $user, Component $component): bool
    {
        return $this->update($user, $component);
    }

    /**
     * Determine whether the user can permanently delete the component.
     */
    public function forceDelete(User $user, Component $component): bool
    {
        return $this->delete($user, $component);
    }

    /**
     * Determine whether the user can move the component.
     */
    public function move(User $user, Component $component): bool
    {
        return $this->update($user, $component);
    }

    /**
     * Determine whether the user can duplicate the component.
     */
    public function duplicate(User $user, Component $component): bool
    {
        return $this->view($user, $component);
    }
}