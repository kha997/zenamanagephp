<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TenantScope
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
        // Check if user is authenticated
        if (!Auth::check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }
        
        $user = Auth::user();
        
        // Check if user has tenant context
        if (!$user->hasTenant()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Access denied. Tenant access required.',
                    'error' => 'NO_TENANT_CONTEXT'
                ], 403);
            }
            
            abort(403, 'Access denied. Tenant access required.');
        }
        
        // Set tenant context for the request
        app()->instance('tenant', $user->tenant);
        
        // Add tenant context to request
        $request->merge(['tenant_id' => $user->tenant_id]);
        
        return $next($request);
    }
}
