<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Admin Settings Policy
 * 
 * Handles authorization for admin settings operations.
 */
class AdminSettingsPolicy
{
    /**
     * Determine if user can view system settings
     */
    public function viewSystemSettings(User $user): bool
    {
        return $user->isSuperAdmin() || $user->can('admin.access');
    }

    /**
     * Determine if user can update system settings
     */
    public function updateSystemSettings(User $user): bool
    {
        return $this->viewSystemSettings($user); // Only Super Admin
    }

    /**
     * Determine if user can view tenant settings
     */
    public function viewTenantSettings(User $user): bool
    {
        return $user->isSuperAdmin() || 
               $user->can('admin.access') || 
               $user->can('admin.settings.tenant');
    }

    /**
     * Determine if user can update tenant settings
     */
    public function updateTenantSettings(User $user, ?string $tenantId = null): bool
    {
        if (!$this->viewTenantSettings($user)) {
            return false;
        }
        
        // Super Admin can update any tenant settings
        if ($user->isSuperAdmin() || $user->can('admin.access')) {
            return true;
        }
        
        // Org Admin can only update their own tenant settings
        if ($user->can('admin.settings.tenant')) {
            return $tenantId === null || $tenantId === $user->tenant_id;
        }
        
        return false;
    }
}
