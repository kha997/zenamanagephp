<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Optimized Project Controller
 * 
 * Implements N+1 query prevention with proper eager loading
 * and caching strategies for better performance
 */
class OptimizedProjectController extends Controller
{
    /**
     * Display a listing of projects with optimized queries
     */
    public function index(Request $request): View
    {
        try {
            $user = session('user');
            $tenantId = $user ? $user['tenant_id'] : '01k5kzpfwd618xmwdwq3rej3jz';

            // Get view mode from session or default to 'table'
            $viewMode = session('projects_view_mode', 'table');
            
            // Get filters from request
            $filters = $request->only(['search', 'status', 'priority', 'sort']);
            
            // Build query with filters and eager loading
            $query = Project::with(['owner:id,name,email']) // Eager load owner to prevent N+1
                ->where('tenant_id', $tenantId);
                
            if (!empty($filters['search'])) {
                $query->where('name', 'like', '%' . $filters['search'] . '%');
            }
            
            if (!empty($filters['status'])) {
                $query->where('status', $filters['status']);
            }
            
            if (!empty($filters['priority'])) {
                $query->where('priority', $filters['priority']);
            }
            
            // Apply sorting
            $sortBy = $filters['sort'] ?? 'name';
            $query->orderBy($sortBy, 'asc');

            $projects = $query->paginate(15);
            
            // Get users for filters with optimized query
            $users = Cache::remember("project-users-{$tenantId}", 300, function () use ($tenantId) {
                return User::where('tenant_id', $tenantId)
                    ->select('id', 'name', 'email')
                    ->get();
            });

            // Prepare KPI data with caching and optimized queries
            $kpis = Cache::remember("projects-kpi-{$tenantId}", 60, function () use ($projects, $tenantId) {
                return $this->getKpiData($tenantId, $projects);
            });

            return view('app.projects.index', compact('projects', 'users', 'filters', 'viewMode', 'kpis'));
        } catch (\Exception $e) {
            Log::error('ProjectController index error: ' . $e->getMessage());

            // Fallback: get projects without tenant filter
            $projects = Project::with(['owner:id,name,email'])
                ->paginate(15);
                
            $users = User::select('id', 'name', 'email')->get();
            $filters = [];
            $viewMode = 'table';
            
            $kpis = $this->getFallbackKpis($projects);

            return view('app.projects.index', compact('projects', 'users', 'filters', 'viewMode', 'kpis'));
        }
    }

    /**
     * Get KPI data with optimized queries
     */
    private function getKpiData(string $tenantId, $projects): array
    {
        // Use single query with conditional aggregation
        $projectStats = Project::where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_count,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_count,
                SUM(CASE WHEN status = "archived" THEN 1 ELSE 0 END) as archived_count,
                AVG(CASE WHEN status = "active" THEN progress_pct ELSE NULL END) as avg_progress
            ')
            ->first();

        return [
            [
                'label' => __('dashboard.kpi.total_projects'),
                'value' => $projectStats->total ?? 0,
                'subtitle' => $projects->count() . ' ' . __('projects.displayed'),
                'icon' => 'fas fa-project-diagram',
                'gradient' => 'from-blue-500 to-blue-600',
                'action' => __('projects.view_all')
            ],
            [
                'label' => __('dashboard.kpi.active_projects'),
                'value' => $projectStats->active_count ?? 0,
                'subtitle' => round($projectStats->avg_progress ?? 0, 1) . '% ' . __('projects.avg_progress'),
                'icon' => 'fas fa-play-circle',
                'gradient' => 'from-green-500 to-green-600',
                'action' => __('projects.manage_active')
            ],
            [
                'label' => __('dashboard.kpi.completed_projects'),
                'value' => $projectStats->completed_count ?? 0,
                'subtitle' => $projectStats->completed_count > 0 ? __('projects.success') : __('projects.none_yet'),
                'icon' => 'fas fa-check-circle',
                'gradient' => 'from-purple-500 to-purple-600',
                'action' => __('projects.view_completed')
            ],
            [
                'label' => __('dashboard.kpi.archived_projects'),
                'value' => $projectStats->archived_count ?? 0,
                'subtitle' => $projectStats->archived_count > 0 ? __('projects.in_archive') : __('projects.none_archived'),
                'icon' => 'fas fa-archive',
                'gradient' => 'from-yellow-500 to-yellow-600',
                'action' => __('projects.view_archived')
            ]
        ];
    }

