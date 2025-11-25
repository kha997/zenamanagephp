<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Admin Activities Policy
 * 
 * Handles authorization for admin activities/audit log operations.
 */
class AdminActivitiesPolicy
{
    /**
     * Determine if user can view activities
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || 
               $user->can('admin.access') || 
               $user->can('admin.activities.tenant');
    }

    /**
     * Determine if user can view activities for a specific tenant
     */
    public function view(User $user, ?string $tenantId = null): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }
        
        // Super Admin can view activities for any tenant
        if ($user->isSuperAdmin() || $user->can('admin.access')) {
            return true;
        }
        
        // Org Admin can only view activities for their own tenant
        if ($user->can('admin.activities.tenant')) {
            return $tenantId === null || $tenantId === $user->tenant_id;
        }
        
        return false;
    }
}
