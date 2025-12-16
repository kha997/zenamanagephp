<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensure System Admin Middleware
 * 
 * Only allows Super Admin (admin.access) to access system-only routes.
 * Used for routes like /admin/tenants, /admin/users, /admin/security, /admin/maintenance
 */
class EnsureSystemAdmin
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
            Log::warning('Unauthenticated request to system admin endpoint', [
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
            Log::warning('Inactive user attempted to access system admin endpoint', [
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

        // Only Super Admin can access system-only routes
        if (!$user->isSuperAdmin() && !$user->can('admin.access')) {
            Log::warning('Non-super-admin user attempted to access system admin endpoint', [
                'user_id' => $user->id,
                'email' => $user->email,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
                'ip' => $request->ip(),
                'url' => $request->url(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            if ($request->expectsJson()) {
                $suggestion = null;
                // If Org Admin tries to access /admin/users, suggest /admin/members
                if ($user->can('admin.members.manage') && str_contains($request->path(), 'admin/users')) {
                    $suggestion = [
                        'message' => 'You can manage members in your tenant at /admin/members',
                        'redirect_to' => '/admin/members'
                    ];
                }
                
                return response()->json([
                    'success' => false,
                    'error' => 'Super Admin access required',
                    'code' => 'SUPER_ADMIN_REQUIRED',
                    'suggestion' => $suggestion
                ], 403);
            }
            
            $message = 'Super Admin access required';
            // If Org Admin tries to access /admin/users, suggest /admin/members
            if ($user->can('admin.members.manage') && str_contains($request->path(), 'admin/users')) {
                $message .= '. Org Admin can manage members at /admin/members';
            }
            
            abort(403, $message);
        }

        // Set system admin context
        $request->attributes->set('admin_scope', 'system');
        $request->attributes->set('is_super_admin', true);
        $request->attributes->set('admin_role', 'super_admin');

        return $next($request);
    }
}
