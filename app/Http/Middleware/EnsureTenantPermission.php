<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\TenancyService;
use App\Support\ApiResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure Tenant Permission Middleware
 * 
 * Checks if the authenticated user has the required tenant-level permission
 * for the active tenant context.
 * 
 * Usage:
 * ->middleware(['auth:sanctum', 'tenant.permission:tenant.manage_members'])
 * 
 * This middleware:
 * 1. Resolves the active tenant using TenancyService
 * 2. Gets current tenant permissions based on the user's role in that tenant
 * 3. Checks if the required permission exists in the permission list
 * 4. Returns 403 if no active tenant or permission is missing
 * 
 * The middleware attaches the active tenant ID to the request for downstream use.
 */
class EnsureTenantPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  Required tenant permission (e.g., 'tenant.manage_members')
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::user();

        if (!$user) {
            return \App\Services\ErrorEnvelopeService::authenticationError(
                'User not authenticated',
                $request->header('X-Request-Id')
            );
        }

        // Resolve active tenant using TenancyService
        $tenancyService = app(TenancyService::class);
        $activeTenantId = $tenancyService->resolveActiveTenantId($user, $request);

        // Check if user has an active tenant
        if (!$activeTenantId) {
            return \App\Services\ErrorEnvelopeService::error(
                'TENANT_PERMISSION_DENIED',
                'No active tenant context',
                [],
                403,
                $request->header('X-Request-Id')
            );
        }

        // Get current tenant permissions
        $permissions = $tenancyService->getCurrentTenantPermissions($user, $request);

        // Check if required permission exists
        if (!in_array($permission, $permissions, true)) {
            return \App\Services\ErrorEnvelopeService::error(
                'TENANT_PERMISSION_DENIED',
                "Permission denied: {$permission}",
                [],
                403,
                $request->header('X-Request-Id')
            );
        }

        // Attach active tenant ID to request for downstream convenience
        $request->attributes->set('active_tenant_id', $activeTenantId);

        return $next($request);
    }
}

