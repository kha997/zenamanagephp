<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * ProjectManagementService
 * 
 * Unified service for all project management operations
 * Replaces multiple project controllers and services
 */
class ProjectManagementService
{
    use ServiceBaseTrait;

    protected string $modelClass = Project::class;

    /**
     * Get projects with pagination and filtering
     */
    public function getProjects(
        array $filters = [],
        int $perPage = 15,
        string $sortBy = 'updated_at',
        string $sortDirection = 'desc',
        string|int|null $tenantId = null
    ): LengthAwarePaginator {
        $this->validateTenantAccess($tenantId);
        
        $query = Project::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['owner']);

        // Apply filters
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['priority']) && $filters['priority']) {
            $query->where('priority', $filters['priority']);
        }

        if (isset($filters['owner_id']) && $filters['owner_id']) {
            $query->where('owner_id', $filters['owner_id']);
        }

        if (isset($filters['start_date_from']) && $filters['start_date_from']) {
            $query->where('start_date', '>=', $filters['start_date_from']);
        }

        if (isset($filters['start_date_to']) && $filters['start_date_to']) {
            $query->where('start_date', '<=', $filters['start_date_to']);
        }

        if (isset($filters['end_date_from']) && $filters['end_date_from']) {
            $query->where('end_date', '>=', $filters['end_date_from']);
        }

        if (isset($filters['end_date_to']) && $filters['end_date_to']) {
            $query->where('end_date', '<=', $filters['end_date_to']);
        }

        return $query
            ->orderBy($sortBy, $sortDirection)
            ->paginate($perPage);
    }

    /**
     * Get project by ID with tenant isolation
     */
    public function getProjectById(string|int $id, string|int|null $tenantId = null): ?Project
    {
        $this->validateTenantAccess($tenantId);
        
        return Project::with(['owner', 'tasks'])
            ->where('id', $id)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->first();
    }

    /**
     * Create new project
     */
    public function createProject(array $data, string|int|null $tenantId = null): Project
    {
        $this->validateTenantAccess($tenantId);
        
        $this->validateProjectData($data, 'create');
        
        $data['tenant_id'] = $tenantId ?? Auth::user()?->tenant_id;
        $data['owner_id'] = $data['owner_id'] ?? Auth::id();
        
        $project = Project::create($data);
        
        // Clear project stats cache
        $this->clearCache('project_stats');
        
        $this->logCrudOperation('created', $project);
        
        return $project->load(['owner']);
    }

    /**
     * Update project
     */
    public function updateProject(string|int $id, array $data, string|int|null $tenantId = null): Project
    {
        $this->validateTenantAccess($tenantId);
        
        $project = $this->findByIdOrFail($id, $tenantId);
        $this->validateModelOwnership($project, $tenantId);
        
        $this->validateProjectData($data, 'update', $project);
        
        $project->update($data);
        
        $this->logCrudOperation('updated', $project);
        
        return $project->fresh()->load(['owner']);
    }

    /**
     * Restore project
     */
    public function restoreProject(string|int $id, string|int|null $tenantId = null): Project
    {
        $this->validateTenantAccess($tenantId);
        
        // Find project including soft-deleted ones
        $project = $this->findByIdOrFailWithTrashed($id, $tenantId);
        $this->validateModelOwnership($project, $tenantId);
        
        $project->restore();
        
        $this->logCrudOperation('restored', $project);
        
        return $project->fresh()->load(['owner']);
    }

    /**
     * Delete project
     */
    public function deleteProject(string|int $id, string|int|null $tenantId = null): bool
    {
        $this->validateTenantAccess($tenantId);
        
        $project = $this->findByIdOrFail($id, $tenantId);
        $this->validateModelOwnership($project, $tenantId);
        
        $deleted = $project->delete();
        
        if ($deleted) {
            $this->logCrudOperation('deleted', $project);
        }
        
        return $deleted;
    }

    /**
     * Bulk delete projects
     */
    public function bulkDeleteProjects(array $ids, ?int $tenantId = null): int
    {
        $this->validateTenantAccess($tenantId);
        
        $count = Project::whereIn('id', $ids)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->delete();
        
        $this->logBulkOperation('deleted', Project::class, $count);
        
        return $count;
    }

    /**
     * Update project status
     */
    public function updateProjectStatus(string|int $id, string $status, string|int|null $tenantId = null): Project
    {
        $this->validateTenantAccess($tenantId);
        
        $project = $this->findByIdOrFail($id, $tenantId);
        $this->validateModelOwnership($project, $tenantId);
        
        $this->validateStatus($status);
        
        $project->update(['status' => $status]);
        
        $this->logCrudOperation('status_updated', $project, [
            'new_status' => $status,
            'old_status' => $project->getOriginal('status')
        ]);
        
        return $project->fresh()->load(['owner']);
    }

    /**
     * Update project progress
     */
    public function updateProjectProgress(int $id, int $progress, ?int $tenantId = null): Project
    {
        $this->validateTenantAccess($tenantId);
        
        $project = $this->findByIdOrFail($id, $tenantId);
        $this->validateModelOwnership($project, $tenantId);
        
        if ($progress < 0 || $progress > 100) {
            abort(422, 'Progress must be between 0 and 100');
        }
        
        $project->update(['progress' => $progress]);
        
        $this->logCrudOperation('progress_updated', $project, [
            'new_progress' => $progress,
            'old_progress' => $project->getOriginal('progress')
        ]);
        
        return $project->fresh()->load(['owner']);
    }

    /**
     * Assign project to user
     */
    public function assignProject(int $id, int $userId, ?int $tenantId = null): Project
    {
        $this->validateTenantAccess($tenantId);
        
        $project = $this->findByIdOrFail($id, $tenantId);
        $this->validateModelOwnership($project, $tenantId);
        
        // Validate user exists and belongs to same tenant
        $user = User::where('id', $userId)
            ->where('tenant_id', $tenantId ?? Auth::user()?->tenant_id)
            ->first();
        
        if (!$user) {
            abort(404, 'User not found');
        }
        
        $project->update(['owner_id' => $userId]);
        
        $this->logCrudOperation('assigned', $project, [
            'new_owner_id' => $userId,
            'old_owner_id' => $project->getOriginal('owner_id')
        ]);
        
        return $project->fresh()->load(['owner']);
    }

    /**
     * Get project statistics
     */
    public function getProjectStats(string|int|null $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        return $this->getCached("project_stats", function() use ($tenantId) {
            $query = Project::query()->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));
            
            return [
                'total' => $query->count(),
                'by_status' => $query->groupBy('status')->selectRaw('status, count(*) as count')->pluck('count', 'status'),
                'by_priority' => $query->groupBy('priority')->selectRaw('priority, count(*) as count')->pluck('count', 'priority'),
                'average_progress' => $query->avg('progress'),
                'total_budget' => $query->sum('budget_total'),
                'total_spent' => $query->sum('budget_actual'),
                'created_this_month' => $query->whereMonth('created_at', now()->month)->count(),
                'overdue' => $query->where('end_date', '<', now())->where('status', '!=', 'completed')->count()
            ];
        }, 300);
    }

    /**
     * Search projects
     */
    public function searchProjects(
        string $search,
        int $limit = 10,
        ?int $tenantId = null
    ): Collection {
        $this->validateTenantAccess($tenantId);
        
        return Project::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Get recent projects
     */
    public function getRecentProjects(int $limit = 5, ?int $tenantId = null): Collection
    {
        $this->validateTenantAccess($tenantId);
        
        return Project::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['owner'])
            ->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Validate project data
     */
    protected function validateProjectData(array $data, string $action, ?Project $project = null): void
    {
        $rules = [
            'name' => $action === 'create' ? ['required', 'string', 'max:255'] : ['sometimes', 'string', 'max:255'],
            'description' => $action === 'create' ? ['required', 'string', 'max:1000'] : ['sometimes', 'string', 'max:1000'],
            'code' => [
                $action === 'create' ? 'required' : 'sometimes',
                'string',
                'max:50',
                Rule::unique('projects')->ignore($project?->id)
            ],
            'status' => $action === 'create' ? ['required', 'string', 'in:planning,active,on_hold,completed,cancelled'] : ['sometimes', 'string', 'in:planning,active,on_hold,completed,cancelled'],
            'priority' => ['sometimes', 'string', 'in:low,medium,high,critical'],
            'progress' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'budget_total' => ['sometimes', 'numeric', 'min:0'],
            'budget_actual' => ['sometimes', 'numeric', 'min:0'],
            'start_date' => $action === 'create' ? ['required', 'date'] : ['sometimes', 'date'],
            'end_date' => $action === 'create' ? ['required', 'date', 'after:start_date'] : ['sometimes', 'date', 'after:start_date'],
            'owner_id' => ['sometimes', 'integer', 'exists:users,id']
        ];

        $validator = Validator::make($data, $rules);
        
        if ($validator->fails()) {
            $this->logError('Project validation failed', null, [
                'action' => $action,
                'errors' => $validator->errors()->toArray()
            ]);
            
            abort(422, 'Validation failed: ' . $validator->errors()->first());
        }
    }

    /**
     * Validate status
     */
    protected function validateStatus(string $status): void
    {
        $validStatuses = ['planning', 'active', 'on_hold', 'completed', 'cancelled'];
        
        if (!in_array($status, $validStatuses)) {
            $this->logError('Invalid status', null, ['status' => $status]);
            abort(422, 'Invalid status');
        }
    }

    /**
     * Get project dashboard data
     */
    public function getProjectDashboardData(?int $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        return $this->getCached("project_dashboard", function() use ($tenantId) {
            $query = Project::query()->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));
            
            return [
                'total_projects' => $query->count(),
                'active_projects' => $query->where('status', 'active')->count(),
                'completed_projects' => $query->where('status', 'completed')->count(),
                'average_progress' => $query->avg('progress'),
                'total_budget' => $query->sum('budget_total'),
                'total_spent' => $query->sum('budget_actual'),
                'budget_utilization' => $query->sum('budget_total') > 0 
                    ? round(($query->sum('budget_actual') / $query->sum('budget_total')) * 100) 
                    : 0,
                'overdue_projects' => $query->where('end_date', '<', now())
                    ->where('status', '!=', 'completed')
                    ->count()
            ];
        }, 300);
    }
}
