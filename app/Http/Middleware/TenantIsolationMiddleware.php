<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Tenant;

/**
 * Tenant Isolation Middleware
 * 
 * Ensures users can only access data within their tenant
 */
class TenantIsolationMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Authentication required'
            ], 401);
        }

        // Get user's tenant ID
        $userTenantId = $user->tenant_id;
        
        if (!$userTenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not associated with any tenant'
            ], 403);
        }

        // Add tenant context to request
        $request->merge(['tenant_context' => $userTenantId]);
        
        // Check for cross-tenant access attempts
        $this->validateTenantAccess($request, $userTenantId);

        return $next($request);
    }

    /**
     * Validate that user is not trying to access other tenant's data
     */
    private function validateTenantAccess(Request $request, string $userTenantId): void
    {
        $route = $request->route();
        
        if (!$route) {
            return;
        }

        $parameters = $route->parameters();
        
        // Check if trying to access another user
        if (isset($parameters['user']) || isset($parameters['id'])) {
            $targetId = $parameters['user'] ?? $parameters['id'];
            
            // If it's a user ID, check tenant
            if ($this->isUserId($targetId)) {
                $targetUser = User::find($targetId);
                
                if ($targetUser && $targetUser->tenant_id !== $userTenantId) {
                    abort(403, 'Access denied: Cross-tenant access not allowed');
                }
            }
        }

        // Check query parameters for tenant-specific resources
        $this->validateQueryParameters($request, $userTenantId);
    }

    /**
     * Check if the ID looks like a user ID (ULID format)
     */
    private function isUserId(string $id): bool
    {
        // ULID format: 26 characters, starts with timestamp
        return strlen($id) === 26 && ctype_alnum($id);
    }

    /**
     * Validate query parameters for tenant isolation
     */
    private function validateQueryParameters(Request $request, string $userTenantId): void
    {
        // Check for explicit tenant_id in query
        $queryTenantId = $request->query('tenant_id');
        
        if ($queryTenantId && $queryTenantId !== $userTenantId) {
            abort(403, 'Access denied: Cannot access other tenant data');
        }

        // Check for user_id in query (for admin operations)
        $queryUserId = $request->query('user_id');
        
        if ($queryUserId) {
            $targetUser = User::find($queryUserId);
            
            if ($targetUser && $targetUser->tenant_id !== $userTenantId) {
                abort(403, 'Access denied: Cannot access other tenant users');
            }
        }
    }
}