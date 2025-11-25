<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use App\Services\TenancyService;

/**
 * MeService - Builds standardized "me" response for authentication endpoints
 * 
 * Provides a single source of truth for user context data including:
 * - User information
 * - Permissions
 * - Abilities
 * - Tenant summary
 * - Onboarding state
 */
class MeService
{
    /**
     * Build standardized "me" response for a user
     * 
     * @param User $user
     * @param Request|null $request Optional request for session access
     * @return array
     */
    public function buildMeResponse(User $user, ?Request $request = null): array
    {
        // Get active tenant via TenancyService (from session, default, or fallback)
        $tenancyService = app(TenancyService::class);
        $activeTenant = $tenancyService->resolveActiveTenant($user, $request);
        $activeTenantId = $activeTenant?->id ?? null;

        // Get current tenant role from active membership
        $currentTenantRole = null;
        if ($activeTenantId) {
            $membership = $user->tenants()
                ->where('tenants.id', $activeTenantId)
                ->first();
            
            if ($membership && $membership->pivot) {
                $currentTenantRole = $membership->pivot->role;
            }
        }

        // Get user role
        $role = $user->role ?? 'member';
        
        // Get user permissions from config
        $permissions = config('permissions.roles.' . $role, []);
        
        // Get user abilities
        $abilities = $this->getUserAbilities($user);
        
        // Get tenants summary
        $tenantsSummary = $this->getTenantsSummary($user);
        
        // Get onboarding state
        $onboardingState = $this->getOnboardingState($user, $activeTenantId);
        
        // Get tenant-level permissions based on current_tenant_role
        $tenantRolePermissions = [];
        if ($currentTenantRole) {
            $tenantRolePermissions = config('permissions.tenant_roles.' . $currentTenantRole, []);
            if (!is_array($tenantRolePermissions)) {
                $tenantRolePermissions = [];
            }
        }
        
        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                // Note: tenant_id in response represents the ACTIVE tenant, not necessarily the DB column
                // This allows SPA to treat user.tenant_id as "current tenant" even in multi-tenant world
                'tenant_id' => $activeTenantId,
                'role' => $role,
                'email_verified_at' => $user->email_verified_at?->toISOString(),
                'last_login_at' => $user->last_login_at?->toISOString(),
                'created_at' => $user->created_at->toISOString(),
                'is_active' => $user->is_active ?? true,
            ],
            'permissions' => $permissions,
            'abilities' => $abilities,
            'tenants_summary' => $tenantsSummary,
            'onboarding_state' => $onboardingState,
            'current_tenant_role' => $currentTenantRole,
            'current_tenant_permissions' => $tenantRolePermissions,
        ];
    }

    /**
     * Get active tenant for user based on session, default, or fallback
     * 
     * @deprecated Use TenancyService::resolveActiveTenant() instead
     * This method is kept for backward compatibility but delegates to TenancyService
     * 
     * @param User|null $user
     * @param Request|null $request
     * @return Tenant|null
     */
    public function getActiveTenant(?User $user, ?Request $request): ?Tenant
    {
        if (!$user) {
            return null;
        }

        $tenancyService = app(TenancyService::class);
        return $tenancyService->resolveActiveTenant($user, $request);
    }
    
    /**
     * Get user abilities (admin, tenant, etc.)
     * 
     * @param User $user
     * @return array
     */
    private function getUserAbilities(User $user): array
    {
        $abilities = [];
        
        // Check if super admin
        $isSuperAdmin = $user->isSuperAdmin() || $user->can('admin.access');
        
        // Check if org admin
        $isOrgAdmin = $user->can('admin.access.tenant');
        
        if ($isSuperAdmin) {
            $abilities[] = 'admin';
        }
        
        // Check if user has tenant membership (pivot or legacy)
        $hasTenantMembership = $user->getMembershipTenants()->isNotEmpty();
        
        if ($isOrgAdmin || $hasTenantMembership) {
            $abilities[] = 'tenant';
        }
        
        // Optionally add tenant_admin ability for elevated tenant roles
        // This is determined from the active tenant role, but we need to check it
        // Note: We can't easily get current_tenant_role here without duplicating logic,
        // so we'll skip this for now. If needed, we can add it later.
        
        return $abilities;
    }
    
    /**
     * Get tenants summary for user from membership pivot
     * 
     * @param User $user
     * @return array
     */
    private function getTenantsSummary(User $user): array
    {
        // Get tenants from pivot membership
        $membershipTenants = $user->getMembershipTenants();
        
        $tenants = $membershipTenants->map(function ($tenant) {
            return [
                'id' => $tenant->id,
                'name' => $tenant->name ?? 'Unknown Tenant',
                'slug' => $tenant->slug ?? null,
                // Include is_default if available from pivot (null-safe for legacy fallback)
                'is_default' => optional($tenant->pivot)->is_default ?? false,
                // Include role if available from pivot
                'role' => optional($tenant->pivot)->role,
            ];
        })->values()->all();
        
        return [
            'count' => count($tenants),
            'items' => $tenants,
        ];
    }
    
    /**
     * Get onboarding state for user
     * 
     * @param User $user
     * @param string|null $activeTenantId Active tenant ID (from getActiveTenant)
     * @return string
     */
    private function getOnboardingState(User $user, ?string $activeTenantId = null): string
    {
        if (!$user->email_verified_at) {
            return 'email_verification';
        }
        
        // Check if user has any tenant membership (pivot or legacy)
        $hasTenant = $activeTenantId || $user->getMembershipTenants()->isNotEmpty();
        
        if (!$hasTenant) {
            return 'tenant_setup';
        }
        
        return 'completed';
    }
}

