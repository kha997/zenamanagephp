<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Models\Tenant;

class DemoUserMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next)
    {
        // Only apply in local/testing environment
        if (!app()->environment('local', 'testing')) {
            return $next($request);
        }

        // Create demo user if not authenticated
        if (!Auth::check()) {
            $demoUser = $this->createDemoUser();
            Auth::login($demoUser);
        }

        return $next($request);
    }

    /**
     * Create a demo user for testing
     */
    private function createDemoUser()
    {
        // Create demo tenant first
        $demoTenant = Tenant::firstOrCreate(
            ['slug' => 'demo-tenant'],
            [
                'name' => 'Demo Tenant',
                'slug' => 'demo-tenant',
                'domain' => 'demo.local',
                'status' => 'active',
                'is_active' => true,
            ]
        );

        // Create demo user
        $demoUser = User::firstOrCreate(
            ['email' => 'demo@example.com'],
            [
                'name' => 'Demo User',
                'email' => 'demo@example.com',
                'password' => bcrypt('password'),
                'tenant_id' => $demoTenant->id,
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );

        // Ensure tenant relationship is loaded
        $demoUser->load('tenant');

        return $demoUser;
    }
}
