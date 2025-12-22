<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

/**
 * Custom Authentication Middleware with Test Bypass
 * 
 * Extends the default authentication middleware to include
 * test authentication bypass for Playwright E2E tests.
 */
class AuthenticateWithTestBypass
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, $guard = null)
    {
        // Check if we're running Playwright tests
        $userAgent = $request->header('User-Agent', '');
        $isPlaywright = str_contains($userAgent, 'Playwright') || 
                       $request->hasHeader('X-Playwright-Test') ||
                       $request->hasHeader('X-Test-Environment');

        // Log for debugging
        \Log::info('AuthenticateWithTestBypass', [
            'url' => $request->url(),
            'user_agent' => $userAgent,
            'has_x_playwright_test' => $request->hasHeader('X-Playwright-Test'),
            'has_x_test_environment' => $request->hasHeader('X-Test-Environment'),
            'is_playwright' => $isPlaywright,
            'is_authenticated' => Auth::check(),
        ]);

        // If this is a Playwright test and user is not authenticated, log in test user
        if ($isPlaywright && !Auth::check()) {
            $testUser = $this->getTestUser();
            if ($testUser) {
                // Use remember token to maintain session across requests
                Auth::login($testUser, true);
                
                // Set tenant context for multi-tenant isolation
                if (method_exists($testUser, 'tenant_id') && $testUser->tenant_id) {
                    session(['tenant_id' => $testUser->tenant_id]);
                }
                
                // Force session to be saved immediately
                session()->save();
                
                \Log::info('AuthenticateWithTestBypass: Logged in test user', [
                    'user_id' => $testUser->id,
                    'user_email' => $testUser->email,
                    'tenant_id' => $testUser->tenant_id,
                    'session_id' => session()->getId(),
                ]);
                
                // Continue with the request
                return $next($request);
            } else {
                \Log::warning('AuthenticateWithTestBypass: No test user found');
            }
        }

        // If user is authenticated, continue
        if (Auth::check()) {
            return $next($request);
        }

        // If not authenticated and not a Playwright test, redirect to login
        if (!$isPlaywright) {
            return redirect()->guest(route('login'));
        }

        // If Playwright test but no test user found, redirect to login
        return redirect()->guest(route('login'));
    }

    /**
     * Get the test user for authentication
     */
    private function getTestUser(): ?User
    {
        // Try to get the PM test user first (has most permissions)
        $testUser = User::where('email', 'uat-pm@test.com')->first();
        
        if (!$testUser) {
            // Fallback to any user from Phase 3 test tenant
            $testUser = User::where('tenant_id', '01K83FPK5XGPXF3V7ANJQRGX5X')
                           ->where('is_active', true)
                           ->first();
        }

        return $testUser;
    }
}
