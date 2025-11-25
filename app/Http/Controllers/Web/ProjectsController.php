<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Unified\ProjectManagementRequest;
use App\Services\ProjectManagementService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * Projects Web Controller
 * 
 * Pure web controller for project operations.
 * Only returns Blade views - no JSON responses.
 * 
 * This replaces the unified ProjectManagementController for Web routes.
 */
class ProjectsController extends Controller
{
    public function __construct(
        private ProjectManagementService $projectService
    ) {}

    /**
     * Display projects list (Web)
     * 
     * @param ProjectManagementRequest $request
     * @return View
     */
    public function index(ProjectManagementRequest $request): View
    {
        $filters = $request->only([
            'search', 
            'status', 
            'priority', 
            'owner_id', 
            'start_date_from', 
            'start_date_to', 
            'end_date_from', 
            'end_date_to'
        ]);
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $perPage = (int) $request->get('per_page', 15);

        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        
        $projects = $this->projectService->getProjects(
            $filters,
            $perPage,
            $sortBy,
            $sortDirection,
            $tenantId
        );

        $stats = $this->projectService->getProjectStats($tenantId);
        
        return view('app.projects.index', compact('projects', 'stats', 'filters'));
    }

    /**
     * Show project (Web)
     * 
     * @param string $id Project ID
     * @return View
     */
    public function show(string $id): View
    {
        $tenantId = (string) (Auth::user()?->tenant_id ?? '');
        $project = $this->projectService->getProjectById($id, $tenantId);
        
        if (!$project) {
            abort(404, 'Project not found');
        }

        $project->load([
            'client:id,name',
            'owner:id,name,email',
            'tasks' => function($q) {
                $q->select('id', 'project_id', 'name', 'title', 'status', 'priority', 'created_at')
                  ->orderBy('created_at', 'desc')
                  ->limit(10);
            },
            'documents' => function($q) {
                $q->select('id', 'project_id', 'name', 'file_type', 'created_at')
                  ->orderBy('created_at', 'desc')
                  ->limit(10);
            },
            'users:id,name,email'
        ]);

        $teamMembersCount = $project->users()->count();
        $tasksCount = $project->tasks()->count();
        $completedTasksCount = $project->tasks()->where('status', 'completed')->count();

        return view('app.projects.show', compact('project', 'teamMembersCount', 'tasksCount', 'completedTasksCount'));
    }

    /**
     * Show create project form (Web)
     * 
     * @return View
     */
    public function create(): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        
        $clients = \App\Models\Client::where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        
        $users = \App\Models\User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
        
        return view('app.projects.create', compact('clients', 'users'));
    }

    /**
     * Show edit project form (Web)
     * 
     * @param string $id Project ID
     * @return View
     */
    public function edit(string $id): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;
        
        $project = $this->projectService->getProjectById($id, $tenantId);
        
        if (!$project) {
            abort(404, 'Project not found');
        }

        $clients = \App\Models\Client::where('tenant_id', $tenantId)
            ->select('id', 'name')
            ->orderBy('name')
            ->get();
        
        $users = \App\Models\User::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return view('app.projects.edit', compact('project', 'clients', 'users'));
    }
}

