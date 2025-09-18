<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        // Simple validation without Laravel validation
        $email = $request->input('email');
        $password = $request->input('password');
        
        if (!$email || !$password) {
            if ($request->isJson() || $request->header('Content-Type') === 'application/json') {
                return response()->json([
                    'success' => false,
                    'message' => 'Vui lòng nhập đầy đủ email và mật khẩu'
                ], 400);
            }
            return back()->withErrors([
                'email' => 'Vui lòng nhập đầy đủ email và mật khẩu',
            ]);
        }
        
        // For demo purposes, we'll create a simple authentication
        $demoUsers = [
            'superadmin@zena.com' => ['name' => 'Super Admin', 'role' => 'super_admin'],
            'pm@zena.com' => ['name' => 'Project Manager', 'role' => 'project_manager'],
            'designer@zena.com' => ['name' => 'Designer', 'role' => 'designer'],
            'site@zena.com' => ['name' => 'Site Engineer', 'role' => 'site_engineer'],
            'qc@zena.com' => ['name' => 'QC Engineer', 'role' => 'qc_engineer'],
            'procurement@zena.com' => ['name' => 'Procurement', 'role' => 'procurement'],
            'finance@zena.com' => ['name' => 'Finance', 'role' => 'finance'],
            'client@zena.com' => ['name' => 'Client', 'role' => 'client'],
        ];
        
        if ($password === 'zena1234' && isset($demoUsers[$email])) {
            $userData = $demoUsers[$email];
            
            // Create a simple user object for session
            $user = new \stdClass();
            $user->id = rand(1000, 9999);
            $user->name = $userData['name'];
            $user->email = $email;
            $user->role = $userData['role'];
            
            // Store user data in session
            session(['user' => $user]);
            
            if ($request->isJson() || $request->header('Content-Type') === 'application/json') {
                return response()->json([
                    'success' => true,
                    'message' => 'Đăng nhập thành công!',
                    'redirect' => '/dashboard',
                    'user' => $user
                ]);
            }
            
            return redirect()->intended('/dashboard');
        }

        if ($request->isJson() || $request->header('Content-Type') === 'application/json') {
            return response()->json([
                'success' => false,
                'message' => 'Email hoặc mật khẩu không đúng'
            ], 401);
        }
        
        return back()->withErrors([
            'email' => 'Email hoặc mật khẩu không đúng',
        ]);
    }

    public function logout(Request $request)
    {
        // Clear session user data
        $request->session()->forget('user');
        
        // Also clear Laravel auth if exists
        if (Auth::check()) {
            Auth::logout();
        }
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        return redirect('/login');
    }
}
