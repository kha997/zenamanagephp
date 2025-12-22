<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant Ability Middleware
 * 
 * Ensures users have tenant-scoped access permissions.
 * This middleware validates that the user belongs to a tenant
 * and has the appropriate role/permissions within that tenant.
 */
class TenantAbilityMiddleware
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
            Log::warning('Unauthenticated request to tenant-scoped endpoint', [
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent(), 0, 50),
                'url' => $request->url(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Authentication required',
                'code' => 'AUTH_REQUIRED'
            ], 401);
        }

        // Check if user has tenant access
        if (!$user->tenant_id) {
            Log::warning('User without tenant access attempted to access tenant-scoped endpoint', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip(),
                'url' => $request->url(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return response()->json([
                'success' => false,
                'error' => 'No tenant access',
                'code' => 'NO_TENANT_ACCESS'
            ], 403);
        }

        // Check if user is active
        if (!$user->is_active) {
            Log::warning('Inactive user attempted to access tenant-scoped endpoint', [
                'user_id' => $user->id,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'ip' => $request->ip(),
                'url' => $request->url(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Account is inactive',
                'code' => 'ACCOUNT_INACTIVE'
            ], 403);
        }

        // Add tenant context to request
        $request->attributes->set('tenant_id', $user->tenant_id);
        $request->attributes->set('user_role', $user->role ?? 'member');

        return $next($request);
    }
}