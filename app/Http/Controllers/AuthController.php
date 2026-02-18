<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Models\User;

class AuthController extends Controller
{
    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|string|min:6',
        ]);

        $credentials = $request->only('email', 'password');

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            return back()
                ->withErrors(['email' => 'Too many login attempts. Please try again in a minute.'])
                ->setStatusCode(429);
        }

        // Try to authenticate using Laravel's built-in auth
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            RateLimiter::clear($throttleKey);

            $user = Auth::user();
            
            // Redirect based on user role
            if ($user->isSuperAdmin()) {
                return redirect()->intended('/admin');
            } else {
                return redirect()->intended('/app/dashboard');
            }
        }

        // If authentication fails, try demo users
        $demoUsers = [
            'superadmin@zena.com' => [
                'name' => 'Super Admin',
                'password' => 'password123',
                'role' => 'super_admin'
            ],
            'pm@zena.com' => [
                'name' => 'Project Manager', 
                'password' => 'password123',
                'role' => 'project_manager'
            ],
            'user@zena.com' => [
                'name' => 'Regular User',
                'password' => 'password123', 
                'role' => 'user'
            ],
        ];

        if (isset($demoUsers[$credentials['email']]) && 
            $demoUsers[$credentials['email']]['password'] === $credentials['password']) {
            
            // Create or get user from database
            $user = User::firstOrCreate(
                ['email' => $credentials['email']],
                [
                    'name' => $demoUsers[$credentials['email']]['name'],
                    'password' => Hash::make($credentials['password']),
                    'is_active' => true,
                ]
            );

            // Assign role if not exists
            if (!$user->hasRole($demoUsers[$credentials['email']]['role'])) {
                $role = \App\Models\Role::where('name', $demoUsers[$credentials['email']]['role'])->first();
                if ($role) {
                    $user->roles()->attach($role->id);
                }
            }

            // Login the user
            Auth::login($user);
            $request->session()->regenerate();

            // Redirect based on role
            if ($user->isSuperAdmin()) {
                return redirect()->intended('/admin');
            } else {
                return redirect()->intended('/app/dashboard');
            }
        }

        RateLimiter::hit($throttleKey, 60);

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        Auth::logout();
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/login')->with('success', 'You have been logged out successfully.');
    }

    protected function throttleKey(Request $request): string
    {
        $email = (string) ($request->input('email') ?? $request->input('username'));
        return Str::lower($email) . '|' . $request->ip();
    }
}
