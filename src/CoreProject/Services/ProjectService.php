<?php declare(strict_types=1);

namespace Src\CoreProject\Services;

use Src\Foundation\Helpers\AuthHelper;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Component;
use Src\CoreProject\Models\Task;
use Src\Foundation\Events\EventBus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Carbon\Carbon;

/**
 * Service xử lý business logic cho Projects
 * 
 * Chức năng chính:
 * - CRUD operations cho projects
 * - Progress và cost calculation
 * - Project status management
 * - Project team management
 * - Event dispatching
 */
class ProjectService
{
    /**
     * Resolve actor ID từ auth helper với fallback an toàn
     * 
     * @return string|int
     */
    private function resolveActorId()
    {
        try {
            return AuthHelper::idOrSystem();
        } catch (\Throwable $e) {
            Log::warning('Failed to resolve actor ID from auth helper', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 'system';
        }
    }

    /**
     * Tạo project mới
     * 
     * @param array $data Dữ liệu project
     * @return Project
     * @throws ValidationException
     */
    public function createProject(array $data): Project
    {
        // Validate dữ liệu đầu vào
        $this->validateProjectData($data);
        
        return DB::transaction(function () use ($data) {
            // Tạo project
            $project = Project::create([
                'tenant_id' => $data['tenant_id'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'status' => $data['status'] ?? Project::STATUS_PLANNING,
                'progress' => 0.0,
                'actual_cost' => 0.0
            ]);
            
            // Tạo default baseline nếu có thông tin
            if (isset($data['baseline_cost']) || isset($data['baseline_start_date']) || isset($data['baseline_end_date'])) {
                $this->createDefaultBaseline($project, $data);
            }
            
            // Dispatch event
            EventBus::dispatch('Project.Project.Created', [
                'entityId' => $project->id,
                'projectId' => $project->id,
                'actorId' => $this->resolveActorId(),
                'changedFields' => $project->toArray(),
                'timestamp' => now()
            ]);
            
            return $project->load(['tenant', 'rootComponents', 'tasks']);
        });
    }

    /**
     * Cập nhật project
     * 
     * @param string $projectId ULID của project
     * @param array $data Dữ liệu cập nhật
     * @return Project
     * @throws ValidationException
     */
    public function updateProject(string $projectId, array $data): Project
    {
        $project = Project::where('ulid', $projectId)->firstOrFail();
        $oldData = $project->toArray();
        
        // Validate dữ liệu đầu vào
        $this->validateProjectData($data, $project);
        
        return DB::transaction(function () use ($project, $data, $oldData) {
            // Cập nhật project
            $project->update($data);
            
            // Tính toán changed fields
            $changedFields = [];
            foreach ($data as $key => $value) {
                if (isset($oldData[$key]) && $oldData[$key] !== $value) {
                    $changedFields[$key] = [
                        'old' => $oldData[$key],
                        'new' => $value
                    ];
                }
            }
            
            // Dispatch event nếu có thay đổi
            if (!empty($changedFields)) {
                EventBus::dispatch('Project.Project.Updated', [
                    'entityId' => $project->id,
                    'projectId' => $project->id,
                    'actorId' => $this->resolveActorId(),
                    'changedFields' => $changedFields,
                    'timestamp' => now()
                ]);
            }
            
            return $project->fresh(['tenant', 'rootComponents', 'tasks']);
        });
    }

    /**
     * Xóa project
     * 
     * @param string $projectId ULID của project
     * @return bool
     */
    public function deleteProject(string $projectId): bool
    {
        $project = Project::where('ulid', $projectId)->firstOrFail();
        
        return DB::transaction(function () use ($project) {
            // Kiểm tra xem project có thể xóa không
            if ($project->status === Project::STATUS_ACTIVE) {
                throw new ValidationException('Cannot delete active project');
            }
            
            // Soft delete các related entities trước
            $project->tasks()->delete();
            $project->components()->delete();
            
            // Dispatch event trước khi xóa
            EventBus::dispatch('Project.Project.Deleted', [
                'entityId' => $project->id,
                'projectId' => $project->id,
                'actorId' => $this->resolveActorId(),
                'changedFields' => $project->toArray(),
                'timestamp' => now()
            ]);
            
            // Xóa project
            return $project->delete();
        });
    }

    /**
     * Lấy danh sách projects với filter và pagination
     * 
     * @param array $filters Bộ lọc
     * @return LengthAwarePaginator
     */
    public function getProjectsList(array $filters = []): LengthAwarePaginator
    {
        $query = Project::query();
        
        // Filter by tenant
        if (isset($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }
        
        // Filter by status
        if (isset($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        
        // Filter active projects only
        if (isset($filters['active_only']) && $filters['active_only']) {
            $query->active();
        }
        
        // Search by name
        if (isset($filters['search'])) {
            $query->where('name', 'like', '%' . $filters['search'] . '%');
        }
        
        // Date range filters
        if (isset($filters['start_date_from'])) {
            $query->where('start_date', '>=', $filters['start_date_from']);
        }
        
        if (isset($filters['start_date_to'])) {
            $query->where('start_date', '<=', $filters['start_date_to']);
        }
        
        // Progress range filters
        if (isset($filters['progress_min'])) {
            $query->where('progress', '>=', $filters['progress_min']);
        }
        
        if (isset($filters['progress_max'])) {
            $query->where('progress', '<=', $filters['progress_max']);
        }
        
        // Sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortBy, $sortDirection);
        
        // Load relationships
        $query->with(['tenant', 'rootComponents', 'tasks']);
        
        return $query->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Lấy chi tiết project
     * 
     * @param string $projectId ULID của project
     * @return Project
     */
    public function getProjectDetail(string $projectId): Project
    {
        return Project::where('ulid', $projectId)
                     ->with([
                         'tenant',
                         'rootComponents.children',
                         'tasks.assignments.user',
                         'userRoles.user',
                         'userRoles.role',
                         'baselines' => function ($query) {
                             $query->orderBy('created_at', 'desc')->limit(5);
                         },
                         'changeRequests' => function ($query) {
                             $query->orderBy('created_at', 'desc')->limit(10);
                         }
                     ])
                     ->firstOrFail();
    }

    /**
     * Cập nhật status của project
     * 
     * @param string $projectId ULID của project
     * @param string $newStatus Status mới
     * @param string|null $note Ghi chú
     * @return Project
     */
    public function updateProjectStatus(string $projectId, string $newStatus, ?string $note = null): Project
    {
        $project = Project::where('ulid', $projectId)->firstOrFail();
        $oldStatus = $project->status;
        
        // Validate status transition
        $this->validateStatusTransition($oldStatus, $newStatus);
        
        return DB::transaction(function () use ($project, $newStatus, $oldStatus, $note) {
            $project->update(['status' => $newStatus]);
            
            // Dispatch event
            EventBus::dispatch('Project.Project.StatusChanged', [
                'entityId' => $project->id,
                'projectId' => $project->id,
                'actorId' => $this->resolveActorId(),
                'changedFields' => [
                    'status' => [
                        'old' => $oldStatus,
                        'new' => $newStatus
                    ]
                ],
                'note' => $note,
                'timestamp' => now()
            ]);
            
            return $project->fresh();
        });
    }

    /**
     * Trigger recalculation của project progress và cost
     * 
     * @param string $projectId ULID của project
     * @return Project
     */
    public function recalculateProjectMetrics(string $projectId): Project
    {
        $project = Project::where('ulid', $projectId)->firstOrFail();
        
        return DB::transaction(function () use ($project) {
            // Recalculate progress
            $project->recalculateProgress();
            
            // Recalculate actual cost
            $project->recalculateActualCost();
            
            return $project->fresh(['rootComponents', 'tasks']);
        });
    }

    /**
     * Lấy project statistics
     * 
     * @param string $projectId ULID của project
     * @return array
     */
    public function getProjectStatistics(string $projectId): array
    {
        $project = Project::where('ulid', $projectId)
                         ->with(['rootComponents', 'tasks', 'changeRequests'])
                         ->firstOrFail();
        
        $totalTasks = $project->tasks->count();
        $completedTasks = $project->tasks->where('status', 'completed')->count();
        $activeTasks = $project->tasks->where('status', 'in_progress')->count();
        $pendingTasks = $project->tasks->where('status', 'pending')->count();
        
        $totalComponents = $project->rootComponents->count();
        $completedComponents = $project->rootComponents->where('progress_percent', 100)->count();
        
        $totalChangeRequests = $project->changeRequests->count();
        $approvedChangeRequests = $project->changeRequests->where('status', 'approved')->count();
        $pendingChangeRequests = $project->changeRequests->where('status', 'awaiting_approval')->count();
        
        return [
            'project_id' => $project->id,
            'project_name' => $project->name,
            'status' => $project->status,
            'progress' => $project->progress,
            'actual_cost' => $project->actual_cost,
            'tasks' => [
                'total' => $totalTasks,
                'completed' => $completedTasks,
                'active' => $activeTasks,
                'pending' => $pendingTasks,
                'completion_rate' => $totalTasks > 0 ? ($completedTasks / $totalTasks) * 100 : 0
            ],
            'components' => [
                'total' => $totalComponents,
                'completed' => $completedComponents,
                'completion_rate' => $totalComponents > 0 ? ($completedComponents / $totalComponents) * 100 : 0
            ],
            'change_requests' => [
                'total' => $totalChangeRequests,
                'approved' => $approvedChangeRequests,
                'pending' => $pendingChangeRequests
            ],
            'duration_days' => $project->start_date && $project->end_date 
                ? $project->start_date->diffInDays($project->end_date) 
                : null,
            'days_elapsed' => $project->start_date 
                ? $project->start_date->diffInDays(now()) 
                : null
        ];
    }

    /**
     * Validate dữ liệu project
     * 
     * @param array $data
     * @param Project|null $project
     * @throws ValidationException
     */
    private function validateProjectData(array $data, ?Project $project = null): void
    {
        // Validate required fields for creation
        if (!$project) {
            if (empty($data['tenant_id'])) {
                throw new ValidationException('Tenant ID is required');
            }
            if (empty($data['name'])) {
                throw new ValidationException('Project name is required');
            }
        }
        
        // Validate status
        if (isset($data['status']) && !in_array($data['status'], Project::VALID_STATUSES)) {
            throw new ValidationException('Invalid project status');
        }
        
        // Validate dates
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);
            
            if ($startDate->gte($endDate)) {
                throw new ValidationException('End date must be after start date');
            }
        }
        
        // Validate progress
        if (isset($data['progress'])) {
            if ($data['progress'] < 0 || $data['progress'] > 100) {
                throw new ValidationException('Progress must be between 0 and 100');
            }
        }
        
        // Validate actual_cost
        if (isset($data['actual_cost']) && $data['actual_cost'] < 0) {
            throw new ValidationException('Actual cost cannot be negative');
        }
    }

    /**
     * Validate status transition
     * 
     * @param string $oldStatus
     * @param string $newStatus
     * @throws ValidationException
     */
    private function validateStatusTransition(string $oldStatus, string $newStatus): void
    {
        $validTransitions = [
            Project::STATUS_PLANNING => [Project::STATUS_ACTIVE, Project::STATUS_CANCELLED],
            Project::STATUS_ACTIVE => [Project::STATUS_ON_HOLD, Project::STATUS_COMPLETED, Project::STATUS_CANCELLED],
            Project::STATUS_ON_HOLD => [Project::STATUS_ACTIVE, Project::STATUS_CANCELLED],
            Project::STATUS_COMPLETED => [], // No transitions from completed
            Project::STATUS_CANCELLED => [] // No transitions from cancelled
        ];
        
        if (!isset($validTransitions[$oldStatus]) || !in_array($newStatus, $validTransitions[$oldStatus])) {
            throw new ValidationException("Invalid status transition from {$oldStatus} to {$newStatus}");
        }
    }

    /**
     * Tạo default baseline cho project
     * 
     * @param Project $project
     * @param array $data
     */
    private function createDefaultBaseline(Project $project, array $data): void
    {
        // Logic tạo baseline sẽ được implement khi có BaselineService
        // Tạm thời log để tracking
        Log::info('Creating default baseline for project', [
            'project_id' => $project->id,
            'baseline_data' => $data
        ]);
    }
}