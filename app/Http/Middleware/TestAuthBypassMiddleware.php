<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

/**
 * Test Authentication Bypass Middleware
 * 
 * Bypasses authentication for Playwright E2E tests by automatically
 * logging in a test user when running in test environment.
 */
class TestAuthBypassMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Check if we're running Playwright tests
        $userAgent = $request->header('User-Agent', '');
        $isPlaywright = str_contains($userAgent, 'Playwright') || 
                       $request->hasHeader('X-Playwright-Test') ||
                       $request->hasHeader('X-Test-Environment');

        // Log for debugging
        \Log::info('TestAuthBypassMiddleware', [
            'url' => $request->url(),
            'user_agent' => $userAgent,
            'has_x_playwright_test' => $request->hasHeader('X-Playwright-Test'),
            'has_x_test_environment' => $request->hasHeader('X-Test-Environment'),
            'is_playwright' => $isPlaywright,
            'is_authenticated' => Auth::check(),
        ]);

        if (!$isPlaywright) {
            return $next($request);
        }

        // If user is not authenticated, log in test user
        if (!Auth::check()) {
            $testUser = $this->getTestUser();
            if ($testUser) {
                Auth::login($testUser);
                
                // Set tenant context for multi-tenant isolation
                if (method_exists($testUser, 'tenant_id') && $testUser->tenant_id) {
                    session(['tenant_id' => $testUser->tenant_id]);
                }
                
                \Log::info('TestAuthBypassMiddleware: Logged in test user', [
                    'user_id' => $testUser->id,
                    'user_email' => $testUser->email,
                    'tenant_id' => $testUser->tenant_id,
                ]);
            } else {
                \Log::warning('TestAuthBypassMiddleware: No test user found');
            }
        }

        return $next($request);
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
