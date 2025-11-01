<?php

namespace App\Policies;

use App\Models\User;
use App\Models\EmailTracking;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmailTrackingPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any email tracking records.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can view the email tracking record.
     */
    public function view(User $user, EmailTracking $emailTracking)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $emailTracking->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can create email tracking records.
     */
    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can update the email tracking record.
     */
    public function update(User $user, EmailTracking $emailTracking)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $emailTracking->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can delete the email tracking record.
     */
    public function delete(User $user, EmailTracking $emailTracking)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $emailTracking->tenant_id) {
            return false;
        }

        // Only super_admin and admin can delete
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can view email tracking analytics.
     */
    public function viewAnalytics(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can export email tracking data.
     */
    public function export(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can manage email tracking settings.
     */
    public function manageSettings(User $user)
    {
        return $user->hasRole(['super_admin', 'admin']);
    }
}
