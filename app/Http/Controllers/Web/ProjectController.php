<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AppApiGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Web Project Controller - UI chỉ render, không có business logic
 * 
 * @package App\Http\Controllers\Web
 */
class ProjectController extends Controller
{
    protected AppApiGateway $apiGateway;

    public function __construct(AppApiGateway $apiGateway)
    {
        $this->apiGateway = $apiGateway;
    }

    /**
     * Display a listing of projects
     */
    public function index(Request $request): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Get view mode from session or default to 'table'
            $viewMode = session('projects_view_mode', 'table');
            
            // Get filters from request
            $filters = $request->only(['status', 'search', 'sort_by', 'sort_direction']);
            
            // Fetch data efficiently
            $responses = $this->fetchProjectData($filters);
            
            // Extract responses from services
            $projects = $responses['projects'];
            $dashboardData = $responses['dashboard'];

            return view('app.projects.index-new', [
                'projects' => $projects,
                'meta' => [],
                'kpis' => $this->buildKpis($dashboardData),
                'viewMode' => $viewMode,
                'filters' => $filters
            ]);

        } catch (\Exception $e) {
            Log::error('ProjectController index error', [
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return view('app.projects.index', [
                'projects' => collect(),
                'kpis' => [],
                'viewMode' => 'table',
                'filters' => [],
                'error' => 'An error occurred while loading projects'
            ]);
        }
    }

    /**
     * Fetch project data efficiently
     */
    private function fetchProjectData(array $filters): array
    {
        // Use services directly instead of internal API calls
        $projectService = app(\App\Services\ProjectService::class);
        $dashboardService = app(\App\Services\DashboardService::class);
        
        $user = Auth::user();
        $userId = $user->id;
        $tenantId = $user->tenant_id;
        
        return [
            'projects' => $projectService->getProjectsList($filters, $userId, $tenantId),
            'dashboard' => $dashboardService->getDashboardData($userId, $tenantId)
        ];
    }

    /**
     * Show the form for creating a new project.
     */
    public function create(): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch clients for form
            $clientsResponse = $this->apiGateway->fetchClients();
            $teamResponse = $this->apiGateway->fetchTeamMembers();

            return view('app.projects.create', [
                'clients' => $clientsResponse['data']['clients'] ?? collect(),
                'users' => $teamResponse['data']['members'] ?? collect()
            ]);

        } catch (\Exception $e) {
            Log::error('ProjectController create error', [
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return view('app.projects.create', [
                'clients' => collect(),
                'users' => collect(),
                'error' => 'An error occurred while loading form data'
            ]);
        }
    }

    /**
     * Store a newly created project.
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'client_id' => 'nullable|string|exists:clients,id',
                'project_manager_id' => 'nullable|string|exists:users,id',
                'status' => 'nullable|in:planning,active,on_hold,completed,cancelled',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'budget_total' => 'nullable|numeric|min:0',
                'code' => 'required|string|max:50|unique:projects,code'
            ]);

            // Create project via API
            $response = $this->apiGateway->createProject($validated);

            if (!$response['success']) {
                return redirect()
                    ->back()
                    ->withErrors(['error' => $response['error']['message'] ?? 'Failed to create project'])
                    ->withInput();
            }

            return redirect()
                ->route('projects.index')
                ->with('success', 'Project created successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            Log::error('ProjectController store error', [
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return redirect()
                ->back()
                ->withErrors(['error' => 'An error occurred while creating the project'])
                ->withInput();
        }
    }

    /**
     * Display the specified project.
     */
    public function show(string $projectId): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch project details
            $response = $this->apiGateway->fetchProject($projectId);

            if (!$response['success']) {
                abort(404, 'Project not found');
            }

            // Fetch project tasks
            $tasksResponse = $this->apiGateway->fetchTasks(['project_id' => $projectId]);

            // Fetch project documents
            $documentsResponse = $this->apiGateway->fetchDocuments(['project_id' => $projectId]);

            return view('app.projects.show', [
                'project' => $response['data']['project'],
                'tasks' => $tasksResponse['data']['tasks'] ?? collect(),
                'documents' => $documentsResponse['data']['documents'] ?? collect()
            ]);

        } catch (\Exception $e) {
            Log::error('ProjectController show error', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            abort(404, 'Project not found');
        }
    }

    /**
     * Show the form for editing the specified project.
     */
    public function edit(string $projectId): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch project details
            $projectResponse = $this->apiGateway->fetchProject($projectId);
            
            if (!$projectResponse['success']) {
                abort(404, 'Project not found');
            }

            // Fetch related data
            $clientsResponse = $this->apiGateway->fetchClients();
            $teamResponse = $this->apiGateway->fetchTeamMembers();

            return view('app.projects.edit', [
                'project' => $projectResponse['data']['project'],
                'clients' => $clientsResponse['data']['clients'] ?? collect(),
                'users' => $teamResponse['data']['members'] ?? collect()
            ]);

        } catch (\Exception $e) {
            Log::error('ProjectController edit error', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            abort(404, 'Project not found');
        }
    }

    /**
     * Update the specified project.
     */
    public function update(Request $request, string $projectId): RedirectResponse
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Validate input
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'client_id' => 'nullable|string|exists:clients,id',
                'project_manager_id' => 'nullable|string|exists:users,id',
                'status' => 'nullable|in:planning,active,on_hold,completed,cancelled',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after:start_date',
                'budget_total' => 'nullable|numeric|min:0',
                'code' => 'sometimes|required|string|max:50'
            ]);

            // Update project via API
            $response = $this->apiGateway->updateProject($projectId, $validated);

            if (!$response['success']) {
                return redirect()
                    ->back()
                    ->withErrors(['error' => $response['error']['message'] ?? 'Failed to update project'])
                    ->withInput();
            }

            return redirect()
                ->route('projects.index')
                ->with('success', 'Project updated successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            Log::error('ProjectController update error', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return redirect()
                ->back()
                ->withErrors(['error' => 'An error occurred while updating the project'])
                ->withInput();
        }
    }

    /**
     * Set view mode preference
     */
    public function setViewMode(Request $request): RedirectResponse
    {
        $viewMode = $request->input('view_mode', 'table');
        
        if (in_array($viewMode, ['table', 'card', 'kanban'])) {
            session(['projects_view_mode' => $viewMode]);
        }

        return redirect()->back();
    }

    /**
     * Build KPI data from dashboard stats
     */
    private function buildKpis(array $stats): array
    {
        return [
            [
                'label' => 'Total Projects',
                'value' => $stats['total_projects'] ?? 0,
                'subtitle' => 'All projects',
                'icon' => 'fas fa-project-diagram',
                'gradient' => 'from-blue-500 to-blue-600',
                'action' => 'View All Projects'
            ],
            [
                'label' => 'Active Projects',
                'value' => $stats['active_projects'] ?? 0,
                'subtitle' => 'Currently running',
                'icon' => 'fas fa-play-circle',
                'gradient' => 'from-green-500 to-green-600',
                'action' => 'View Active'
            ],
            [
                'label' => 'Completed',
                'value' => $stats['completed_projects'] ?? 0,
                'subtitle' => 'Finished projects',
                'icon' => 'fas fa-check-circle',
                'gradient' => 'from-purple-500 to-purple-600',
                'action' => 'View Completed'
            ],
            [
                'label' => 'On Hold',
                'value' => $stats['on_hold_projects'] ?? 0,
                'subtitle' => 'Paused projects',
                'icon' => 'fas fa-pause-circle',
                'gradient' => 'from-yellow-500 to-yellow-600',
                'action' => 'View On Hold'
            ]
        ];
    }

    /**
     * Show project documents
     */
    public function documents(Project $project): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch project documents via API
            $documents = $this->apiGateway->fetchProjectDocuments($project->id);
            
            return view('app.projects.documents', compact('project', 'documents'));
        } catch (\Exception $e) {
            Log::error('Error fetching project documents', [
                'project_id' => $project->id,
                'error' => $e->getMessage()
            ]);
            
            return view('app.projects.documents', compact('project'))
                ->with('documents', [])
                ->with('error', 'Unable to load documents');
        }
    }

    /**
     * Show project history
     */
    public function history(Project $project): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch project history via API
            $history = $this->apiGateway->fetchProjectHistory($project->id);
            
            return view('app.projects.history', compact('project', 'history'));
        } catch (\Exception $e) {
            Log::error('Error fetching project history', [
                'project_id' => $project->id,
                'error' => $e->getMessage()
            ]);
            
            return view('app.projects.history', compact('project'))
                ->with('history', [])
                ->with('error', 'Unable to load history');
        }
    }
}
