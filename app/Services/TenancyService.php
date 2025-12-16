<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * TenancyService - Single source of truth for tenant resolution and membership
 * 
 * Provides centralized logic for:
 * - Resolving the active tenant (id + model)
 * - Reading membership for a user
 * 
 * This service centralizes tenant resolution logic that was previously
 * scattered across MeService and TenantController.
 */
class TenancyService
{
    /**
     * Resolve active tenant for user based on session, default, or fallback
     * 
     * Canonical tenant resolution rule (single source of truth):
     * Priority:
     * 1. Session selected_tenant_id (if user is a member of that tenant)
     * 2. Default tenant from pivot (is_default = true) via user->defaultTenant()
     * 3. Fallback to user->tenant_id (legacy) via user->defaultTenant()
     * 4. null if no tenant at all
     * 
     * This method ensures the tenants relationship is loaded so defaultTenant() can work correctly.
     * 
     * @param User $user
     * @param Request|null $request Optional request for session access
     * @return Tenant|null
     */
    public function resolveActiveTenant(User $user, ?Request $request = null): ?Tenant
    {
        // Ensure tenants relationship is loaded so defaultTenant() can access pivot data
        if (!$user->relationLoaded('tenants')) {
            $user->load('tenants');
        }

        // Check session for selected tenant
        $selectedTenantId = null;
        if ($request && $request->hasSession()) {
            $selectedTenantId = $request->session()->get('selected_tenant_id');
        }

        // If selected tenant ID is set and user is a member, use it
        if ($selectedTenantId) {
            $selectedTenant = $user->tenants()
                ->where('tenants.id', $selectedTenantId)
                ->first();
            
            if ($selectedTenant) {
                return $selectedTenant;
            }
            
            // If session has invalid tenant ID, clear it
            if ($request && $request->hasSession()) {
                $request->session()->forget('selected_tenant_id');
            }
        }

        // Use default tenant (from pivot or legacy)
        // Method defaultTenant() is null-safe and returns null if no tenant found
        // It checks: pivot is_default -> legacy tenant_id -> first tenant -> super_admin fallback
        $defaultTenant = $user->defaultTenant();
        return $defaultTenant;
    }

    /**
     * Resolve active tenant ID for user
     * 
     * Thin wrapper around resolveActiveTenant returning $tenant?->id
     * 
     * @param User $user
     * @param Request|null $request Optional request for session access
     * @return string|null
     */
    public function resolveActiveTenantId(User $user, ?Request $request = null): ?string
    {
        $tenant = $this->resolveActiveTenant($user, $request);
        return $tenant?->id;
    }

    /**
     * Get membership tenants for user
     * 
     * This can simply delegate to $user->getMembershipTenants().
     * Use this helper where it makes sense (MeService, TenantController).
     * 
     * @param User $user
     * @return Collection
     */
    public function getMembershipTenants(User $user): Collection
    {
        return $user->getMembershipTenants();
    }

    /**
     * Get current tenant permissions for user based on active tenant role
     * 
     * This method computes tenant-level permissions the same way MeService does,
     * ensuring consistency across the application.
     * 
     * @param User $user
     * @param Request|null $request Optional request for session access
     * @return array Array of permission strings (e.g., ['tenant.manage_members', 'tenant.view_projects'])
     */
    public function getCurrentTenantPermissions(User $user, ?Request $request = null): array
    {
        $activeTenant = $this->resolveActiveTenant($user, $request);
        $activeTenantId = $activeTenant?->id ?? null;

        if (!$activeTenantId) {
            return [];
        }

        // Get current tenant role from active membership
        $membership = $user->tenants()
            ->where('tenants.id', $activeTenantId)
            ->first();
        
        if (!$membership || !$membership->pivot) {
            return [];
        }

        $currentTenantRole = $membership->pivot->role;
        
        if (!$currentTenantRole) {
            return [];
        }

        // Get permissions from config based on tenant role
        $permissions = config('permissions.tenant_roles.' . $currentTenantRole, []);
        
        if (!is_array($permissions)) {
            return [];
        }

        return $permissions;
    }
}

