<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InvitationAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if user is authenticated via session
        $user = session('user');
        
        if (!$user) {
            // Redirect to login page if not authenticated
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authentication required',
                    'redirect' => '/login'
                ], 401);
            }
            
            return redirect('/login')->with('error', 'Please login to access this page');
        }
        
        // Add user to request for easy access
        $request->merge(['authenticated_user' => $user]);
        
        return $next($request);
    }
}