    /**
     * Get fallback KPI data
     */
    private function getFallbackKpis($projects): array
    {
        return [
            [
                'label' => __('app.kpi.total_projects'),
                'value' => $projects->total(),
                'subtitle' => $projects->count() . ' ' . __('app.projects.displayed'),
                'icon' => 'fas fa-project-diagram',
                'gradient' => 'from-blue-500 to-blue-600',
                'action' => __('app.projects.view_all')
            ],
            [
                'label' => __('app.kpi.active_projects'),
                'value' => 0,
                'subtitle' => __('app.projects.none_yet'),
                'icon' => 'fas fa-play-circle',
                'gradient' => 'from-green-500 to-green-600',
                'action' => __('app.projects.manage_active')
            ],
            [
                'label' => __('app.kpi.completed_projects'),
                'value' => 0,
                'subtitle' => __('app.projects.none_yet'),
                'icon' => 'fas fa-check-circle',
                'gradient' => 'from-purple-500 to-purple-600',
                'action' => __('app.projects.view_completed')
            ],
            [
                'label' => __('app.kpi.archived_projects'),
                'value' => 0,
                'subtitle' => __('app.projects.none_archived'),
                'icon' => 'fas fa-archive',
                'gradient' => 'from-yellow-500 to-yellow-600',
                'action' => __('app.projects.view_archived')
            ]
        ];
    }

    public function create()
    {
        return view('app.projects.create');
    }

    /**
     * Store a newly created project
     */
    public function store(Request $request)
    {
        try {
            $projectData = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'priority' => 'nullable|in:low,normal,high,urgent',
                'budget_total' => 'nullable|numeric|min:0',
            ]);

            $user = session('user');
            $projectData['tenant_id'] = $user ? $user['tenant_id'] : '01k5kzpfwd618xmwdwq3rej3jz';
            $projectData['owner_id'] = $user ? $user['id'] : null;
            
            $project = Project::create($projectData);

            // Clear cache after creating project
            Cache::forget("projects-kpi-{$projectData['tenant_id']}");

            return redirect()->route('app.projects.index')->with('success', 'Project created successfully!');
        } catch (\Exception $e) {
            Log::error('Project creation error: ' . $e->getMessage());
            return redirect()->back()->with('error', 'Failed to create project: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Display the specified project with optimized queries
     */
    public function show(string $projectId): JsonResponse
    {
        try {
            $user = session('user');
            $tenantId = $user ? $user['tenant_id'] : '01k5kzpfwd618xmwdwq3rej3jz';

            $project = Project::with([
                'owner:id,name,email',
                'tasks:id,project_id,title,status,assignee_id,end_date',
                'tasks.assignee:id,name,email'
            ])
                ->where('tenant_id', $tenantId)
                ->findOrFail($projectId);

            return response()->json([
                'project' => $project
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Project not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to load project',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified project
     */
    public function update(Request $request, string $projectId): JsonResponse
    {
        try {
            $user = session('user');
            $tenantId = $user ? $user['tenant_id'] : '01k5kzpfwd618xmwdwq3rej3jz';

            $project = Project::where('tenant_id', $tenantId)
                ->findOrFail($projectId);
            
            $project->update($request->validate([
                'name' => 'sometimes|string|max:255',
                'description' => 'sometimes|nullable|string',
                'start_date' => 'sometimes|nullable|date',
                'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
                'priority' => 'sometimes|nullable|in:low,normal,high,urgent',
                'budget_total' => 'sometimes|nullable|numeric|min:0',
                'status' => 'sometimes|in:active,archived,completed,on_hold,cancelled,planning',
            ]));

            $project->load('owner:id,name,email');

            // Clear cache after updating project
            Cache::forget("projects-kpi-{$tenantId}");

            return response()->json([
                'message' => 'Project updated successfully',
                'project' => $project
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Project not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update project',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified project
     */
    public function destroy(string $projectId): JsonResponse
    {
        try {
            $user = session('user');
            $tenantId = $user ? $user['tenant_id'] : '01k5kzpfwd618xmwdwq3rej3jz';

            $project = Project::where('tenant_id', $tenantId)
                ->findOrFail($projectId);

            $project->delete();

            // Clear cache after deleting project
            Cache::forget("projects-kpi-{$tenantId}");

            return response()->json([
                'message' => 'Project deleted successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'error' => 'Project not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to delete project',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
