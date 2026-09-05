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

            // /admin requires a real super_admin role that no seeded account
            // currently has (RoleSeeder creates "System Admin", a different,
            // permission-scoped role - see app/Traits/HasRoles.php::isSuperAdmin()).
            // Sending users there produces a silent 403. Everyone lands on the
            // operator dashboard, which already covers workload/projects/
            // contracts/etc.; /admin remains reachable only via its own
            // rbac:admin gate for whoever actually qualifies.
            return redirect()->intended('/app/today');
        }

        // Demo-user fallback: local/testing convenience only. GAP-049 Gate-3
        // Round-1 Owner correction — a real super_admin role now exists in
        // production (created by production:bootstrap), which turned this
        // pre-existing hardcoded demo-credential shortcut into an exploitable
        // privileged production authentication path. Reuses the same
        // hardcoded app()->environment(['local', 'testing']) guard already
        // used for the same purpose in routes/web.php (Simple Authentication
        // Routes) — not a new configurable toggle.
        if (app()->environment(['local', 'testing'])) {
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
