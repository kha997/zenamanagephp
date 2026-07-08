<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;

class AppController extends Controller
{
    public function dashboard()
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $totalTasks = Task::query()->where('tenant_id', $tenantId)->count();
        $doneTasks = Task::query()->where('tenant_id', $tenantId)->where('status', 'done')->count();

        $dashboardStats = [
            'activeTasks' => Task::query()
                ->where('tenant_id', $tenantId)
                ->whereIn('status', ['todo', 'pending', 'in_progress'])
                ->count(),
            'completedToday' => Task::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'done')
                ->whereDate('updated_at', now()->toDateString())
                ->count(),
            'teamMembers' => User::query()->where('tenant_id', $tenantId)->count(),
            'projects' => Project::query()->where('tenant_id', $tenantId)->count(),
            'completionRate' => $totalTasks > 0
                ? round($doneTasks / $totalTasks * 100) . '%'
                : '0%',
        ];

        return view('layouts.app-layout', [
            'content' => 'app.dashboard-content',
            'dashboardStats' => $dashboardStats,
        ]);
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
