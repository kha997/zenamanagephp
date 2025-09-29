<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminOnly
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
        // Check if user is authenticated via Sanctum
        if (!Auth::guard('sanctum')->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }
        
        $user = Auth::guard('sanctum')->user();
        
        // Check if user has admin ability or super admin role
        $hasAdminAccess = false;
        
        // Check token ability
        if ($request->user()->currentAccessToken() && $request->user()->currentAccessToken()->can('admin')) {
            $hasAdminAccess = true;
        }
        
        // Check user role
        if ($user->isSuperAdmin()) {
            $hasAdminAccess = true;
        }
        
        if (!$hasAdminAccess) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => [
                        'code' => 'FORBIDDEN',
                        'message' => 'Admin access required'
                    ]
                ], 403);
            }
            
            abort(403, 'Access denied. Admin access required.');
        }
        
        return $next($request);
    }
}
