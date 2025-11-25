<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Admin Analytics Policy
 * 
 * Handles authorization for admin analytics operations.
 */
class AdminAnalyticsPolicy
{
    /**
     * Determine if user can view analytics
     */
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || 
               $user->can('admin.access') || 
               $user->can('admin.analytics.tenant');
    }

    /**
     * Determine if user can view analytics for a specific tenant
     */
    public function view(User $user, ?string $tenantId = null): bool
    {
        if (!$this->viewAny($user)) {
            return false;
        }
        
        // Super Admin can view analytics for any tenant
        if ($user->isSuperAdmin() || $user->can('admin.access')) {
            return true;
        }
        
        // Org Admin can only view analytics for their own tenant
        if ($user->can('admin.analytics.tenant')) {
            return $tenantId === null || $tenantId === $user->tenant_id;
        }
        
        return false;
    }
}
