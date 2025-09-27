<?php

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TenantPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any tenants.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the tenant.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        // Admin can view any tenant
        if ($user->hasRole('admin')) {
            return true;
        }

        // Users can view their own tenant
        return $user->tenant_id === $tenant->id;
    }

    /**
     * Determine whether the user can create tenants.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the tenant.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        // Admin can update any tenant
        if ($user->hasRole('admin')) {
            return true;
        }

        // Project managers can update their tenant settings
        if ($user->hasRole('project_manager') && $user->tenant_id === $tenant->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the tenant.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can restore the tenant.
     */
    public function restore(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the tenant.
     */
    public function forceDelete(User $user, Tenant $tenant): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can manage tenant settings.
     */
    public function manageSettings(User $user, Tenant $tenant): bool
    {
        return $this->update($user, $tenant);
    }
}
