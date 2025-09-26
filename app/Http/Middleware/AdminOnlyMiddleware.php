<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminOnlyMiddleware
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
                return response()->json(['error' => 'Unauthenticated'], 401);
            }
            return redirect('/login')->with('error', 'Please login to access admin area.');
        }

        $user = Auth::guard('sanctum')->user();

        // Check if user has admin role
        if (!$user->isSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Access denied. Admin privileges required.'], 403);
            }
            return redirect('/app/dashboard')->with('error', 'Access denied. Admin privileges required.');
        }

        return $next($request);
    }
}
