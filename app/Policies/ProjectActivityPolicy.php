<?php

namespace App\Policies;

use App\Models\ProjectActivity;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectActivityPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any project activities.
     */
    public function viewAny(User $user): bool
    {
        return $user->is_active;
    }

    /**
     * Determine whether the user can view the project activity.
     */
    public function view(User $user, ProjectActivity $projectActivity): bool
    {
        // Admin can view any activity
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($projectActivity->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Project members can view project activities
        if ($projectActivity->project_id && $user->hasRole('project_manager')) {
            return true;
        }

        // Users can view activities they created
        if ($projectActivity->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can create project activities.
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
     * Determine whether the user can update the project activity.
     */
    public function update(User $user, ProjectActivity $projectActivity): bool
    {
        // Admin can update any activity
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($projectActivity->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Users can update activities they created
        if ($projectActivity->user_id === $user->id) {
            return true;
        }

        // Project managers can update project activities
        if ($projectActivity->project_id && $user->hasRole('project_manager')) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the project activity.
     */
    public function delete(User $user, ProjectActivity $projectActivity): bool
    {
        // Admin can delete any activity
        if ($user->hasRole('admin')) {
            return true;
        }

        // Check tenant isolation
        if ($projectActivity->tenant_id !== $user->tenant_id) {
            return false;
        }

        // Users can delete activities they created
        if ($projectActivity->user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can restore the project activity.
     */
    public function restore(User $user, ProjectActivity $projectActivity): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can permanently delete the project activity.
     */
    public function forceDelete(User $user, ProjectActivity $projectActivity): bool
    {
        return $user->hasRole('admin');
    }
}
