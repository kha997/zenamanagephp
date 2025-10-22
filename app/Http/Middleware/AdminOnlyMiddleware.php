<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin Only Middleware
 * 
 * Ensures only admin users can access admin-level endpoints.
 * This middleware validates that the user has admin or super_admin role.
 */
class AdminOnlyMiddleware
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

        // Check if user has admin role
        $adminRoles = ['super_admin', 'admin'];
        if (!in_array($user->role, $adminRoles)) {
            Log::warning('Non-admin user attempted to access admin endpoint', [
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
                    'code' => 'ADMIN_REQUIRED'
                ], 403);
            }
            
            abort(403, 'Admin access required');
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
                    'success' => false,
                    'error' => 'Account is inactive',
                    'code' => 'ACCOUNT_INACTIVE'
                ], 403);
            }
            
            abort(403, 'Account is inactive');
        }

        // Add admin context to request
        $request->attributes->set('admin_role', $user->role);
        $request->attributes->set('is_super_admin', $user->role === 'super_admin');

        return $next($request);
    }
}