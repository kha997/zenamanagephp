<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TenantAbilityMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated via Sanctum
        if (!Auth::guard('sanctum')->check()) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $user = Auth::guard('sanctum')->user();

        // For admin users, allow access to all tenants
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        // For regular users, ensure they have a tenant scope
        if (!$user->tenant_id) {
            return response()->json(['error' => 'No tenant scope assigned to user'], 403);
        }

        // Set tenant context for the request
        $request->attributes->set('tenant_id', $user->tenant_id);
        
        // Add tenant scope to the request for easy access
        app()->instance('current_tenant_id', $user->tenant_id);

        return $next($request);
    }
}
