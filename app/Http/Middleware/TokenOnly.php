<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class TokenOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // Only use this middleware if SECURITY_AUTH_BYPASS is enabled
        if (!config('app.security_auth_bypass', false)) {
            return $next($request);
        }
        
        $token = $request->bearerToken();
        
        if (!$token) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Bearer token required'
                ]
            ], 401);
        }
        
        $pat = PersonalAccessToken::findToken($token);
        
        if (!$pat) {
            return response()->json([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Invalid token'
                ]
            ], 401);
        }
        
        // Check ability 'admin' or user role
        if (!$pat->can('admin') && $pat->tokenable->role !== 'super_admin') {
            return response()->json([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'Admin access required'
                ]
            ], 403);
        }
        
        // Set user into request
        $request->setUserResolver(function() use ($pat) {
            return $pat->tokenable;
        });
        
        return $next($request);
    }
}
