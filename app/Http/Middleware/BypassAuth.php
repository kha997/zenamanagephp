<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class BypassAuth
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
        try {
            // Always create/set admin user for testing
            $user = User::firstOrCreate(
                ['email' => 'admin@zenamanage.com'],
                [
                    'name' => 'Super Administrator',
                    'password' => bcrypt('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'tenant_id' => null,
                ]
            );
            
            // Set user in Auth facade
            Auth::setUser($user);
            
            // Store in session
            session(['user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'super_admin'
            ]]);
            
            return $next($request);
            
        } catch (\Exception $e) {
            \Log::error('BypassAuth error: ' . $e->getMessage());
            
            // Return a simple response if everything fails
            return response()->view('admin.dashboard', [], 200);
        }
    }
}
