<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AppApiGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
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
            $filters = $request->only([
                'status',
                'priority',
                'client_id',
                'search',
                'sort_by',
                'sort_direction',
                'page'
            ]);
            
            // Fetch data efficiently
            $responses = $this->fetchProjectData($filters);
            
            // Extract responses from services
            $projects = $responses['projects'];
            $dashboardData = $responses['dashboard'];
            $clients = $responses['clients'] ?? collect();
            $meta = $responses['meta'] ?? [];

                   return view('app.projects.index', [
                       'projects' => $projects,
                       'clients' => $clients,
                       'meta' => $meta,
                       'kpis' => $this->buildKpis($dashboardData),
                       'viewMode' => $viewMode,
                       'filters' => $filters,
                       'user' => auth()->user(),
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('app.dashboard')],
                    ['label' => 'Projects', 'url' => null]
                ],
                'actions' => '<a href="' . route('app.projects.create') . '" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-plus mr-2"></i>
                    New Project
                </a>',
                'sortOptions' => [
                    ['value' => 'name', 'label' => 'Project Name'],
                    ['value' => 'status', 'label' => 'Status'],
                    ['value' => 'priority', 'label' => 'Priority'],
                    ['value' => 'start_date', 'label' => 'Start Date'],
                    ['value' => 'end_date', 'label' => 'End Date'],
                    ['value' => 'budget', 'label' => 'Budget'],
                    ['value' => 'progress', 'label' => 'Progress'],
                    ['value' => 'updated_at', 'label' => 'Last Updated']
                ],
                'bulkActions' => [
                    ['value' => 'delete', 'label' => 'Delete Selected', 'icon' => 'fas fa-trash', 'handler' => 'bulkDelete'],
                    ['value' => 'archive', 'label' => 'Archive Selected', 'icon' => 'fas fa-archive', 'handler' => 'bulkArchive'],
                    ['value' => 'export', 'label' => 'Export Selected', 'icon' => 'fas fa-download', 'handler' => 'bulkExport']
                ],
                'tableData' => $projects->map(function($project) {
                    return [
                        'id' => $project->id,
                        'name' => $project->name,
                        'status' => $project->status,
                        'priority' => $project->priority,
                        'start_date' => $project->start_date,
                        'end_date' => $project->end_date,
                        'budget' => $project->budget,
                        'progress' => $project->progress,
                        'updated_at' => $project->updated_at
                    ];
                }),
                'columns' => [
                    ['key' => 'name', 'label' => 'Project Name', 'sortable' => true],
                    ['key' => 'status', 'label' => 'Status', 'sortable' => true],
                    ['key' => 'priority', 'label' => 'Priority', 'sortable' => true],
                    ['key' => 'start_date', 'label' => 'Start Date', 'sortable' => true],
                    ['key' => 'end_date', 'label' => 'End Date', 'sortable' => true],
                    ['key' => 'budget', 'label' => 'Budget', 'sortable' => true],
                    ['key' => 'progress', 'label' => 'Progress', 'sortable' => true],
                    ['key' => 'updated_at', 'label' => 'Last Updated', 'sortable' => true]
                ]
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
                       'error' => 'An error occurred while loading projects',
                       'user' => auth()->user(),
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('app.dashboard')],
                    ['label' => 'Projects', 'url' => null]
                ],
                'actions' => '<a href="' . route('app.projects.create') . '" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-plus mr-2"></i>
                    New Project
                </a>',
                'sortOptions' => [
                    ['value' => 'name', 'label' => 'Project Name'],
                    ['value' => 'status', 'label' => 'Status'],
                    ['value' => 'priority', 'label' => 'Priority'],
                    ['value' => 'start_date', 'label' => 'Start Date'],
                    ['value' => 'end_date', 'label' => 'End Date'],
                    ['value' => 'budget', 'label' => 'Budget'],
                    ['value' => 'progress', 'label' => 'Progress'],
                    ['value' => 'updated_at', 'label' => 'Last Updated']
                ],
                'bulkActions' => [
                    ['value' => 'delete', 'label' => 'Delete Selected', 'icon' => 'fas fa-trash', 'handler' => 'bulkDelete'],
                    ['value' => 'archive', 'label' => 'Archive Selected', 'icon' => 'fas fa-archive', 'handler' => 'bulkArchive'],
                    ['value' => 'export', 'label' => 'Export Selected', 'icon' => 'fas fa-download', 'handler' => 'bulkExport']
                ],
                'tableData' => collect(),
                'columns' => [
                    ['key' => 'name', 'label' => 'Project Name', 'sortable' => true],
                    ['key' => 'status', 'label' => 'Status', 'sortable' => true],
                    ['key' => 'priority', 'label' => 'Priority', 'sortable' => true],
                    ['key' => 'start_date', 'label' => 'Start Date', 'sortable' => true],
                    ['key' => 'end_date', 'label' => 'End Date', 'sortable' => true],
                    ['key' => 'budget', 'label' => 'Budget', 'sortable' => true],
                    ['key' => 'progress', 'label' => 'Progress', 'sortable' => true],
                    ['key' => 'updated_at', 'label' => 'Last Updated', 'sortable' => true]
                ]
            ]);
        }
    }

    /**
     * Fetch project data efficiently
     */
    private function fetchProjectData(array $filters): array
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch projects with filters
            $projectsResponse = $this->apiGateway->fetchProjects($filters);
            $meta = data_get($projectsResponse, 'data.meta', []);
            
            // Fetch clients for filter dropdown
            $clientsResponse = $this->apiGateway->fetchClients();
            
            // Fetch dashboard data
            $dashboardResponse = $this->apiGateway->fetchDashboardData();
            
            return [
                'projects' => collect($projectsResponse['data']['data'] ?? [])->map(fn ($item) => (object) $item),
                'clients' => collect($clientsResponse['data']['data'] ?? [])->map(fn ($item) => (object) $item),
                'dashboard' => $dashboardResponse['data'] ?? [],
                'meta' => $meta
            ];
        } catch (\Exception $e) {
            Log::error('ProjectController fetchProjectData error', [
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);
            
            return [
                'projects' => collect(),
                'clients' => collect(),
                'dashboard' => []
            ];
        }
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
                ->route('app.projects.index')
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
                'project' => $this->mapProject($response['data']['data'] ?? null),
                'tasks' => collect(data_get($tasksResponse, 'data.data', []))->map(fn ($task) => (object) $task),
                'documents' => collect(data_get($documentsResponse, 'data.data', []))->map(fn ($doc) => (object) $doc)
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
                'project' => $this->mapProject($projectResponse['data']['data'] ?? null),
                'clients' => collect(data_get($clientsResponse, 'data.data', []))->map(fn ($c) => (object) $c),
                'users' => collect(data_get($teamResponse, 'data.data', []))->map(fn ($u) => (object) $u)
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
                ->route('app.projects.index')
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
     * Remove the specified project from storage.
     */
    public function destroy(string $projectId): RedirectResponse
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Delete project via API
            $response = $this->apiGateway->deleteProject($projectId);

            if (!$response['success']) {
                return redirect()
                    ->back()
                    ->withErrors(['error' => $response['error']['message'] ?? 'Failed to delete project']);
            }

            return redirect()
                ->route('app.projects.index')
                ->with('success', 'Project deleted successfully!');

        } catch (\Exception $e) {
            Log::error('ProjectController destroy error', [
                'project_id' => $projectId,
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return redirect()
                ->back()
                ->withErrors(['error' => 'An error occurred while deleting the project']);
        }
    }

    /**
     * Map API project data to object for views
     */
    private function mapProject(?array $projectData): ?object
    {
        if (!$projectData) {
            return null;
        }

        // Convert array to object and add fake relationships
        $project = (object) $projectData;
        
        // Add fake client relationship if client_id exists
        if (isset($projectData['client_id']) && $projectData['client_id']) {
            $project->client = (object) [
                'id' => $projectData['client_id'],
                'name' => $projectData['client_name'] ?? 'Unknown Client'
            ];
        } else {
            $project->client = null;
        }
        
        // Add fake project manager relationship if project_manager_id exists
        if (isset($projectData['project_manager_id']) && $projectData['project_manager_id']) {
            $project->projectManager = (object) [
                'id' => $projectData['project_manager_id'],
                'name' => $projectData['project_manager_name'] ?? 'Unknown Manager'
            ];
        } else {
            $project->projectManager = null;
        }
        
        // Convert date strings to Carbon objects for proper formatting
        if (isset($projectData['start_date']) && $projectData['start_date']) {
            $project->start_date = \Carbon\Carbon::parse($projectData['start_date']);
        }
        
        if (isset($projectData['end_date']) && $projectData['end_date']) {
            $project->end_date = \Carbon\Carbon::parse($projectData['end_date']);
        }
        
        return $project;
    }

    /**
     * Set preferred projects view mode (table/card/kanban)
     */
    public function setViewMode(Request $request): RedirectResponse
    {
        $viewMode = $request->input('view_mode', 'table');
        
        if (in_array($viewMode, ['table', 'card', 'kanban'], true)) {
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

    /**
     * Handle bulk actions on projects
     */
    public function bulkAction(Request $request): JsonResponse
    {
        $request->validate([
            'action' => 'required|in:delete,archive,export',
            'project_ids' => 'required|array',
            'project_ids.*' => 'required|string|ulid'
        ]);

        try {
            $this->apiGateway->setAuthContext();
            $action = $request->input('action');
            $projectIds = $request->input('project_ids');

            [$response, $message] = $this->dispatchBulkAction($action, $projectIds);

            return $response['success']
                ? response()->json(['message' => $message])
                : response()->json(['message' => 'Failed to perform bulk action'], 422);

        } catch (\Exception $e) {
            Log::error('ProjectController bulkAction error', [
                'error' => $e->getMessage(),
                'action' => $request->input('action'),
                'project_ids' => $request->input('project_ids'),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return response()->json(['message' => 'An error occurred while performing the bulk action'], 500);
        }
    }

    /**
     * Dispatch bulk action to appropriate API method
     */
    private function dispatchBulkAction(string $action, array $projectIds): array
    {
        switch ($action) {
            case 'delete':
                $response = $this->apiGateway->bulkDeleteProjects($projectIds);
                $message = 'Projects deleted successfully';
                break;
            case 'archive':
                $response = $this->apiGateway->bulkArchiveProjects($projectIds);
                $message = 'Projects archived successfully';
                break;
            case 'export':
                $response = $this->apiGateway->bulkExportProjects($projectIds);
                $message = 'Projects exported successfully';
                break;
            default:
                throw new \InvalidArgumentException("Unknown bulk action: {$action}");
        }

        return [$response, $message];
    }
}
