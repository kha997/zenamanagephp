<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Src\Foundation\Permission;
use Illuminate\Support\Facades\Log;

class CheckPermission
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $permission): mixed
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Authentication required']
                ], 401);
            }

            // Get user role
            $userRole = $user->role ?? 'member';
            
            // Get role permissions
            $rolePermissions = Permission::getRolePermissions($userRole);
            $userPermissions = $rolePermissions['permissions'] ?? [];
            
            // Check if user has required permission
            if (!in_array($permission, $userPermissions)) {
                Log::warning('Permission denied', [
                    'user_id' => $user->id,
                    'user_role' => $userRole,
                    'required_permission' => $permission,
                    'user_permissions' => $userPermissions,
                    'route' => $request->route()->getName(),
                    'method' => $request->method(),
                    'url' => $request->url()
                ]);
                
                return response()->json([
                    'success' => false,
                    'error' => [
                        'message' => 'Insufficient permissions',
                        'required_permission' => $permission,
                        'user_role' => $userRole
                    ]
                ], 403);
            }

            // Add permission context to request
            $request->merge([
                'user_permissions' => $userPermissions,
                'user_role' => $userRole
            ]);

            return $next($request);
        } catch (\Exception $e) {
            Log::error('Permission check error: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'permission' => $permission,
                'route' => $request->route()->getName()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Permission check failed']
            ], 500);
        }
    }
}
