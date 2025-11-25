<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\TaskManagementService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Tasks Web Controller
 * 
 * Pure web controller for task operations.
 * Only returns Blade views - no JSON responses.
 * 
 * This replaces the unified TaskManagementController for Web routes.
 */
class TasksController extends Controller
{
    public function __construct(
        private TaskManagementService $taskService
    ) {}

    /**
     * Display tasks list (Web)
     * 
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $filters = $request->only([
            'project_id',
            'status',
            'priority',
            'assignee_id',
            'search',
            'start_date_from',
            'start_date_to',
            'end_date_from',
            'end_date_to'
        ]);

        $filters = array_filter($filters, function($value) {
            return $value !== '' && $value !== null;
        });

        $perPage = (int) $request->get('per_page', 15);
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $tenantId = (string) auth()->user()->tenant_id;

        $tasks = $this->taskService->getTasks($filters, $perPage, $sortBy, $sortDirection, $tenantId);

        return view('app.tasks.index', compact('tasks', 'filters'));
    }

    /**
     * Show task (Web)
     * 
     * @param string $id Task ID
     * @return View
     */
    public function show(string $id): View
    {
        $tenantId = (string) auth()->user()->tenant_id;
        $task = $this->taskService->getTaskById($id, $tenantId);

        if (!$task) {
            abort(404, 'Task not found');
        }

        return view('app.tasks.show', compact('task'));
    }

    /**
     * Show create task form (Web)
     * 
     * @param Request $request
     * @return View
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        
        $projectId = $request->get('project_id');
        $projects = \App\Models\Project::where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        
        $users = \App\Models\User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return view('app.tasks.create', compact('projects', 'users', 'projectId'));
    }

    /**
     * Show edit task form (Web)
     * 
     * @param string $id Task ID
     * @return View
     */
    public function edit(string $id): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        
        $task = $this->taskService->getTaskById($id, $tenantId);

        if (!$task) {
            abort(404, 'Task not found');
        }

        $projects = \App\Models\Project::where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        
        $users = \App\Models\User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return view('app.tasks.edit', compact('task', 'projects', 'users'));
    }

    /**
     * Show kanban board (Web)
     * 
     * @param Request $request
     * @return View
     */
    public function kanban(Request $request): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        
        $projectId = $request->get('project_id');
        
        return view('app.tasks.kanban', compact('projectId'));
    }
}

