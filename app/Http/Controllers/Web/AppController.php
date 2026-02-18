<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class AppController extends Controller
{
    public function dashboard()
    {
        return view('layouts.app-layout', ['content' => 'app.dashboard-content']);
    }
    
    public function projects()
    {
        $tenantId = Auth::user()?->tenant_id;
        $projects = [];

        if ($tenantId !== null) {
            $projects = Project::where('tenant_id', $tenantId)->get();
        }

        return view('layouts.app-layout', compact('projects'));
    }
    
    public function tasks()
    {
        return view('tasks.index');
    }
    
    public function documents()
    {
        return view('layouts.app-layout');
    }
    
    public function team()
    {
        return view('layouts.app-layout');
    }
    
    public function teamUsers()
    {
        return view('layouts.app-layout');
    }
    
    public function templates()
    {
        return view('layouts.app-layout');
    }
    
    public function settings()
    {
        return view('layouts.app-layout');
    }
    
    public function profile()
    {
        return view('layouts.app-layout');
    }
}
