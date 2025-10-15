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
 * Web Task Controller - UI chỉ render, không có business logic
 * 
 * @package App\Http\Controllers\Web
 */
class TaskController extends Controller
{
    protected AppApiGateway $apiGateway;

    public function __construct(AppApiGateway $apiGateway)
    {
        $this->apiGateway = $apiGateway;
    }

    /**
     * Display a listing of tasks.
     */
    public function index(Request $request): View
    {
        try {
            // Set auth context
            $this->apiGateway->setAuthContext();
            
            // Fetch all data in parallel using Promise-like approach
            $responses = $this->fetchDataInParallel($request->all());
            
            // Extract responses from services
            $tasks = $responses['tasks'];
            $projects = $responses['projects'];
            $teamMembers = $responses['team']['data']['members'];
            $dashboardData = $responses['dashboard'];

            return view('app.tasks.index-new', [
                'tasks' => $tasks,
                'projects' => $projects,
                'users' => $teamMembers,
                'kpis' => $this->buildKpis($dashboardData),
                'filters' => $request->all()
            ]);

        } catch (\Exception $e) {
            Log::error('TaskController index error', [
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return view('app.tasks.index', [
                'tasks' => collect(),
                'projects' => collect(),
                'users' => collect(),
                'kpis' => [],
                'error' => 'An error occurred while loading tasks'
            ]);
        }
    }

    /**
     * Fetch all required data in parallel
     */
    private function fetchDataInParallel(array $filters): array
    {
        // Use services directly instead of internal API calls
        $taskService = app(\App\Services\TaskService::class);
        $projectService = app(\App\Services\ProjectService::class);
        $dashboardService = app(\App\Services\DashboardService::class);
        
        $user = Auth::user();
        $userId = $user->id;
        $tenantId = $user->tenant_id;
        
        return [
            'tasks' => $taskService->getTasksList($filters, $userId, $tenantId),
            'projects' => $projectService->getProjectsList([], $userId, $tenantId),
            'team' => ['data' => ['members' => collect()]], // Mock team data
            'dashboard' => $dashboardService->getDashboardData($userId, $tenantId)
        ];
    }

    /**
     * Show the form for creating a new task.
     */
    public function create(): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch projects and users for form
            $projectsResponse = $this->apiGateway->fetchProjects();
            $teamResponse = $this->apiGateway->fetchTeamMembers();

            return view('app.tasks.create', [
                'projects' => $projectsResponse['data']['projects'] ?? collect(),
                'users' => $teamResponse['data']['members'] ?? collect()
            ]);

        } catch (\Exception $e) {
            Log::error('TaskController create error', [
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return view('app.tasks.create', [
                'projects' => collect(),
                'users' => collect(),
                'error' => 'An error occurred while loading form data'
            ]);
        }
    }

    /**
     * Store a newly created task.
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Validate input
            $validated = $request->validate([
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'project_id' => 'required|string|exists:projects,id',
                'assignee_id' => 'nullable|string|exists:users,id',
                'priority' => 'nullable|in:low,medium,high',
                'status' => 'nullable|in:pending,in_progress,completed,cancelled',
                'due_date' => 'nullable|date',
                'estimated_hours' => 'nullable|numeric|min:0'
            ]);

            // Create task via API
            $response = $this->apiGateway->createTask($validated);

            if (!$response['success']) {
                return redirect()
                    ->back()
                    ->withErrors(['error' => $response['error']['message'] ?? 'Failed to create task'])
                    ->withInput();
            }

            return redirect()
                ->route('tasks.index')
                ->with('success', 'Task created successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            Log::error('TaskController store error', [
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return redirect()
                ->back()
                ->withErrors(['error' => 'An error occurred while creating the task'])
                ->withInput();
        }
    }

    /**
     * Display the specified task.
     */
    public function show(string $taskId): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch task details
            $response = $this->apiGateway->fetchTask($taskId);

            if (!$response['success']) {
                abort(404, 'Task not found');
            }

            return view('app.tasks.show', [
                'task' => $response['data']['task']
            ]);

        } catch (\Exception $e) {
            Log::error('TaskController show error', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            abort(404, 'Task not found');
        }
    }

    /**
     * Show the form for editing the specified task.
     */
    public function edit(string $taskId): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch task details
            $taskResponse = $this->apiGateway->fetchTask($taskId);
            
            if (!$taskResponse['success']) {
                abort(404, 'Task not found');
            }

            // Fetch related data
            $projectsResponse = $this->apiGateway->fetchProjects();
            $teamResponse = $this->apiGateway->fetchTeamMembers();

            return view('app.tasks.edit', [
                'task' => $taskResponse['data']['task'],
                'projects' => $projectsResponse['data']['projects'] ?? collect(),
                'users' => $teamResponse['data']['members'] ?? collect()
            ]);

        } catch (\Exception $e) {
            Log::error('TaskController edit error', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            abort(404, 'Task not found');
        }
    }

