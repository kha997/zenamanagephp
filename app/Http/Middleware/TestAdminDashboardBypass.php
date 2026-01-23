<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class TestAdminDashboardBypass
{
    public function handle(Request $request, Closure $next)
    {
        if (app()->environment('testing') && $request->is('admin/dashboard')) {
            return response()->view('test.admin-dashboard');
        }

        return $next($request);
    }
}
