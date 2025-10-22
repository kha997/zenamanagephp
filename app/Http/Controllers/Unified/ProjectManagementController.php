<?php declare(strict_types=1);

namespace App\Http\Controllers\Unified;

use App\Http\Controllers\Controller;
use App\Services\ProjectManagementService;
use App\Http\Requests\Unified\ProjectManagementRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

/**
 * ProjectManagementController
 * 
 * Unified controller for all project management operations
 * Replaces multiple project controllers (Api/ProjectsController, Web/ProjectController, etc.)
 */
class ProjectManagementController extends Controller
{
    protected ProjectManagementService $projectService;

    public function __construct(ProjectManagementService $projectService)
    {
        $this->projectService = $projectService;
    }

    /**
     * Display projects list (Web)
     */
    public function index(ProjectManagementRequest $request): View
    {
        $filters = $request->only(['search', 'status', 'priority', 'owner_id', 'start_date_from', 'start_date_to', 'end_date_from', 'end_date_to']);
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $perPage = $request->get('per_page', 15);

        $projects = $this->projectService->getProjects(
            $filters,
            $perPage,
            $sortBy,
            $sortDirection
        );

        $stats = $this->projectService->getProjectStats();

        return view('app.projects.index', compact('projects', 'stats', 'filters'));
    }

    /**
     * Get projects (API)
     */
    public function getProjects(ProjectManagementRequest $request): JsonResponse
    {
        $filters = $request->only(['search', 'status', 'priority', 'owner_id', 'start_date_from', 'start_date_to', 'end_date_from', 'end_date_to']);
        $sortBy = $request->get('sort_by', 'updated_at');
        $sortDirection = $request->get('sort_direction', 'desc');
        $perPage = (int) $request->get('per_page', 15);

        $projects = $this->projectService->getProjects(
            $filters,
            $perPage,
            $sortBy,
            $sortDirection,
            (string) (Auth::user()?->tenant_id ?? '')
        );

        return $this->projectService->successResponse($projects);
    }

    /**
     * Get project by ID (API)
     */
    public function getProject(string $id): JsonResponse
    {
        $project = $this->projectService->getProjectById($id, (string) (Auth::user()?->tenant_id ?? ''));
        
        if (!$project) {
            return $this->projectService->errorResponse('Project not found', 404);
        }

        return $this->projectService->successResponse($project);
    }

