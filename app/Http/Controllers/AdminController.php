<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function dashboard()
    {
        return view('layouts.admin-layout');
    }
    
    public function users()
    {
        return view('layouts.admin-layout');
    }
    
    public function tenants()
    {
        return view('layouts.admin-layout');
    }
    
    public function security()
    {
        return view('layouts.admin-layout');
    }
    
    public function alerts()
    {
        return view('layouts.admin-layout');
    }
    
    public function activities()
    {
        return view('layouts.admin-layout');
    }
    
    public function analytics()
    {
        return view('layouts.admin-layout');
    }
    
    public function projects()
    {
        // System-wide Project Oversight with Tenant Filter Required
        return view('layouts.admin-layout');
    }
    
    public function tasks()
    {
        return view('layouts.admin-layout');
    }
    
    public function settings()
    {
        return view('layouts.admin-layout');
    }
    
    public function maintenance()
    {
        return view('layouts.admin-layout');
    }
    
    public function sidebarBuilder()
    {
        return view('layouts.admin-layout');
    }
}