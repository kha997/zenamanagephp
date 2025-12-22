<?php declare(strict_types=1);

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Trait ScopesByAdminAccess
 * 
 * Provides automatic tenant scoping for admin queries based on user's admin access level.
 * 
 * Usage:
 * - Super Admin: No filter (sees all tenants)
 * - Org Admin: Filter by tenant_id (sees only their tenant)
 */
trait ScopesByAdminAccess
{
    /**
     * Scope query based on admin access level
     * 
     * @param Builder $query
     * @param User $user
     * @return Builder
     */
    public function scopeForAdmin(Builder $query, User $user): Builder
    {
        // Super Admin has admin.access - no filter
        if ($user->isSuperAdmin() || $user->can('admin.access')) {
            return $query; // No filter for Super Admin
        }
        
        // Org Admin has admin.access.tenant - filter by tenant_id
        if ($user->can('admin.access.tenant')) {
            if (!$user->tenant_id) {
                // Org Admin without tenant_id should not see anything
                return $query->whereRaw('1 = 0'); // Empty result
            }
            
            return $query->where('tenant_id', $user->tenant_id);
        }
        
        // No admin access - return empty result
        return $query->whereRaw('1 = 0');
    }
    
    /**
     * Scope query to only include tenant-scoped data
     * Used when you explicitly want tenant filtering regardless of admin level
     * 
     * @param Builder $query
     * @param User $user
     * @return Builder
     */
    public function scopeForTenant(Builder $query, User $user): Builder
    {
        if (!$user->tenant_id) {
            return $query->whereRaw('1 = 0'); // Empty result if no tenant
        }
        
        return $query->where('tenant_id', $user->tenant_id);
    }
}

