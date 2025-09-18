<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user)
    {
        // All authenticated users can view tasks list
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Task $task)
    {
        // SuperAdmin/Admin can view all tasks
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can view tasks in projects they manage
        if ($user->hasRole('project_manager') && $task->project->pm_id === $user->id) {
            return true;
        }

        // Assignee can view their assigned tasks
        if ($task->assignee_id === $user->id) {
            return true;
        }

        // Watchers can view tasks they're watching
        if ($task->watchers && in_array($user->id, $task->watchers)) {
            return true;
        }

        // Client can view client-visible tasks in their projects
        if ($user->hasRole('client') && 
            $task->project->client_id === $user->id && 
            $task->visibility === 'client') {
            return true;
        }

        // Team members can view tasks in projects they're assigned to
        if ($user->hasRole(['designer', 'site_engineer', 'qc', 'procurement', 'finance'])) {
            return $task->project->tasks()->whereHas('assignee', function ($query) use ($user) {
                $query->where('id', $user->id);
            })->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Task $task = null)
    {
        // SuperAdmin/Admin can create tasks in any project
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can create tasks in projects they manage
        if ($user->hasRole('project_manager') && $task && $task->project->pm_id === $user->id) {
            return true;
        }

        // Team members can create tasks in projects they're assigned to
        if ($user->hasRole(['designer', 'site_engineer', 'qc', 'procurement', 'finance'])) {
            return $task && $task->project->tasks()->whereHas('assignee', function ($query) use ($user) {
                $query->where('id', $user->id);
            })->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Task $task)
    {
        // SuperAdmin/Admin can update all tasks
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can update tasks in projects they manage
        if ($user->hasRole('project_manager') && $task->project->pm_id === $user->id) {
            return true;
        }

        // Assignee can update their assigned tasks
        if ($task->assignee_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task)
    {
        // Only SuperAdmin, Admin, and PM can delete tasks
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        if ($user->hasRole('project_manager') && $task->project->pm_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can assign the task.
     */
    public function assign(User $user, Task $task)
    {
        // SuperAdmin/Admin can assign any task
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can assign tasks in projects they manage
        if ($user->hasRole('project_manager') && $task->project->pm_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can comment on the task.
     */
    public function comment(User $user, Task $task)
    {
        // SuperAdmin/Admin can comment on any task
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can comment on tasks in projects they manage
        if ($user->hasRole('project_manager') && $task->project->pm_id === $user->id) {
            return true;
        }

        // Assignee can comment on their assigned tasks
        if ($task->assignee_id === $user->id) {
            return true;
        }

        // Watchers can comment on tasks they're watching
        if ($task->watchers && in_array($user->id, $task->watchers)) {
            return true;
        }

        // Client can comment on client-visible tasks in their projects
        if ($user->hasRole('client') && 
            $task->project->client_id === $user->id && 
            $task->visibility === 'client') {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can attach files to the task.
     */
    public function attachFiles(User $user, Task $task)
    {
        // SuperAdmin/Admin can attach files to any task
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can attach files to tasks in projects they manage
        if ($user->hasRole('project_manager') && $task->project->pm_id === $user->id) {
            return true;
        }

        // Assignee can attach files to their assigned tasks
        if ($task->assignee_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can change task status.
     */
    public function changeStatus(User $user, Task $task)
    {
        // SuperAdmin/Admin can change status of any task
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can change status of tasks in projects they manage
        if ($user->hasRole('project_manager') && $task->project->pm_id === $user->id) {
            return true;
        }

        // Assignee can change status of their assigned tasks
        if ($task->assignee_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can view task time tracking.
     */
    public function viewTimeTracking(User $user, Task $task)
    {
        // SuperAdmin/Admin can view time tracking for any task
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can view time tracking for tasks in projects they manage
        if ($user->hasRole('project_manager') && $task->project->pm_id === $user->id) {
            return true;
        }

        // Assignee can view time tracking for their assigned tasks
        if ($task->assignee_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can edit task time tracking.
     */
    public function editTimeTracking(User $user, Task $task)
    {
        // SuperAdmin/Admin can edit time tracking for any task
        if ($user->hasRole(['super_admin', 'admin'])) {
            return true;
        }

        // PM can edit time tracking for tasks in projects they manage
        if ($user->hasRole('project_manager') && $task->project->pm_id === $user->id) {
            return true;
        }

        // Assignee can edit time tracking for their assigned tasks
        if ($task->assignee_id === $user->id) {
            return true;
        }

        return false;
    }
}