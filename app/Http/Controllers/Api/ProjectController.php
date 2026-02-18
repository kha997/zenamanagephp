<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ZenaContractResponseTrait;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectFormRequest;
use Src\CoreProject\Models\LegacyProjectAdapter as Project;
use App\Repositories\ProjectRepository;
use Src\CoreProject\Services\LegacyProjectServiceAdapter as ProjectService;
use App\Services\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * ProjectController - API Controller cho Project management
 * Sử dụng unified Project model và ProjectService
 */
class ProjectController extends Controller
{
    use ZenaContractResponseTrait;

    public function __construct(
        private ProjectService $projectService,
        private ProjectRepository $projectRepository
    ) {}

    /**
     * Get all projects with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $filters = $request->all();
            $perPage = (int)($filters['per_page'] ?? 15);
            unset($filters['per_page']);
            
            // Apply tenant isolation
            $filters['tenant_id'] = $user->tenant_id;
            $filters['user_id'] = $user->id; // For access control
            
            $projects = $this->projectRepository->getAll($filters, $perPage);
            
            return $this->zenaSuccessResponse($projects);
            
        } catch (\Exception $e) {
            Log::error('Failed to get projects', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'filters' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve projects',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific project by ID
     */
    public function show(Request $request, string $id): JsonResponse
    {
        try {
            $tenantId = TenantContext::id($request);

            if ($tenantId === null) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project not found'
                ], 404);
            }

            $user = Auth::user();
            $project = $this->projectRepository->getProjectById($id, [
                'client', 'projectManager', 'teamMembers', 'tasks', 'documents'
            ], $tenantId);

            if (!$project) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project not found'
                ], 404);
            }

            $routeTenantId = trim((string) $request->attributes->get('tenant_id', ''));

            if ($routeTenantId !== '' && $routeTenantId !== $project->tenant_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project not found'
                ], 404);
            }

            // Check tenant isolation
            if ($project->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied to this project'
                ], 403);
            }
            
            // Check user access
            if (!$user->hasRole(['SuperAdmin', 'Admin']) && 
                !$project->teamMembers()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied to this project'
                ], 403);
            }
            
            // Get project metrics
            $metrics = $this->projectService->getProjectMetrics($project->id, $user->id, $user->tenant_id);
            
            return $this->zenaSuccessResponse([
                'project' => $project,
                'metrics' => $metrics,
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get project', [
                'error' => $e->getMessage(),
                'project_id' => $id,
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new project
     */
    public function store(ProjectFormRequest $request): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Check permission
            if (!$user->hasPermission('project.write')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to create projects'
                ], 403);
            }
            
            $data = $request->validated();
            $data['tenant_id'] = $user->tenant_id;
            
            $project = $this->projectService->createProject($data, $user->id, $user->tenant_id);
            
            return $this->zenaSuccessResponse(
                $project,
                'Project created successfully',
                201
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to create project', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update an existing project
     */
    public function update(ProjectFormRequest $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $project = Project::find($id);
            
            if (!$project) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project not found'
                ], 404);
            }
            
            // Check tenant isolation
            if ($project->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied to this project'
                ], 403);
            }
            
            // Check permission
            if (!$user->hasPermission('project.write')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to update projects'
                ], 403);
            }
            
            $data = $request->validated();
            $project = $this->projectService->updateProject($project, $data, $user->id);
            
            return $this->zenaSuccessResponse(
                $project->load(['client', 'projectManager', 'teamMembers']),
                'Project updated successfully'
            );
            
        } catch (\Exception $e) {
            Log::error('Failed to update project', [
                'error' => $e->getMessage(),
                'project_id' => $id,
                'user_id' => Auth::id(),
                'data' => $request->all()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a project (soft delete)
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $project = Project::find($id);
            
            if (!$project) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project not found'
                ], 404);
            }
            
            // Check tenant isolation
            if ($project->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied to this project'
                ], 403);
            }
            
            // Check permission
            if (!$user->hasPermission('project.write')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to delete projects'
                ], 403);
            }
            
            $this->projectService->deleteProject($project, $user->id);
            
            return $this->zenaSuccessResponse(null, 'Project deleted successfully');
            
        } catch (\Exception $e) {
            Log::error('Failed to delete project', [
                'error' => $e->getMessage(),
                'project_id' => $id,
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete project',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update project status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $request->validate([
                'status' => 'required|in:' . implode(',', Project::VALID_STATUSES),
                'reason' => 'nullable|string|max:500'
            ]);
            
            $project = Project::find($id);
            
            if (!$project) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project not found'
                ], 404);
            }
            
            // Check tenant isolation
            if ($project->tenant_id !== $user->tenant_id) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied to this project'
                ], 403);
            }
            
            // Check permission
            if (!$user->hasPermission('project.write')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to update project status'
                ], 403);
            }
            
            $project = $this->projectService->updateProjectStatus(
                $project, 
                $request->input('status'), 
                $user->id,
                $request->input('reason')
            );
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project status updated successfully',
                'data' => $project
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to update project status', [
                'error' => $e->getMessage(),
                'project_id' => $id,
                'user_id' => Auth::id(),
                'new_status' => $request->input('status')
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update project status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get project statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $statistics = $this->projectRepository->getProjectStatistics($user->tenant_id);
            
            return response()->json([
                'status' => 'success',
                'data' => $statistics
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get project statistics', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve project statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get projects for dropdown
     */
    public function dropdown(): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $projects = $this->projectRepository->getProjectsForDropdown($user->tenant_id);
            
            return response()->json([
                'status' => 'success',
                'data' => $projects
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to get projects for dropdown', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve projects',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate project progress
     */
    public function recalculateProgress(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $project = Project::forTenant($user->tenant_id)->find($id);
            
            if (!$project) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project not found'
                ], 404);
            }
            
            // Check permission
            if (!$user->hasPermission('project.write')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to recalculate progress'
                ], 403);
            }
            
            $oldProgress = $project->progress;
            $project->updateProgress();
            
            Log::info('Project progress recalculated', [
                'project_id' => $project->id,
                'user_id' => $user->id,
                'old_progress' => $oldProgress,
                'new_progress' => $project->fresh()->progress
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project progress recalculated successfully',
                'data' => [
                    'old_progress' => $oldProgress,
                    'new_progress' => $project->fresh()->progress
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to recalculate project progress', [
                'error' => $e->getMessage(),
                'project_id' => $id,
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to recalculate project progress',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Recalculate project actual cost
     */
    public function recalculateActualCost(Request $request, string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            
            $project = Project::forTenant($user->tenant_id)->find($id);
            
            if (!$project) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Project not found'
                ], 404);
            }
            
            // Check permission
            if (!$user->hasPermission('project.write')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to recalculate actual cost'
                ], 403);
            }
            
            $oldActualCost = $project->budget_actual;
            
            // Calculate actual cost from tasks and other project expenses
            $taskCosts = $project->tasks()->sum('actual_cost') ?? 0;
            $otherExpenses = $project->settings['other_expenses'] ?? 0;
            $newActualCost = $taskCosts + $otherExpenses;
            
            $project->update(['budget_actual' => $newActualCost]);
            
            Log::info('Project actual cost recalculated', [
                'project_id' => $project->id,
                'user_id' => $user->id,
                'old_actual_cost' => $oldActualCost,
                'new_actual_cost' => $newActualCost,
                'task_costs' => $taskCosts,
                'other_expenses' => $otherExpenses
            ]);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project actual cost recalculated successfully',
                'data' => [
                    'old_actual_cost' => $oldActualCost,
                    'new_actual_cost' => $newActualCost,
                    'task_costs' => $taskCosts,
                    'other_expenses' => $otherExpenses
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Failed to recalculate project actual cost', [
                'error' => $e->getMessage(),
                'project_id' => $id,
                'user_id' => Auth::id()
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to recalculate project actual cost',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
