<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkTemplate;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkTemplatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any work templates.
     */
    public function viewAny(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can view the work template.
     */
    public function view(User $user, WorkTemplate $workTemplate)
    {
        // Check tenant isolation if tenant_id exists
        if (isset($workTemplate->tenant_id) && $user->tenant_id !== $workTemplate->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can create work templates.
     */
    public function create(User $user)
    {
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer']);
    }

    /**
     * Determine whether the user can update the work template.
     */
    public function update(User $user, WorkTemplate $workTemplate)
    {
        // Check tenant isolation if tenant_id exists
        if (isset($workTemplate->tenant_id) && $user->tenant_id !== $workTemplate->tenant_id) {
            return false;
        }

        // Check role-based access
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer']);
    }

    /**
     * Determine whether the user can delete the work template.
     */
    public function delete(User $user, WorkTemplate $workTemplate)
    {
        // Check tenant isolation if tenant_id exists
        if (isset($workTemplate->tenant_id) && $user->tenant_id !== $workTemplate->tenant_id) {
            return false;
        }

        // Only super_admin and admin can delete
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can use the work template.
     */
    public function use(User $user, WorkTemplate $workTemplate)
    {
        // Check tenant isolation if tenant_id exists
        if (isset($workTemplate->tenant_id) && $user->tenant_id !== $workTemplate->tenant_id) {
            return false;
        }

        // All team members can use templates
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }

    /**
     * Determine whether the user can publish the work template.
     */
    public function publish(User $user, WorkTemplate $workTemplate)
    {
        // Check tenant isolation if tenant_id exists
        if (isset($workTemplate->tenant_id) && $user->tenant_id !== $workTemplate->tenant_id) {
            return false;
        }

        // Only management can publish templates
        return $user->hasRole(['super_admin', 'admin', 'pm']);
    }

    /**
     * Determine whether the user can duplicate the work template.
     */
    public function duplicate(User $user, WorkTemplate $workTemplate)
    {
        // Check tenant isolation if tenant_id exists
        if (isset($workTemplate->tenant_id) && $user->tenant_id !== $workTemplate->tenant_id) {
            return false;
        }

        // All team members can duplicate templates
        return $user->hasRole(['super_admin', 'admin', 'pm', 'designer', 'engineer']);
    }
}
