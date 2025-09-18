<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\SidebarConfig;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SidebarConfigPolicy
{
    use HandlesAuthorization;

    /**
     * Perform pre-authorization checks.
     */
    public function before(User $user, string $ability): ?bool
    {
        // Super Admin has full access to everything
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    /**
     * Determine whether the user can view any sidebar configs.
     */
    public function viewAny(User $user): bool
    {
        // Only Super Admin and Admin can view sidebar configs
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can view the sidebar config.
     */
    public function view(User $user, SidebarConfig $sidebarConfig): bool
    {
        // Only Super Admin and Admin can view sidebar configs
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can create sidebar configs.
     */
    public function create(User $user): bool
    {
        // Only Super Admin can create sidebar configs
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can update the sidebar config.
     */
    public function update(User $user, SidebarConfig $sidebarConfig): bool
    {
        // Only Super Admin can update sidebar configs
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can delete the sidebar config.
     */
    public function delete(User $user, SidebarConfig $sidebarConfig): bool
    {
        // Only Super Admin can delete sidebar configs
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can restore the sidebar config.
     */
    public function restore(User $user, SidebarConfig $sidebarConfig): bool
    {
        // Only Super Admin can restore sidebar configs
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can permanently delete the sidebar config.
     */
    public function forceDelete(User $user, SidebarConfig $sidebarConfig): bool
    {
        // Only Super Admin can force delete sidebar configs
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can manage sidebar configs.
     */
    public function manage(User $user): bool
    {
        // Only Super Admin can manage sidebar configs
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can clone sidebar configs.
     */
    public function clone(User $user): bool
    {
        // Only Super Admin can clone sidebar configs
        return $user->hasRole('super_admin');
    }

    /**
     * Determine whether the user can reset sidebar configs.
     */
    public function reset(User $user): bool
    {
        // Only Super Admin can reset sidebar configs
        return $user->hasRole('super_admin');
    }
}
