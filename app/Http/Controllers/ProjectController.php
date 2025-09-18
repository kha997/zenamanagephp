<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Project Controller - handles project CRUD operations
 */
class ProjectController extends Controller
{
    public function __construct()
    {
        // Temporarily disable auth middleware for testing
        // $this->middleware('auth');
        
        // Register policies
        // $this->authorizeResource(Project::class, 'project');
    }

    /**
     * Display projects list page
     */
    public function index(): View
    {
        return view('projects.index');
    }

    /**
     * Display project detail page
     */
    public function show($project): View
    {
        // Temporarily disable authorization for testing
        // $this->authorize('view', $project);
        
        // Route to specific project based on ID
        if ($project == 1) {
            return view('projects.design-project');
        } elseif ($project == 2) {
            return view('projects.construction-project');
        } else {
            // Default to design project for other IDs
            return view('projects.design-project');
        }
    }

    /**
     * Show the form for creating a new project
     */
    public function create(): View
    {
        // Temporarily disable authorization for testing
        // $this->authorize('create', Project::class);
        
        // Get users (mock data for testing without database)
        $users = collect([
            (object)['id' => 1, 'name' => 'John Smith', 'email' => 'john@example.com'],
            (object)['id' => 2, 'name' => 'Sarah Wilson', 'email' => 'sarah@example.com'],
            (object)['id' => 3, 'name' => 'Mike Johnson', 'email' => 'mike@example.com'],
            (object)['id' => 4, 'name' => 'Alex Lee', 'email' => 'alex@example.com'],
            (object)['id' => 5, 'name' => 'Emma Davis', 'email' => 'emma@example.com'],
        ]);
        return view('projects.create', compact('users'));
    }

    /**
     * Store a newly created project
     */
    public function store(Request $request)
    {
        // Temporarily disable authorization for testing
        // $this->authorize('create', Project::class);
        
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:projects,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_id' => 'nullable|exists:users,id',
            'pm_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget_total' => 'nullable|numeric|min:0',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return redirect()->back()
                ->withErrors($validator)
                ->withInput();
        }

        try {
            $project = Project::create([
                'tenant_id' => '01HZQZQZQZQZQZQZQZQZQZQZQZ', // Temporary tenant ID
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'client_id' => $request->client_id,
                'pm_id' => $request->pm_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'budget_total' => $request->budget_total ?? 0,
                'tags' => $request->tags ?? [],
                'status' => 'draft',
                'progress' => 0,
            ]);

            return redirect()->route('projects.show', $project)
                ->with('success', 'Project created successfully');

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to create project: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Show the form for editing the specified project
     */
    public function edit($project): View
    {
        // Temporarily disable authorization for testing
        // $this->authorize('update', $project);
        
        // Mock project data for testing without database
        $projectData = (object)[
            'id' => $project,
            'code' => 'PROJ-' . str_pad($project, 3, '0', STR_PAD_LEFT),
            'name' => 'Sample Project ' . $project,
            'description' => 'This is a sample project description for testing purposes.',
            'client_id' => 1,
            'pm_id' => 2,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'budget_total' => 100000,
            'status' => 'active',
            'progress' => 25,
            'tags' => ['urgent', 'high-priority'],
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-15 10:30:00'
        ];
        
        // Mock users data
        $users = collect([
            (object)['id' => 1, 'name' => 'John Smith', 'email' => 'john@example.com'],
            (object)['id' => 2, 'name' => 'Sarah Wilson', 'email' => 'sarah@example.com'],
            (object)['id' => 3, 'name' => 'Mike Johnson', 'email' => 'mike@example.com'],
            (object)['id' => 4, 'name' => 'Alex Lee', 'email' => 'alex@example.com'],
            (object)['id' => 5, 'name' => 'Emma Davis', 'email' => 'emma@example.com'],
        ]);
        
        return view('projects.edit', compact('projectData', 'users'));
    }

    /**
     * Show project documents page
     */
    public function documents($project): View
    {
        // Mock project data for testing without database
        $projectData = (object)[
            'id' => $project,
            'code' => 'PROJ-' . str_pad($project, 3, '0', STR_PAD_LEFT),
            'name' => 'Sample Project ' . $project,
            'description' => 'This is a sample project description for testing purposes.',
            'client_id' => 1,
            'pm_id' => 2,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'budget_total' => 100000,
            'status' => 'active',
            'progress' => 25,
            'tags' => ['urgent', 'high-priority'],
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-15 10:30:00'
        ];
        
        return view('projects.documents', compact('projectData'));
    }

    /**
     * Show project history page
     */
    public function history($project): View
    {
        // Mock project data for testing without database
        $projectData = (object)[
            'id' => $project,
            'code' => 'PROJ-' . str_pad($project, 3, '0', STR_PAD_LEFT),
            'name' => 'Sample Project ' . $project,
            'description' => 'This is a sample project description for testing purposes.',
            'client_id' => 1,
            'pm_id' => 2,
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
            'budget_total' => 100000,
            'status' => 'active',
            'progress' => 25,
            'tags' => ['urgent', 'high-priority'],
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-15 10:30:00'
        ];
        
        return view('projects.history', compact('projectData'));
    }

    /**
     * API: Get projects list with filters
     */
    public function apiIndex(Request $request): JsonResponse
    {
        $query = Project::with(['client', 'pm']);
            // ->where('tenant_id', Auth::user()->tenant_id);

        // Search
        if ($request->filled('q')) {
            $searchTerm = $request->get('q');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('code', 'like', "%{$searchTerm}%")
                  ->orWhere('description', 'like', "%{$searchTerm}%");
            });
        }

