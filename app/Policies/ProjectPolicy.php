<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProjectPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        // All authenticated users can view projects list
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Project $project)
    {
        // SuperAdmin/Admin can view all projects
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can view projects they manage
        if ($user->hasRole('project_manager') && $project->pm_id === $user->id) {
            return true;
        }

        // Client can view their own projects
        if ($user->hasRole('client') && $project->client_id === $user->id) {
            return true;
        }

        // Team members can view projects they're assigned to
        if ($user->hasRole(['designer', 'site_engineer', 'qc', 'procurement', 'finance'])) {
            return $project->tasks()->whereHas('assignee', function ($query) use ($user) {
                $query->where('id', $user->id);
            })->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        // Only SuperAdmin, Admin, and Project Managers can create projects
        return $user->hasRole(['super_admin', 'admin', 'project_manager']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Project $project)
    {
        // SuperAdmin/Admin can update all projects
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can update projects they manage
        if ($user->hasRole('project_manager') && $project->pm_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Project $project)
    {
        // Only SuperAdmin and Admin can delete projects
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project)
    {
        // Only SuperAdmin and Admin can restore projects
        return $user->hasRole(['super_admin', 'admin']);
    }

    /**
     * Determine whether the user can archive the model.
     */
    public function archive(User $user, Project $project)
    {
        // SuperAdmin/Admin can archive all projects
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can archive projects they manage
        if ($user->hasRole('project_manager') && $project->pm_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can duplicate the model.
     */
    public function duplicate(User $user, Project $project)
    {
        // SuperAdmin/Admin can duplicate all projects
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can duplicate projects they manage
        if ($user->hasRole('project_manager') && $project->pm_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can manage team members.
     */
    public function manageTeam(User $user, Project $project)
    {
        // SuperAdmin/Admin can manage all project teams
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can manage team for projects they manage
        if ($user->hasRole('project_manager') && $project->pm_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view project budget.
     */
    public function viewBudget(User $user, Project $project)
    {
        // SuperAdmin/Admin can view all budgets
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can view budget for projects they manage
        if ($user->hasRole('project_manager') && $project->pm_id === $user->id) {
            return true;
        }

        // Finance can view budgets for all projects
        if ($user->hasRole('finance')) {
            return true;
        }

        // Client can view budget for their projects
        if ($user->hasRole('client') && $project->client_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can edit project budget.
     */
    public function editBudget(User $user, Project $project)
    {
        // Only SuperAdmin, Admin, and Finance can edit budgets
        return $user->hasRole(['super_admin', 'admin', 'finance']);
    }

    /**
     * Determine whether the user can view project files.
     */
    public function viewFiles(User $user, Project $project)
    {
        // SuperAdmin/Admin can view all files
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can view files for projects they manage
        if ($user->hasRole('project_manager') && $project->pm_id === $user->id) {
            return true;
        }

        // Team members can view files for projects they're assigned to
        if ($user->hasRole(['designer', 'site_engineer', 'qc', 'procurement', 'finance'])) {
            return $project->tasks()->whereHas('assignee', function ($query) use ($user) {
                $query->where('id', $user->id);
            })->exists();
        }

        // Client can view files for their projects
        if ($user->hasRole('client') && $project->client_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can upload project files.
     */
    public function uploadFiles(User $user, Project $project)
    {
        // SuperAdmin/Admin can upload files to all projects
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can upload files to projects they manage
        if ($user->hasRole('project_manager') && $project->pm_id === $user->id) {
            return true;
        }

        // Team members can upload files to projects they're assigned to
        if ($user->hasRole(['designer', 'site_engineer', 'qc', 'procurement', 'finance'])) {
            return $project->tasks()->whereHas('assignee', function ($query) use ($user) {
                $query->where('id', $user->id);
            })->exists();
        }

        return false;
    }
}