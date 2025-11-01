<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class SessionAuthMiddleware
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
        // Check if user is logged in via session but not in Auth facade
        if (session()->has('user') && !Auth::check()) {
            $userData = session('user');
            
            // Try to find user in database first
            $user = User::where('email', $userData['email'])->first();
            
            if (!$user) {
                // Create user if not exists (for demo users)
                $user = User::create([
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => bcrypt('password123'), // Default password for demo users
                    'is_active' => true,
                ]);
                
                // Assign role if not exists
                if (!$user->hasRole($userData['role'])) {
                    $role = \App\Models\Role::where('name', $userData['role'])->first();
                    if ($role) {
                        $user->roles()->attach($role->id);
                    }
                }
            }
            
            // Set the user in Auth facade
            Auth::setUser($user);
        }

        return $next($request);
    }
}
