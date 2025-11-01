<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

/**
 * User Policy
 * 
 * Handles authorization for user-related operations
 * with proper RBAC enforcement and tenant isolation.
 */
class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any users.
     */
    public function viewAny(User $user): bool
    {
        return $this->hasPermission($user, 'users.view');
    }

    /**
     * Determine whether the user can view the user.
     */
    public function view(User $user, User $model): bool
    {
        // Super admin can view any user
        if ($user->role === 'super_admin') {
            return true;
        }

        // Users can view themselves
        if ($user->id === $model->id) {
            return true;
        }

        // Check tenant isolation
        if ($user->tenant_id !== $model->tenant_id) {
            Log::warning('User attempted to view user from different tenant', [
                'user_id' => $user->id,
                'target_user_id' => $model->id,
                'user_tenant_id' => $user->tenant_id,
                'target_tenant_id' => $model->tenant_id,
            ]);
            return false;
        }

        return $this->hasPermission($user, 'users.view');
    }

    /**
     * Determine whether the user can create users.
     */
    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'users.create');
    }

    /**
     * Determine whether the user can update the user.
     */
    public function update(User $user, User $model): bool
    {
        // Super admin can update any user
        if ($user->role === 'super_admin') {
            return true;
        }

        // Users can update themselves
        if ($user->id === $model->id) {
            return true;
        }

        // Check tenant isolation
        if ($user->tenant_id !== $model->tenant_id) {
            Log::warning('User attempted to update user from different tenant', [
                'user_id' => $user->id,
                'target_user_id' => $model->id,
                'user_tenant_id' => $user->tenant_id,
                'target_tenant_id' => $model->tenant_id,
            ]);
            return false;
        }

        return $this->hasPermission($user, 'users.update');
    }

    /**
     * Determine whether the user can delete the user.
     */
    public function delete(User $user, User $model): bool
    {
        // Super admin can delete any user
        if ($user->role === 'super_admin') {
            return true;
        }

        // Users cannot delete themselves
        if ($user->id === $model->id) {
            Log::warning('User attempted to delete themselves', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        // Check tenant isolation
        if ($user->tenant_id !== $model->tenant_id) {
            Log::warning('User attempted to delete user from different tenant', [
                'user_id' => $user->id,
                'target_user_id' => $model->id,
                'user_tenant_id' => $user->tenant_id,
                'target_tenant_id' => $model->tenant_id,
            ]);
            return false;
        }

        return $this->hasPermission($user, 'users.delete');
    }

    /**
     * Determine whether the user can restore the user.
     */
    public function restore(User $user, User $model): bool
    {
        // Super admin can restore any user
        if ($user->role === 'super_admin') {
            return true;
        }

        // Check tenant isolation
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        return $this->hasPermission($user, 'users.restore');
    }

    /**
     * Determine whether the user can permanently delete the user.
     */
    public function forceDelete(User $user, User $model): bool
    {
        // Only super admin can force delete
        if ($user->role !== 'super_admin') {
            Log::warning('Non-super admin attempted force delete', [
                'user_id' => $user->id,
                'target_user_id' => $model->id,
                'user_role' => $user->role,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can manage user roles.
     */
    public function manageRoles(User $user, User $model): bool
    {
        // Super admin can manage any user's role
        if ($user->role === 'super_admin') {
            return true;
        }

        // Users cannot manage their own role
        if ($user->id === $model->id) {
            return false;
        }

        // Check tenant isolation
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        return $this->hasPermission($user, 'users.manage_roles');
    }

    /**
     * Determine whether the user can manage user permissions.
     */
    public function managePermissions(User $user, User $model): bool
    {
        // Super admin can manage any user's permissions
        if ($user->role === 'super_admin') {
            return true;
        }

        // Users cannot manage their own permissions
        if ($user->id === $model->id) {
            return false;
        }

        // Check tenant isolation
        if ($user->tenant_id !== $model->tenant_id) {
            return false;
        }

        return $this->hasPermission($user, 'users.manage_permissions');
    }

    /**
     * Check if user has specific permission
     */
    private function hasPermission(User $user, string $permission): bool
    {
        $role = $user->role ?? 'member';
        $permissions = config('permissions.roles.' . $role, []);

        if (in_array('*', $permissions)) {
            return true;
        }

        return in_array($permission, $permissions);
    }
}