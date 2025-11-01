<?php declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ability Middleware
 * 
 * Handles ability-based authorization with parameters
 * Usage: ability:tenant, ability:admin
 */
class AbilityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $ability
     */
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = Auth::user();

        if (!$user) {
            Log::warning('Unauthenticated request to ability-protected endpoint', [
                'ability' => $ability,
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated',
                'error' => [
                    'id' => 'UNAUTHENTICATED',
                    'code' => 'AUTH_REQUIRED',
                    'details' => 'Authentication required to access this resource'
                ]
            ], 401);
        }

        // Check ability based on parameter
        $result = $this->checkAbility($user, $request, $ability);
        
        // If result is a Response (error), return it
        if ($result instanceof Response) {
            return $result;
        }
        
        // Otherwise, continue to the next middleware/controller
        return $next($request);
    }

    /**
     * Check ability based on type
     */
    private function checkAbility($user, Request $request, string $ability): ?Response
    {
        switch ($ability) {
            case 'tenant':
                return $this->checkTenantAbility($user, $request);
            case 'admin':
                return $this->checkAdminAbility($user, $request);
            default:
                Log::warning('Unknown ability requested', [
                    'ability' => $ability,
                    'user_id' => $user->id,
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid ability',
                    'error' => [
                        'id' => 'INVALID_ABILITY',
                        'code' => 'ABILITY_INVALID',
                        'details' => "Unknown ability: {$ability}"
                    ]
                ], 403);
        }
    }

    /**
     * Check tenant ability
     */
    private function checkTenantAbility($user, Request $request): ?Response
    {
        if (!$user->tenant_id) {
            Log::warning('User without tenant accessing tenant-scoped endpoint', [
                'user_id' => $user->id,
                'email' => $user->email,
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Tenant access required',
                'error' => [
                    'id' => 'TENANT_REQUIRED',
                    'code' => 'TENANT_ACCESS_DENIED',
                    'details' => 'User must belong to a tenant to access this resource'
                ]
            ], 403);
        }

        // Check if user has appropriate role within tenant
        $allowedRoles = ['admin', 'pm', 'member', 'project_manager', 'site_engineer', 'design_lead', 'client_rep', 'qc_inspector'];
        // Normalize role to lowercase for case-insensitive comparison
        $userRole = strtolower($user->role ?? '');
        if (!in_array($userRole, array_map('strtolower', $allowedRoles))) {
            Log::warning('User with invalid role accessing tenant endpoint', [
                'user_id' => $user->id,
                'role' => $user->role,
                'tenant_id' => $user->tenant_id,
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient permissions',
                'error' => [
                    'id' => 'INSUFFICIENT_PERMISSIONS',
                    'code' => 'ROLE_ACCESS_DENIED',
                    'details' => 'User role does not have access to this resource'
                ]
            ], 403);
        }

        // Access granted - return null to continue to controller
        return null;
    }

    /**
     * Check admin ability
     */
    private function checkAdminAbility($user, Request $request): ?Response
    {
        $adminRoles = ['super_admin', 'admin'];
        // Normalize role to lowercase for case-insensitive comparison
        $userRole = strtolower($user->role ?? '');
        
        if (!in_array($userRole, array_map('strtolower', $adminRoles))) {
            Log::warning('Non-admin user accessing admin endpoint', [
                'user_id' => $user->id,
                'role' => $user->role,
                'email' => $user->email,
                'url' => $request->fullUrl(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Admin access required',
                'error' => [
                    'id' => 'ADMIN_REQUIRED',
                    'code' => 'ADMIN_ACCESS_DENIED',
                    'details' => 'Admin role required to access this resource'
                ]
            ], 403);
        }

        // Access granted - return null to continue to controller
        return null;
    }
}