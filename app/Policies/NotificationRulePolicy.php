<?php

namespace App\Policies;

use App\Models\User;
use App\Models\NotificationRule;
use Illuminate\Auth\Access\HandlesAuthorization;

class NotificationRulePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any notification rules.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can view the notification rule.
     */
    public function view(User $user, NotificationRule $notificationRule)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $notificationRule->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can create notification rules.
     */
    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can update the notification rule.
     */
    public function update(User $user, NotificationRule $notificationRule)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $notificationRule->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can delete the notification rule.
     */
    public function delete(User $user, NotificationRule $notificationRule)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $notificationRule->tenant_id) {
            return false;
        }

        // Only super_admin and admin can delete
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can activate the notification rule.
     */
    public function activate(User $user, NotificationRule $notificationRule)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $notificationRule->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can deactivate the notification rule.
     */
    public function deactivate(User $user, NotificationRule $notificationRule)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $notificationRule->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can test the notification rule.
     */
    public function test(User $user, NotificationRule $notificationRule)
    {
        // Check tenant isolation
        if ($user->tenant_id !== $notificationRule->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }
}