    /**
     * Update the specified task.
     */
    public function update(Request $request, string $taskId): RedirectResponse
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Validate input
            $validated = $request->validate([
                'title' => 'sometimes|required|string|max:255',
                'description' => 'nullable|string',
                'project_id' => 'sometimes|required|string|exists:projects,id',
                'assignee_id' => 'nullable|string|exists:users,id',
                'priority' => 'nullable|in:low,medium,high',
                'status' => 'nullable|in:pending,in_progress,completed,cancelled',
                'due_date' => 'nullable|date',
                'estimated_hours' => 'nullable|numeric|min:0',
                'progress_percent' => 'nullable|numeric|min:0|max:100'
            ]);

            // Update task via API
            $response = $this->apiGateway->updateTask($taskId, $validated);

            if (!$response['success']) {
                return redirect()
                    ->back()
                    ->withErrors(['error' => $response['error']['message'] ?? 'Failed to update task'])
                    ->withInput();
            }

            return redirect()
                ->route('tasks.index')
                ->with('success', 'Task updated successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            Log::error('TaskController update error', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return redirect()
                ->back()
                ->withErrors(['error' => 'An error occurred while updating the task'])
                ->withInput();
        }
    }

    /**
     * Display task documents.
     */
    public function documents(string $taskId): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch task details
            $taskResponse = $this->apiGateway->fetchTask($taskId);
            
            if (!$taskResponse['success']) {
                abort(404, 'Task not found');
            }

            // Fetch task documents
            $documentsResponse = $this->apiGateway->fetchDocuments(['task_id' => $taskId]);

            return view('app.tasks.documents', [
                'task' => $taskResponse['data']['task'],
                'documents' => $documentsResponse['data']['documents'] ?? collect()
            ]);

        } catch (\Exception $e) {
            Log::error('TaskController documents error', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            abort(404, 'Task not found');
        }
    }

    /**
     * Display task history.
     */
    public function history(string $taskId): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch task details
            $taskResponse = $this->apiGateway->fetchTask($taskId);
            
            if (!$taskResponse['success']) {
                abort(404, 'Task not found');
            }

            // TODO: Implement task history API endpoint
            $history = [];

            return view('app.tasks.history', [
                'task' => $taskResponse['data']['task'],
                'history' => $history
            ]);

        } catch (\Exception $e) {
            Log::error('TaskController history error', [
                'task_id' => $taskId,
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            abort(404, 'Task not found');
        }
    }

    /**
     * Build KPI data from dashboard stats
     */
    private function buildKpis(array $stats): array
    {
        return [
            [
                'label' => 'Total Tasks',
                'value' => $stats['total_tasks'] ?? 0,
                'subtitle' => 'All tasks',
                'icon' => 'fas fa-tasks',
                'gradient' => 'from-blue-500 to-blue-600',
                'action' => 'View All Tasks'
            ],
            [
                'label' => 'Pending Tasks',
                'value' => $stats['pending_tasks'] ?? 0,
                'subtitle' => 'Awaiting start',
                'icon' => 'fas fa-clock',
                'gradient' => 'from-yellow-500 to-yellow-600',
                'action' => 'View Pending'
            ],
            [
                'label' => 'In Progress',
                'value' => $stats['in_progress_tasks'] ?? 0,
                'subtitle' => 'Currently active',
                'icon' => 'fas fa-play-circle',
                'gradient' => 'from-green-500 to-green-600',
                'action' => 'View Active'
            ],
            [
                'label' => 'Completed',
                'value' => $stats['completed_tasks'] ?? 0,
                'subtitle' => 'Finished tasks',
                'icon' => 'fas fa-check-circle',
                'gradient' => 'from-purple-500 to-purple-600',
                'action' => 'View Completed'
            ]
        ];
    }
}
