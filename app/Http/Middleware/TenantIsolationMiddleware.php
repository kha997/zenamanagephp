<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ErrorEnvelopeService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant Isolation Middleware
 * 
 * Ensures that all database queries are properly scoped to the authenticated user's tenant.
 * This is critical for multi-tenant security.
 */
class TenantIsolationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
                'message' => 'User not authenticated',
                'code' => 'USER_NOT_AUTHENTICATED'
            ], 401);
        }

        $headerTenantId = trim((string) $request->header('X-Tenant-ID'));

        if ($headerTenantId === '') {
            return ErrorEnvelopeService::error(
                'TENANT_REQUIRED',
                'X-Tenant-ID header is required',
                [],
                400,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        // Ensure user has a tenant_id
        if (!$user->tenant_id) {
            Log::warning('User without tenant_id attempted to access API', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);

            return ErrorEnvelopeService::error(
                'NO_TENANT_ACCESS',
                'User is not assigned to any tenant',
                [],
                403,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        $userTenantId = (string) $user->tenant_id;

        if ($userTenantId !== $headerTenantId) {
            Log::info('Tenant mismatch debug', [
                'route' => $request->route()?->getName(),
                'header_tenant' => $headerTenantId,
                'user_tenant' => $userTenantId,
                'user_id' => $user->id,
                'token_prefix' => substr((string) $request->bearerToken(), 0, 5),
            ]);
            Log::warning('Tenant mismatch', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'header_tenant_id' => $headerTenantId,
                'ip' => $request->ip(),
            ]);

            return ErrorEnvelopeService::error(
                'TENANT_INVALID',
                'X-Tenant-ID does not match authenticated user',
                [],
                403,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        $tenantId = $headerTenantId;

        // Set tenant context globally
        app()->instance('current_tenant_id', $tenantId);
        app()->instance('tenant', $user->tenant);

        // Add tenant context to request
        $request->attributes->set('tenant_id', $tenantId);
        $request->attributes->set('tenant_user', $user);
        $request->merge(['tenant_id' => $tenantId]);
        
        // Log tenant access
        Log::info('Tenant isolation applied', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'ip' => $request->ip()
        ]);
        
        return $next($request);
    }
}
