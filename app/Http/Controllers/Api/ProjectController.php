<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProjectFormRequest;
use App\Repositories\ProjectRepository;
use App\Services\ProjectService;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

/**
 * ProjectController - API Controller cho Project management
 * Sử dụng unified Project model và ProjectService
 */
class ProjectController extends Controller
{
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
            if (!$this->userHasPermission($user, 'project.read')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to view projects'
                ], 403);
            }
            $filters = $request->all();
            $perPage = (int)($filters['per_page'] ?? 15);
            unset($filters['per_page']);

            if (app()->environment('testing')) {
                Log::info('ProjectController::index ENTER', [
                    'user_id' => optional($user)->id,
                    'tenant_id' => optional($user)->tenant_id,
                    'request_filters' => $filters,
                    'per_page' => $perPage,
                ]);
            }
            
            // Apply tenant isolation
            $filters['tenant_id'] = $user->tenant_id;
            $filters['user_id'] = $user->id; // For access control
            
            $projects = $this->projectRepository->getAll($filters, $perPage);

            if (app()->environment('testing')) {
                Log::info('ProjectController::index fetched projects', [
                    'total' => $projects->total(),
                    'items' => $projects->count(),
                ]);
            }

            $payloadData = $projects->getCollection()
                ->map(fn (Project $project) => $this->projectPayload($project))
                ->values()
                ->all();

            if (app()->environment('testing')) {
                Log::info('ProjectController::index RESPONSE', [
                    'filters' => $filters,
                    'projects_returned' => count($payloadData),
                    'total' => $projects->total(),
                ]);
            }
            
            Log::info('ProjectController::index returning response', [
                'filters' => $filters,
                'project_count' => $projects->total()
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $payloadData,
                'meta' => [
                    'total' => $projects->total(),
                    'per_page' => $projects->perPage(),
                    'current_page' => $projects->currentPage(),
                    'last_page' => $projects->lastPage(),
                    'from' => $projects->firstItem(),
                    'to' => $projects->lastItem()
                ]
            ]);
            
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
    public function show(string $id): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$this->userHasPermission($user, 'project.read')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to view this project'
                ], 403);
            }
            
            $project = $this->projectRepository->getProjectById($id, [
                'client', 'projectManager', 'teamMembers', 'tasks', 'documents'
            ]);
            
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
            
            // Check user access
            if (!$user->hasRole(['SuperAdmin', 'Admin']) && 
                !$project->teamMembers()->where('user_id', $user->id)->exists()) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Access denied to this project'
                ], 403);
            }
            
            // Get project metrics
            $metrics = $this->projectService->getProjectMetrics($project);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'project' => $project,
                    'metrics' => $metrics
                ]
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
            if (!$this->userHasPermission($user, 'project.create')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to create projects'
                ], 403);
            }
            
            $data = $request->validated();
            $data['tenant_id'] = $user->tenant_id;
            
            $this->logProjectCreationStep('before-service-call', [
                'user_id' => $user?->id,
                'tenant_id' => $user->tenant_id,
                'request_keys' => array_keys($data)
            ]);

            $project = $this->projectService->createProject($data, $user->id);

            $this->logProjectCreationStep('after-service-call', [
                'project_id' => $project->id,
                'tenant_id' => $project->tenant_id,
                'project_code' => $project->code
            ]);

            $relations = $this->getAvailableRelations($project);
            $this->logProjectCreationStep('load-relations-start', ['relations' => $relations]);
            $payload = $project->loadMissing($relations);
            $this->logProjectCreationStep('load-relations-end', ['relations' => $relations]);

            $dbTeamMembers = DB::table('project_team_members')
                ->join('users', 'project_team_members.user_id', '=', 'users.id')
                ->where('project_team_members.project_id', $payload->id)
                ->select([
                    'users.id',
                    'users.name',
                    'users.email',
                    'users.tenant_id',
                    'project_team_members.role',
                    'project_team_members.joined_at',
                    'project_team_members.left_at',
                ])
                ->get();

            $sanitizedTeamMembers = $dbTeamMembers->map(function ($member) {
                return [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'tenant_id' => $member->tenant_id,
                    'role' => $member->role,
                    'joined_at' => $member->joined_at ? Carbon::parse($member->joined_at)->toISOString() : null,
                    'left_at' => $member->left_at ? Carbon::parse($member->left_at)->toISOString() : null,
                ];
            })->values()->all();

            $responseData = [
                'id' => $payload->id,
                'tenant_id' => $payload->tenant_id,
                'name' => $payload->name,
                'description' => $payload->description,
                'status' => $payload->status,
                'progress' => $payload->progress,
                'created_at' => $payload->created_at?->toISOString(),
                'updated_at' => $payload->updated_at?->toISOString(),
                'teamMembers' => $sanitizedTeamMembers,
            ];

            Log::info('ProjectController::store returning response', ['project_id' => $project->id]);

            return response()->json([
                'status' => 'success',
                'message' => 'Project created successfully',
                'data' => $responseData
            ], 201);
            
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

    private function logProjectCreationStep(string $stage, array $context = []): void
    {
        if (!app()->environment('testing')) {
            return;
        }

        Log::info("ProjectController::store {$stage}", $context);
    }

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
            if (!$this->userHasPermission($user, 'project.update')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to update projects'
                ], 403);
            }
            
            $data = $request->validated();
            $project = $this->projectService->updateProject($project, $data, $user->id);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project updated successfully',
                'data' => $project->loadMissing($this->getAvailableRelations($project))
            ]);
            
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
     * Get relations that actually exist on the model
     */
    private function getAvailableRelations($model): array
    {
        $relations = ['client', 'projectManager', 'teamMembers'];

        return array_filter($relations, function (string $relation) use ($model) {
            return method_exists($model, $relation);
        });
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
            if (!$this->userHasPermission($user, 'project.delete')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to delete projects'
                ], 403);
            }
            
            $this->projectService->deleteProject($project, $user->id);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Project deleted successfully'
            ]);
            
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
            $status = $this->normalizeIncomingStatus($request);
            $request->merge(['status' => $status]);
            
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
            if (!$this->userHasPermission($user, 'project.update')) {
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
     * Normalize status values coming from different payload shapes.
     */
    private function normalizeIncomingStatus(Request $request): ?string
    {
        $payload = $request->all();
        $candidates = [
            $request->input('status'),
            Arr::get($payload, 'data.status'),
            Arr::get($payload, 'project.status'),
            Arr::get($payload, 'attributes.status'),
        ];

        foreach ($candidates as $candidate) {
            if (!is_scalar($candidate)) {
                continue;
            }

            $normalized = trim((string) $candidate);

            if ($normalized === '') {
                continue;
            }

            return strtolower($normalized);
        }

        return null;
    }

    /**
     * Get project statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$this->userHasPermission($user, 'project.read')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to view project statistics'
                ], 403);
            }
            
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
            if (!$this->userHasPermission($user, 'project.read')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Insufficient permissions to view projects'
                ], 403);
            }
            
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
            if (!$this->userHasPermission($user, 'project.update')) {
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
            if (!$this->userHasPermission($user, 'project.update')) {
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

    private function userHasPermission(User $user, string $permission): bool
    {
        foreach ($this->getPermissionCandidates($permission) as $candidate) {
            if ($user->hasPermission($candidate)) {
                return true;
            }
        }

        return false;
    }

    private function getPermissionCandidates(string $permission): array
    {
        $map = config('rbac.permission_aliases', []);
        $candidates = [$permission];

        foreach ($map[$permission] ?? [] as $alias) {
            $candidates[] = $alias;
        }

        return $candidates;
    }

    private function projectPayload(Project $project): array
    {
        if (app()->environment('testing')) {
            Log::info('ProjectController::projectPayload serializing', [
                'project_id' => $project->id,
            ]);
        }
        $payload = [
            'id' => $project->id,
            'tenant_id' => $project->tenant_id,
            'manager_id' => $project->manager_id,
            'code' => $project->code,
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
            'priority' => $project->priority,
            'progress' => $project->progress,
            'budget' => $project->budget,
            'spent_amount' => $project->spent_amount,
            'budget_total' => $project->budget_total,
            'start_date' => $this->isoTimestamp($project->start_date),
            'end_date' => $this->isoTimestamp($project->end_date),
            'created_at' => $this->isoTimestamp($project->created_at),
            'updated_at' => $this->isoTimestamp($project->updated_at),
            'project_manager' => $this->projectManagerPayload($project),
            'team_members' => $this->projectTeamMembersPayload($project),
        ];

        return $payload;
    }

    private function projectManagerPayload(Project $project): ?array
    {
        if (!$project->relationLoaded('manager')) {
            $project->loadMissing('manager');
        }

        if (!$project->manager) {
            return null;
        }

        return $this->userPayload($project->manager);
    }

    private function projectTeamMembersPayload(Project $project): array
    {
        $rows = DB::table('project_team_members')
            ->join('users', 'project_team_members.user_id', '=', 'users.id')
            ->where('project_team_members.project_id', $project->id)
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.tenant_id',
                'project_team_members.role',
                'project_team_members.joined_at',
                'project_team_members.left_at',
            ])
            ->get();

        if (app()->environment('testing')) {
            Log::info('ProjectController::projectTeamMembersPayload (db)', [
                'project_id' => $project->id,
                'team_members_retrieved' => $rows->count(),
            ]);
        }

        return $rows->map(function ($row) {
            return [
                'id' => $row->id,
                'name' => $row->name,
                'email' => $row->email,
                'tenant_id' => $row->tenant_id,
                'pivot' => [
                    'role' => $row->role,
                    'joined_at' => $this->isoTimestamp($row->joined_at),
                    'left_at' => $this->isoTimestamp($row->left_at),
                ],
            ];
        })->toArray();
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'tenant_id' => $user->tenant_id,
            'role' => $user->role ?? null,
        ];
    }

    private function isoTimestamp(mixed $value): ?string
    {
        if (!$value) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value)->toISOString();
        }

        try {
            return Carbon::parse($value)->toISOString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
