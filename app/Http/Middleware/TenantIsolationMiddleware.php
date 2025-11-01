<?php declare(strict_types=1);

namespace App\Http\Middleware;

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
        
        // Super admin users don't need tenant_id
        if ((method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) || (isset($user->is_admin) && $user->is_admin)) {
            Log::info('Super admin access - bypassing tenant isolation', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);
            
            // Set global tenant context to null for super admin
            app()->instance('current_tenant_id', null);
            $request->attributes->set('tenant_id', null);
            $request->attributes->set('tenant_user', $user);
            
            return $next($request);
        }
        
        // Ensure regular users have a tenant_id
        if (!$user->tenant_id) {
            Log::warning('User without tenant_id attempted to access API', [
                'user_id' => $user->id,
                'email' => $user->email,
                'ip' => $request->ip()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'No Tenant Access',
                'message' => 'User is not assigned to any tenant',
                'code' => 'NO_TENANT_ACCESS'
            ], 403);
        }
        
        // Set tenant context globally
        app()->instance('current_tenant_id', $user->tenant_id);
        
        // Add tenant context to request
        $request->attributes->set('tenant_id', $user->tenant_id);
        $request->attributes->set('tenant_user', $user);
        
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