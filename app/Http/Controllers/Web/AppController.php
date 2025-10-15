<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
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
        $projectController = new \App\Http\Controllers\Web\ProjectController();
        return $projectController->index();
    }
    
    public function tasks()
    {
        return view('app.tasks.index');
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