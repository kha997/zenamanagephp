<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Policy
 * 
 * Handles authorization for tenant-related operations
 * with proper RBAC enforcement.
 */
class TenantPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any tenants.
     */
    public function viewAny(User $user): bool
    {
        // Only super admin can view all tenants
        return $user->role === 'super_admin';
    }

    /**
     * Determine whether the user can view the tenant.
     */
    public function view(User $user, Tenant $tenant): bool
    {
        // Super admin can view any tenant
        if ($user->role === 'super_admin') {
            return true;
        }

        // Users can only view their own tenant
        if ($user->tenant_id === $tenant->id) {
            return $this->hasPermission($user, 'tenants.view');
        }

        Log::warning('User attempted to view different tenant', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'user_tenant_id' => $user->tenant_id,
        ]);

        return false;
    }

    /**
     * Determine whether the user can create tenants.
     */
    public function create(User $user): bool
    {
        // Only super admin can create tenants
        if ($user->role === 'super_admin') {
            return true;
        }

        // Regular users cannot create tenants
        Log::warning('Non-super admin attempted to create tenant', [
            'user_id' => $user->id,
            'user_role' => $user->role,
        ]);

        return false;
    }

    /**
     * Determine whether the user can update the tenant.
     */
    public function update(User $user, Tenant $tenant): bool
    {
        // Super admin can update any tenant
        if ($user->role === 'super_admin') {
            return true;
        }

        // Users can only update their own tenant
        if ($user->tenant_id === $tenant->id) {
            return $this->hasPermission($user, 'tenants.update');
        }

        Log::warning('User attempted to update different tenant', [
            'user_id' => $user->id,
            'tenant_id' => $tenant->id,
            'user_tenant_id' => $user->tenant_id,
        ]);

        return false;
    }

    /**
     * Determine whether the user can delete the tenant.
     */
    public function delete(User $user, Tenant $tenant): bool
    {
        // Only super admin can delete tenants
        if ($user->role !== 'super_admin') {
            Log::warning('Non-super admin attempted to delete tenant', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'user_role' => $user->role,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the tenant.
     */
    public function restore(User $user, Tenant $tenant): bool
    {
        // Only super admin can restore tenants
        return $user->role === 'super_admin';
    }

    /**
     * Determine whether the user can permanently delete the tenant.
     */
    public function forceDelete(User $user, Tenant $tenant): bool
    {
        // Only super admin can force delete tenants
        if ($user->role !== 'super_admin') {
            Log::warning('Non-super admin attempted force delete tenant', [
                'user_id' => $user->id,
                'tenant_id' => $tenant->id,
                'user_role' => $user->role,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can manage tenant settings.
     */
    public function manageSettings(User $user, Tenant $tenant): bool
    {
        // Super admin can manage any tenant's settings
        if ($user->role === 'super_admin') {
            return true;
        }

        // Users can only manage their own tenant's settings
        if ($user->tenant_id === $tenant->id) {
            return $this->hasPermission($user, 'tenants.manage_settings');
        }

        return false;
    }

    /**
     * Determine whether the user can manage tenant users.
     */
    public function manageUsers(User $user, Tenant $tenant): bool
    {
        // Super admin can manage any tenant's users
        if ($user->role === 'super_admin') {
            return true;
        }

        // Users can only manage their own tenant's users
        if ($user->tenant_id === $tenant->id) {
            return $this->hasPermission($user, 'tenants.manage_users');
        }

        return false;
    }

    /**
     * Determine whether the user can manage tenant billing.
     */
    public function manageBilling(User $user, Tenant $tenant): bool
    {
        // Super admin can manage any tenant's billing
        if ($user->role === 'super_admin') {
            return true;
        }

        // Users can only manage their own tenant's billing
        if ($user->tenant_id === $tenant->id) {
            return $this->hasPermission($user, 'tenants.manage_billing');
        }

        return false;
    }

    /**
     * Determine whether the user can access tenant analytics.
     */
    public function viewAnalytics(User $user, Tenant $tenant): bool
    {
        // Super admin can view any tenant's analytics
        if ($user->role === 'super_admin') {
            return true;
        }

        // Users can only view their own tenant's analytics
        if ($user->tenant_id === $tenant->id) {
            return $this->hasPermission($user, 'tenants.view_analytics');
        }

        return false;
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