        // Filters
        if ($request->filled('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->filled('pm_id')) {
            $query->where('pm_id', $request->get('pm_id'));
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->get('client_id'));
        }

        if ($request->filled('from')) {
            $query->where('start_date', '>=', $request->get('from'));
        }

        if ($request->filled('to')) {
            $query->where('end_date', '<=', $request->get('to'));
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $projects = $query->paginate($perPage);

        return response()->json([
            'data' => $projects->items(),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ]
        ]);
    }

    /**
     * API: Store a newly created project
     */
    public function apiStore(Request $request): JsonResponse
    {
        // Check if user can create projects
        $this->authorize('create', Project::class);
        
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|max:50|unique:projects,code',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'client_id' => 'nullable|exists:users,id',
            'pm_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget_total' => 'nullable|numeric|min:0',
            'tags' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $project = Project::create([
                'tenant_id' => '01HZQZQZQZQZQZQZQZQZQZQZQZ', // Temporary tenant ID
                'code' => $request->code,
                'name' => $request->name,
                'description' => $request->description,
                'client_id' => $request->client_id,
                'pm_id' => $request->pm_id,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'budget_total' => $request->budget_total ?? 0,
                'tags' => $request->tags ?? [],
                'status' => 'draft',
                'progress' => 0,
            ]);

            return response()->json([
                'success' => true,
                'data' => $project->load(['client', 'pm']),
                'message' => 'Project created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create project: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Get project details
     */
    public function apiShow(Project $project): JsonResponse
    {
        $project->load(['client', 'pm', 'tasks.assignee', 'tasks.subtasks']);
        
        return response()->json([
            'success' => true,
            'data' => $project
        ]);
    }

    /**
     * API: Update the specified project
     */
    public function apiUpdate(Request $request, Project $project): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|max:50|unique:projects,code,' . $project->id,
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'client_id' => 'nullable|exists:users,id',
            'pm_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget_total' => 'nullable|numeric|min:0',
            'tags' => 'nullable|array',
            'status' => 'sometimes|in:draft,active,on_hold,completed,archived',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $project->update($request->only([
                'code', 'name', 'description', 'client_id', 'pm_id',
                'start_date', 'end_date', 'budget_total', 'tags', 'status'
            ]));

            return response()->json([
                'success' => true,
                'data' => $project->load(['client', 'pm']),
                'message' => 'Project updated successfully'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Soft delete the specified project
     */
    public function apiDestroy(Project $project): JsonResponse
    {
        try {
            $project->delete();

            return response()->json([
                'success' => true,
                'message' => 'Project deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete project: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Restore soft deleted project
     */
    public function apiRestore(Project $project): JsonResponse
    {
        try {
            $project->restore();
            
            return response()->json([
                'success' => true,
                'message' => 'Project restored successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore project: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Archive project
     */
    public function apiArchive(Project $project): JsonResponse
    {
        try {
            $project->update(['status' => 'archived']);

            return response()->json([
                'success' => true,
                'message' => 'Project archived successfully'
            ]);

        } catch (\Exception $e) {
                return response()->json([
                'success' => false,
                'message' => 'Failed to archive project: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Duplicate project
     */
    public function apiDuplicate(Project $project): JsonResponse
    {
        try {
            DB::beginTransaction();

            $newProject = $project->replicate();
            $newProject->code = $project->code . '-COPY-' . time();
            $newProject->name = $project->name . ' (Copy)';
            $newProject->status = 'draft';
            $newProject->progress = 0;
            $newProject->save();

            // Duplicate tasks
            foreach ($project->tasks as $task) {
                $newTask = $task->replicate();
                $newTask->project_id = $newProject->id;
                $newTask->status = 'todo';
                $newTask->progress_percent = 0;
                $newTask->save();
            }

            DB::commit();
            
            return response()->json([
                'success' => true,
                'data' => $newProject->load(['client', 'pm']),
                'message' => 'Project duplicated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate project: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API: Get project summary/KPIs
     */
    public function apiSummary(Project $project): JsonResponse
    {
        $tasks = $project->tasks;
        
        $summary = [
            'total_tasks' => $tasks->count(),
            'completed_tasks' => $tasks->where('status', 'done')->count(),
            'overdue_tasks' => $tasks->where('end_date', '<', now())->where('status', '!=', 'done')->count(),
            'progress' => $project->progress,
            'budget_used' => $project->budget_total * ($project->progress / 100),
            'budget_remaining' => $project->budget_total * (1 - $project->progress / 100),
            'team_members' => $tasks->pluck('assignee_id')->unique()->count(),
            'milestones' => 0, // Placeholder
            'risks' => 0, // Placeholder
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * API: Get trash (soft deleted projects)
     */
    public function apiTrash(Request $request): JsonResponse
    {
        $query = Project::onlyTrashed()
            // ->where('tenant_id', Auth::user()->tenant_id)
            ->with(['client', 'pm']);

        if ($request->filled('q')) {
            $searchTerm = $request->get('q');
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', "%{$searchTerm}%")
                  ->orWhere('code', 'like', "%{$searchTerm}%");
            });
        }

        $projects = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => $projects->items(),
            'meta' => [
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
                'per_page' => $projects->perPage(),
                'total' => $projects->total(),
            ]
        ]);
    }
}