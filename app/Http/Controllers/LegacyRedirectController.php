<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LegacyRedirectController extends Controller
{
    /**
     * Handle legacy dashboard redirects
     */
    public function dashboard(Request $request)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        if ($user->isSuperAdmin()) {
            return redirect('/admin');
        } else {
            return redirect('/app/dashboard');
        }
    }

    /**
     * Handle legacy role-based dashboard redirects
     */
    public function roleDashboard(Request $request, $role)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        // Redirect to new app dashboard with role context
        return redirect("/app/dashboard?role={$role}");
    }

    /**
     * Handle legacy project routes
     */
    public function projects(Request $request)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        if ($user->canAccessApp()) {
            return redirect('/app/projects');
        } else {
            return redirect('/login');
        }
    }

    /**
     * Handle legacy task routes
     */
    public function tasks(Request $request)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        if ($user->canAccessApp()) {
            return redirect('/app/tasks');
        } else {
            return redirect('/login');
        }
    }

    /**
     * Handle legacy user routes
     */
    public function users(Request $request)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        if ($user->isSuperAdmin()) {
            return redirect('/admin/users');
        } else {
            return redirect('/app/team');
        }
    }

    /**
     * Handle legacy tenant routes
     */
    public function tenants(Request $request)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        if ($user->isSuperAdmin()) {
            return redirect('/admin/tenants');
        } else {
            return redirect('/app/dashboard');
        }
    }

    /**
     * Handle legacy document routes
     */
    public function documents(Request $request)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        if ($user->canAccessApp()) {
            return redirect('/app/documents');
        } else {
            return redirect('/login');
        }
    }

    /**
     * Handle legacy template routes
     */
    public function templates(Request $request)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        if ($user->canAccessApp()) {
            return redirect('/app/templates');
        } else {
            return redirect('/login');
        }
    }

    /**
     * Handle legacy settings routes
     */
    public function settings(Request $request)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        if ($user->isSuperAdmin()) {
            return redirect('/admin/settings');
        } else {
            return redirect('/app/settings');
        }
    }

    /**
     * Handle legacy profile routes
     */
    public function profile(Request $request)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        return redirect('/app/profile');
    }

    /**
     * Handle legacy calendar routes
     */
    public function calendar(Request $request)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        if ($user->canAccessApp()) {
            return redirect('/calendar');
        } else {
            return redirect('/login');
        }
    }

    /**
     * Handle legacy team routes
     */
    public function team(Request $request)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        if ($user->canAccessApp()) {
            return redirect('/app/team');
        } else {
            return redirect('/login');
        }
    }

    /**
     * Handle legacy admin routes
     */
    public function admin(Request $request)
    {
        if (!Auth::check()) {
            return redirect('/login');
        }

        $user = Auth::user();
        
        if ($user->isSuperAdmin()) {
            return redirect('/admin');
        } else {
            return redirect('/app/dashboard');
        }
    }

    /**
     * Handle legacy debug routes (only in local environment)
     */
    public function debug(Request $request, $path = '')
    {
        if (!app()->environment('local')) {
            abort(404);
        }

        return redirect("/_debug/{$path}");
    }
}
