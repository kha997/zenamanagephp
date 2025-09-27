<?php

namespace App\Policies;

use App\Models\DashboardWidget;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class DashboardWidgetPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any dashboard widgets.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can view the dashboard widget.
     */
    public function view(User $user, DashboardWidget $dashboardWidget): bool
    {
        // Admin can view any widget
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($dashboardWidget->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Owner can view their widgets
        if ($dashboardWidget->user_id === $user->id) {
            return true;
        }

        // Public widgets can be viewed by anyone in the tenant
        if ($dashboardWidget->is_public) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create dashboard widgets.
     */
    public function create(User $user): bool
    {
        return $user->is_active && (
            $user->hasRole('admin') ||
            $user->hasRole('project_manager') ||
            $user->hasRole('member')
        );
    }

    /**
     * Determine whether the user can update the dashboard widget.
     */
    public function update(User $user, DashboardWidget $dashboardWidget): bool
    {
        // Admin can update any widget
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($dashboardWidget->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Owner can update their widgets
        if ($dashboardWidget->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the dashboard widget.
     */
    public function delete(User $user, DashboardWidget $dashboardWidget): bool
    {
        // Admin can delete any widget
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($dashboardWidget->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Owner can delete their widgets
        if ($dashboardWidget->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the dashboard widget.
     */
    public function restore(User $user, DashboardWidget $dashboardWidget): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the dashboard widget.
     */
    public function forceDelete(User $user, DashboardWidget $dashboardWidget): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can configure dashboard widgets.
     */
    public function configure(User $user, DashboardWidget $dashboardWidget): bool
    {
        return $this->update($user, $dashboardWidget);
    }
}
