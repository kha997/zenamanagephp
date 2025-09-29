<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConditionalAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @param  string|null  $guard
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        // Check if auth bypass is enabled via environment
        if (config('app.auth_bypass_enabled', false) || env('AUTH_BYPASS_ENABLED', false)) {
            // Mock authenticated user for testing
            $this->mockAuthenticatedUser($request);
            return $next($request);
        }

        // Normal authentication flow
        if (!Auth::guard($guard)->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->route('login');
        }

        return $next($request);
    }

    /**
     * Mock authenticated user for testing
     */
    private function mockAuthenticatedUser(Request $request)
    {
        // Create a mock user for testing
        $mockUser = new \App\Models\User([
            'id' => 1,
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'role' => 'super_admin'
        ]);

        // Set the user in the request
        $request->setUserResolver(function () use ($mockUser) {
            return $mockUser;
        });

        // Set user in Auth facade
        Auth::setUser($mockUser);
    }
}
