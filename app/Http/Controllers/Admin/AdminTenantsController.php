<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminTenantsController extends Controller
{
    public function index(Request $request): View
    {
        // Get all tenants
        $tenants = \App\Models\Tenant::all();
        
        return view('admin.tenants.index', compact('tenants'));
    }
}
