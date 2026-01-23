<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
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

        // Try to authenticate using Laravel's built-in auth
        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            
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

        $failedMessage = __('auth.failed');

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $failedMessage,
            ], 401);
        }

        throw ValidationException::withMessages([
            'email' => [$failedMessage],
        ]);
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
}
