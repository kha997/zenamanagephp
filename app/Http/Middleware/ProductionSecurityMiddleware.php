<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Production Security Middleware
 * 
 * Chặn các routes không an toàn trong production environment
 */
class ProductionSecurityMiddleware
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
        // Chỉ áp dụng trong production
        if (app()->environment('production')) {
            $path = $request->path();
            
            // Chặn SimpleUserController routes
            if (str_contains($path, 'simple/users') || str_contains($path, 'users-v2')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'This endpoint is not available in production environment',
                    'code' => 'PRODUCTION_SECURITY_BLOCK'
                ], 404);
            }
            
            // Chặn debug/test routes
            if (str_contains($path, 'debug') || str_contains($path, 'test') || str_contains($path, 'zena-test')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Debug endpoints are not available in production',
                    'code' => 'PRODUCTION_SECURITY_BLOCK'
                ], 404);
            }
        }
        
        return $next($request);
    }
}