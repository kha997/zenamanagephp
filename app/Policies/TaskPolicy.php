<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\Task;
use Illuminate\Auth\Access\HandlesAuthorization;

class TaskPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Task $task): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $task->tenant_id) {
            return false;
        }

        // Creator can view
        if ($task->created_by === $user->id) {
            return true;
        }

        // Assignee can view
        if ($task->assignee_id === $user->id) {
            return true;
        }

        return true; // Allow all tenant users to view tasks
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->tenant_id !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Task $task): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $task->tenant_id) {
            return false;
        }

        // Creator can update
        if ($task->created_by === $user->id) {
            return true;
        }

        // Assignee can update
        if ($task->assignee_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): bool
    {
        // Multi-tenant isolation
        if ($user->tenant_id !== $task->tenant_id) {
            return false;
        }

        // Creator can delete
        if ($task->created_by === $user->id) {
            return true;
        }

        return false;
    }
}