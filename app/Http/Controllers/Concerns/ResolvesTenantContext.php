<?php declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;
use App\Services\TenancyService;

/**
 * ResolvesTenantContext trait
 * 
 * Provides reusable tenant context resolution for legacy API controllers
 * that cannot or do not extend BaseApiV1Controller.
 * 
 * This trait mirrors the logic used by BaseApiV1Controller::getTenantId()
 * to ensure consistency across the application.
 * 
 * Priority order:
 * 1. Request attribute 'active_tenant_id' (set by EnsureTenantPermission middleware)
 * 2. TenancyService::resolveActiveTenantId() (session + default + fallback)
 * 3. Legacy user->tenant_id (backward compatibility)
 * 4. null if no tenant found
 */
trait ResolvesTenantContext
{
    /**
     * Resolve active tenant ID from request
     * 
     * @param Request $request
     * @return string|null Active tenant ID or null if not found
     */
    protected function resolveActiveTenantIdFromRequest(Request $request): ?string
    {
        // 1. If middleware (EnsureTenantPermission / tenant.scope) already attached active_tenant_id:
        if ($request->attributes->has('active_tenant_id')) {
            $activeTenantId = $request->attributes->get('active_tenant_id');
            if ($activeTenantId) {
                return (string) $activeTenantId;
            }
        }

        // 2. Fallback via TenancyService (session + pivot + legacy tenant_id):
        /** @var \App\Models\User|null $user */
        $user = $request->user();
        if (!$user) {
            return null;
        }

        /** @var TenancyService $tenancy */
        $tenancy = app(TenancyService::class);

        $resolvedTenantId = $tenancy->resolveActiveTenantId($user, $request);
        if ($resolvedTenantId) {
            return (string) $resolvedTenantId;
        }

        // 3. Fallback to legacy user->tenant_id (backward compatibility)
        if ($user->tenant_id) {
            return (string) $user->tenant_id;
        }

        return null;
    }
}

