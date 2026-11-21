<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
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
     * Determine whether the user can update the model (fields, status, completion, block/unblock, dependencies).
     *
     * UAT-RECOVERY-04/04b. Operation-specific, tenant-bound, canonical DB permissions + task relationships;
     * `users.role` is never consulted and no permission (task.delete included, O-10) implies another operation:
     *   (a) assignee: tasks.assignee_id, or a task_assignments row with role = assignee;
     *   (b) the owning project's PM (projects.pm_id) who holds task.update.
     */
    public function update(User $user, Task $task)
    {
        return $this->sameTenant($user, $task)
            && ($this->isAssignee($user, $task) || $this->isProjectManagerHolding($user, $task, 'task.update'));
    }

    /**
     * Determine whether the user can delete the model.
     * Independent operation: the owning project's PM (holding task.update) or a holder of task.delete.
     */
    public function delete(User $user, Task $task)
    {
        return $this->sameTenant($user, $task)
            && ($this->isProjectManagerHolding($user, $task, 'task.update') || $user->hasPermission('task.delete'));
    }

    /**
     * Determine whether the user can assign / reassign / unassign the task.
     * Only the owning project's PM holding task.assign. Being an assignee, or holding any other permission, is not enough.
     */
    public function assign(User $user, Task $task)
    {
        return $this->sameTenant($user, $task)
            && $this->isProjectManagerHolding($user, $task, 'task.assign');
    }

    private function sameTenant(User $user, Task $task): bool
    {
        return $user->tenant_id !== null && (string) $user->tenant_id === (string) $task->tenant_id;
    }

    private function isAssignee(User $user, Task $task): bool
    {
        if ($task->assignee_id !== null && (string) $task->assignee_id === (string) $user->id) {
            return true;
        }

        return $task->assignments()
            ->where('user_id', $user->id)
            ->where('role', 'assignee')
            ->exists();
    }

    private function isProjectManagerHolding(User $user, Task $task, string $permission): bool
    {
        $pmId = $task->project?->pm_id;

        return $pmId !== null
            && (string) $pmId === (string) $user->id
            && $user->hasPermission($permission);
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
