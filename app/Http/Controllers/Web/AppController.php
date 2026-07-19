<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Models\EventRecord;
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

        return view('app.dashboard', [
            'dashboardStats' => $dashboardStats,
            'recentProjects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderByDesc('updated_at')
                ->limit(5)
                ->get(['id', 'tenant_id', 'name', 'code', 'status', 'progress']),
            'recentEvents' => EventRecord::query()
                ->where('tenant_id', $tenantId)
                ->with('actor:id,name')
                ->orderByDesc('occurred_at')
                ->limit(8)
                ->get(),
        ]);
    }

    public function projects()
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $projects = Project::query()
            ->where('tenant_id', $tenantId)
            ->with('latestBaseline')
            ->orderByDesc('updated_at')
            ->get(['id', 'tenant_id', 'name', 'code', 'status', 'progress', 'start_date', 'end_date', 'budget_total']);

        $delays = $projects->mapWithKeys(fn (Project $p) => [
            (string) $p->id => \App\Services\ProjectDelayStatus::evaluate($p, $p->latestBaseline),
        ]);

        return view('app.projects', [
            'projects' => $projects,
            'delays' => $delays,
        ]);
    }

    public function tasks()
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        return view('app.tasks', [
            'tasks' => Task::query()
                ->where('tenant_id', $tenantId)
                ->with('project:id,tenant_id,name,code')
                ->orderByDesc('updated_at')
                ->limit(200)
                ->get(['id', 'tenant_id', 'project_id', 'name', 'title', 'status', 'priority', 'progress_percent', 'start_date', 'end_date', 'blocked_at']),
        ]);
    }

    public function calendar()
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $tasks = Task::query()
            ->where('tenant_id', $tenantId)
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', now()->toDateString())
            ->whereDate('end_date', '<=', now()->addDays(30)->toDateString())
            ->with('project:id,tenant_id,name,code')
            ->orderBy('end_date')
            ->get(['id', 'tenant_id', 'project_id', 'name', 'title', 'status', 'end_date']);

        return view('app.calendar', [
            'tasksByDate' => $tasks->groupBy(fn (Task $task) => substr((string) $task->end_date, 0, 10)),
        ]);
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
