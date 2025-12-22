<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure Admin Access Middleware
 * 
 * Allows access to admin routes for:
 * - Super Admin (admin.access - system scope)
 * - Org Admin (admin.access.tenant - tenant scope)
 */
class EnsureAdminAccess
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
            Log::warning('Unauthenticated request to admin endpoint', [
                'ip' => $request->ip(),
                'user_agent' => substr($request->userAgent(), 0, 50),
                'url' => $request->url(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required',
                    'code' => 'AUTH_REQUIRED'
                ], 401);
            }
            
            return redirect()->route('login')->with('error', 'Authentication required');
        }

        // Check if user is active
        if (!$user->is_active) {
            Log::warning('Inactive user attempted to access admin endpoint', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
                'ip' => $request->ip(),
                'url' => $request->url(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Account is inactive',
                    'error' => [
                        'id' => 'ACCOUNT_INACTIVE',
                        'code' => 'ACCOUNT_INACTIVE',
                        'details' => 'Account is inactive'
                    ]
                ], 403);
            }
            
            abort(403, 'Account is inactive');
        }

        // Check Super Admin access (admin.access - all permissions)
        if ($user->can('admin.access') || $user->isSuperAdmin()) {
            // Super Admin - system scope
            $request->attributes->set('admin_scope', 'system');
            $request->attributes->set('is_super_admin', true);
            $request->attributes->set('admin_role', 'super_admin');
            
            return $next($request);
        }

        // Check Org Admin access (admin.access.tenant)
        if ($user->can('admin.access.tenant')) {
            // Org Admin - tenant scope
            if (!$user->tenant_id) {
                Log::warning('Org Admin user without tenant_id attempted to access admin endpoint', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip' => $request->ip(),
                    'url' => $request->url(),
                    'request_id' => $request->header('X-Request-Id')
                ]);

                if ($request->expectsJson()) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Tenant context required for Org Admin',
                        'code' => 'TENANT_REQUIRED'
                    ], 403);
                }
                
                abort(403, 'Tenant context required');
            }

            $request->attributes->set('admin_scope', 'tenant');
            $request->attributes->set('tenant_id', $user->tenant_id);
            $request->attributes->set('is_super_admin', false);
            $request->attributes->set('admin_role', 'org_admin');
            
            return $next($request);
        }

        // No admin access
        Log::warning('User without admin access attempted to access admin endpoint', [
            'user_id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'tenant_id' => $user->tenant_id,
            'ip' => $request->ip(),
            'url' => $request->url(),
            'request_id' => $request->header('X-Request-Id')
        ]);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'error' => 'Admin access required',
                'code' => 'ADMIN_ACCESS_REQUIRED'
            ], 403);
        }
        
        abort(403, 'Admin access required');
    }
}
