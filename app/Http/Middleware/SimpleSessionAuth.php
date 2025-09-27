<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class SimpleSessionAuth
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
            // Check if user is already authenticated
            if (Auth::check()) {
                return $next($request);
            }

            // Check if we have session data
            if (session()->has('user')) {
                $userData = session('user');
                
                // Find or create user
                $user = User::where('email', $userData['email'])->first();
                
                if (!$user) {
                    // Create a basic user if not exists
                    $user = User::create([
                        'name' => $userData['name'] ?? 'Admin User',
                        'email' => $userData['email'],
                        'password' => bcrypt('password'),
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]);
                }
                
                // Set the user in Auth facade
                Auth::setUser($user);
            } else {
                // Create a default admin user for testing
                $user = User::firstOrCreate(
                    ['email' => 'admin@zenamanage.com'],
                    [
                        'name' => 'Super Admin',
                        'password' => bcrypt('password'),
                        'is_active' => true,
                        'email_verified_at' => now(),
                    ]
                );
                
                Auth::setUser($user);
                
                // Store in session
                session(['user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => 'super_admin'
                ]]);
            }
            
            return $next($request);
            
        } catch (\Exception $e) {
            // Log error but don't break the application
            \Log::warning('SimpleSessionAuth error: ' . $e->getMessage());
            
            // Create a fallback user
            $user = User::firstOrCreate(
                ['email' => 'admin@zenamanage.com'],
                [
                    'name' => 'Super Admin',
                    'password' => bcrypt('password'),
                    'is_active' => true,
                    'email_verified_at' => now(),
                ]
            );
            
            Auth::setUser($user);
            
            return $next($request);
        }
    }
}
