<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        // SuperAdmin/Admin can view all users
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // Project Managers can view users
        if ($user->hasRole('project_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model)
    {
        // SuperAdmin/Admin can view all users
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // Users can view their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Project Managers can view users
        if ($user->hasRole('project_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        // Only SuperAdmin, Admin, and Project Managers can create users
        return $user->hasRole(['super_admin', 'admin', 'project_manager']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model)
    {
        // SuperAdmin/Admin can update all users
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // Users can update their own profile
        if ($user->id === $model->id) {
            return true;
        }

        // Project Managers can update users
        if ($user->hasRole('project_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model)
    {
        // Only SuperAdmin and Admin can delete users
        return $user->hasRole(['super_admin', 'admin']);
    }
}