    /**
     * Create project (API)
     */
    public function createProject(ProjectManagementRequest $request): JsonResponse
    {
        try {
            $project = $this->projectService->createProject($request->all(), (string) (Auth::user()?->tenant_id ?? ''));
            
            return $this->projectService->successResponse(
                $project,
                'Project created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Update project (API)
     */
    public function updateProject(ProjectManagementRequest $request, string $id): JsonResponse
    {
        $project = $this->projectService->updateProject($id, $request->all(), (string) (Auth::user()?->tenant_id ?? ''));
        
        return $this->projectService->successResponse(
            $project,
            'Project updated successfully'
        );
    }

    /**
     * Restore project (API)
     */
    public function restoreProject(string $id): JsonResponse
    {
        $project = $this->projectService->restoreProject($id, (string) (Auth::user()?->tenant_id ?? ''));
        
        return $this->projectService->successResponse(
            $project,
            'Project restored successfully'
        );
    }

    /**
     * Delete project (API)
     */
    public function deleteProject(string $id): JsonResponse
    {
        try {
            $deleted = $this->projectService->deleteProject($id);
            
            if (!$deleted) {
                return $this->projectService->errorResponse('Failed to delete project', 500);
            }
            
            return $this->projectService->successResponse(
                null,
                'Project deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Bulk delete projects (API)
     */
    public function bulkDeleteProjects(ProjectManagementRequest $request): JsonResponse
    {
        try {
            $count = $this->projectService->bulkDeleteProjects($request->input('ids'));
            
            return $this->projectService->successResponse(
                ['deleted_count' => $count],
                "Successfully deleted {$count} projects"
            );
        } catch (\Exception $e) {
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Update project status (API)
     */
    public function updateProjectStatus(ProjectManagementRequest $request, string $id): JsonResponse
    {
        try {
            $project = $this->projectService->updateProjectStatus($id, $request->input('status'), (string) (Auth::user()?->tenant_id ?? ''));
            
            return $this->projectService->successResponse(
                $project,
                'Project status updated successfully'
            );
        } catch (\Exception $e) {
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Update project progress (API)
     */
    public function updateProjectProgress(ProjectManagementRequest $request, int $id): JsonResponse
    {
        try {
            $project = $this->projectService->updateProjectProgress($id, $request->input('progress'));
            
            return $this->projectService->successResponse(
                $project,
                'Project progress updated successfully'
            );
        } catch (\Exception $e) {
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Assign project to user (API)
     */
    public function assignProject(ProjectManagementRequest $request, int $id): JsonResponse
    {
        try {
            $project = $this->projectService->assignProject($id, $request->input('user_id'));
            
            return $this->projectService->successResponse(
                $project,
                'Project assigned successfully'
            );
        } catch (\Exception $e) {
            return $this->projectService->errorResponse(
                $e->getMessage(),
                $e->getCode() ?: 500
            );
        }
    }

    /**
     * Get project statistics (API)
     */
    public function getProjectStats(): JsonResponse
    {
        $stats = $this->projectService->getProjectStats((string) (string) (Auth::user()?->tenant_id ?? ''));
        
        return $this->projectService->successResponse($stats);
    }

    /**
     * Search projects (API)
     */
    public function searchProjects(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2',
            'limit' => 'sometimes|integer|min:1|max:50'
        ]);

        $projects = $this->projectService->searchProjects(
            $request->input('search'),
            $request->input('limit', 10)
        );

        return $this->projectService->successResponse($projects);
    }

    /**
     * Get recent projects (API)
     */
    public function getRecentProjects(Request $request): JsonResponse
    {
        $request->validate([
            'limit' => 'sometimes|integer|min:1|max:20'
        ]);

        $projects = $this->projectService->getRecentProjects(
            $request->input('limit', 5)
        );

        return $this->projectService->successResponse($projects);
    }

    /**
     * Get project dashboard data (API)
     */
    public function getProjectDashboardData(): JsonResponse
    {
        $data = $this->projectService->getProjectDashboardData();
        
        return $this->projectService->successResponse($data);
    }

    /**
     * Show project details (Web)
     */
    public function show(string $id): View
    {
        $project = $this->projectService->getProjectById($id);
        
        if (!$project) {
            abort(404, 'Project not found');
        }

        return view('app.projects.show', compact('project'));
    }

    /**
     * Show create project form (Web)
     */
    public function create(): View
    {
        return view('app.projects.create');
    }

    /**
     * Show edit project form (Web)
     */
    public function edit(string $id): View
    {
        $project = $this->projectService->getProjectById($id);
        
        if (!$project) {
            abort(404, 'Project not found');
        }

        return view('app.projects.edit', compact('project'));
    }

    /**
     * Bulk archive projects
     */
    public function bulkArchiveProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|string|ulid'
            ]);

            $projectIds = $request->input('ids');
            $result = $this->projectService->bulkArchiveProjects($projectIds);

            return response()->json([
                'success' => true,
                'message' => 'Projects archived successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to archive projects: ' . $e->getMessage()
            ], 422);
        }
    }

    /**
     * Bulk export projects
     */
    public function bulkExportProjects(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'ids' => 'required|array',
                'ids.*' => 'required|string|ulid'
            ]);

            $projectIds = $request->input('ids');
            $result = $this->projectService->bulkExportProjects($projectIds);

            return response()->json([
                'success' => true,
                'message' => 'Projects exported successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export projects: ' . $e->getMessage()
            ], 422);
        }
    }
}
