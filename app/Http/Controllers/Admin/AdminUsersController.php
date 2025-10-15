<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUsersController extends Controller
{
    public function index(Request $request): View
    {
        // Get all users with their tenants
        $users = \App\Models\User::with('tenant')->get();
        
        // Get all tenants for filter options
        $tenants = \App\Models\Tenant::all();
        
        return view('admin.users.index', compact('users', 'tenants'));
    }
}
