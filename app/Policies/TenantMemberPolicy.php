<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Support\Facades\Log;

/**
 * Tenant Member Policy
 * 
 * Handles authorization for tenant-scoped member management operations.
 * Used by Org Admin to manage members within their tenant.
 */
class TenantMemberPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any tenant members.
     */
    public function viewAny(User $user): bool
    {
        // Only Org Admin with admin.members.manage permission
        return $user->can('admin.members.manage') && !$user->isSuperAdmin();
    }

    /**
     * Determine whether the user can view a specific member.
     */
    public function view(User $user, User $member): bool
    {
        // Must have permission
        if (!$user->can('admin.members.manage') || $user->isSuperAdmin()) {
            return false;
        }

        // Must be in same tenant
        if ($user->tenant_id !== $member->tenant_id) {
            Log::warning('Org Admin attempted to view member from different tenant', [
                'user_id' => $user->id,
                'member_id' => $member->id,
                'user_tenant_id' => $user->tenant_id,
                'member_tenant_id' => $member->tenant_id,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can invite a new member.
     */
    public function invite(User $user): bool
    {
        // Only Org Admin with permission (not Super Admin)
        return $user->can('admin.members.manage') && !$user->isSuperAdmin();
    }

    /**
     * Determine whether the user can update a member's role.
     */
    public function updateRole(User $user, User $member): bool
    {
        // Must have permission
        if (!$user->can('admin.members.manage') || $user->isSuperAdmin()) {
            return false;
        }

        // Must be in same tenant
        if ($user->tenant_id !== $member->tenant_id) {
            Log::warning('Org Admin attempted to update role of member from different tenant', [
                'user_id' => $user->id,
                'member_id' => $member->id,
                'user_tenant_id' => $user->tenant_id,
                'member_tenant_id' => $member->tenant_id,
            ]);
            return false;
        }

        // Cannot change own role
        if ($user->id === $member->id) {
            Log::warning('Org Admin attempted to change own role', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can remove a member from the tenant.
     */
    public function remove(User $user, User $member): bool
    {
        // Must have permission
        if (!$user->can('admin.members.manage') || $user->isSuperAdmin()) {
            return false;
        }

        // Must be in same tenant
        if ($user->tenant_id !== $member->tenant_id) {
            Log::warning('Org Admin attempted to remove member from different tenant', [
                'user_id' => $user->id,
                'member_id' => $member->id,
                'user_tenant_id' => $user->tenant_id,
                'member_tenant_id' => $member->tenant_id,
            ]);
            return false;
        }

        // Cannot remove self
        if ($user->id === $member->id) {
            Log::warning('Org Admin attempted to remove self', [
                'user_id' => $user->id,
            ]);
            return false;
        }

        return true;
    }
}

