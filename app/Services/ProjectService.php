<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * ProjectService - Business logic layer cho Project management
 */
class ProjectService
{
    /**
     * Tạo project mới
     */
    public function createProject(array $data, string $userId): Project
    {
        DB::beginTransaction();
        try {
            // Validate business rules
            $this->validateProjectData($data);
            
            // Set default values
            $data['status'] = $data['status'] ?? Project::STATUS_DRAFT;
            $data['progress'] = 0;
            $data['budget_actual'] = 0;
            $data['priority'] = $data['priority'] ?? Project::PRIORITY_MEDIUM;
            
            // Generate project code nếu chưa có
            if (empty($data['code'])) {
                $data['code'] = $this->generateProjectCode();
            }
            
            $project = Project::create($data);
            
            // Add creator as team member
            $project->teamMembers()->attach($userId, [
                'role' => 'project_manager',
                'joined_at' => now()
            ]);
            
            // Log audit
            $this->logAudit('project_created', $project, [], $project->toArray());
            
            DB::commit();
            
            Log::info('Project created successfully', [
                'project_id' => $project->id,
                'user_id' => $userId,
                'project_name' => $project->name
            ]);
            
            return $project;
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to create project', [
                'error' => $e->getMessage(),
                'user_id' => $userId,
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Cập nhật project
     */
    public function updateProject(Project $project, array $data, string $userId): Project
    {
        DB::beginTransaction();
        try {
            $oldData = $project->toArray();
            
            // Validate business rules
            $this->validateProjectData($data, $project->id);
            
            $project->update($data);
            
            // Log audit
            $this->logAudit('project_updated', $project, $oldData, $project->toArray());
            
            DB::commit();
            
            Log::info('Project updated successfully', [
                'project_id' => $project->id,
                'user_id' => $userId,
                'changes' => array_diff_assoc($data, $oldData)
            ]);
            
            return $project->fresh();
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to update project', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'user_id' => $userId,
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Cập nhật trạng thái project
     */
    public function updateProjectStatus(Project $project, string $newStatus, string $userId, string $reason = null): Project
    {
        if (!$project->canTransitionTo($newStatus)) {
            throw new \InvalidArgumentException("Cannot transition from {$project->status} to {$newStatus}");
        }
        
        DB::beginTransaction();
        try {
            $oldStatus = $project->status;
            $project->update(['status' => $newStatus]);
            
            // Recalculate progress nếu cần
            if ($newStatus === Project::STATUS_ACTIVE) {
                $project->updateProgress();
            }
            
            // Log audit
            $this->logAudit('project_status_changed', $project, 
                           ['status' => $oldStatus, 'reason' => $reason], 
                           ['status' => $newStatus]);
            
            DB::commit();
            
            Log::info('Project status updated', [
                'project_id' => $project->id,
                'user_id' => $userId,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'reason' => $reason
            ]);
            
            return $project->fresh();
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to update project status', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'user_id' => $userId,
                'new_status' => $newStatus
            ]);
            throw $e;
        }
    }

    /**
     * Xóa project (soft delete)
     */
    public function deleteProject(Project $project, string $userId): bool
    {
        DB::beginTransaction();
        try {
            // Log audit trước khi xóa
            $this->logAudit('project_deleted', $project, $project->toArray(), []);
            
            // Soft delete project
            $project->delete();
            
            DB::commit();
            
            Log::info('Project deleted successfully', [
                'project_id' => $project->id,
                'user_id' => $userId,
                'project_name' => $project->name
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Failed to delete project', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    /**
     * Thêm team member vào project
     */
    public function addTeamMember(Project $project, string $userId, string $role = 'member'): void
    {
        // Kiểm tra user đã là member chưa
        if ($project->teamMembers()->where('user_id', $userId)->exists()) {
            throw new \InvalidArgumentException('User is already a team member');
        }
        
        $project->teamMembers()->attach($userId, [
            'role' => $role,
            'joined_at' => now()
        ]);
        
        Log::info('Team member added to project', [
            'project_id' => $project->id,
            'user_id' => $userId,
            'role' => $role
        ]);
    }

    /**
     * Xóa team member khỏi project
     */
    public function removeTeamMember(Project $project, string $userId): void
    {
        $project->teamMembers()->updateExistingPivot($userId, [
            'left_at' => now()
        ]);
        
        Log::info('Team member removed from project', [
            'project_id' => $project->id,
            'user_id' => $userId
        ]);
    }

    /**
     * Lấy projects với filtering và pagination
     */
    public function getProjects(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Project::with(['client', 'projectManager', 'teamMembers']);
        
        // Apply filters
        if (!empty($filters['tenant_id'])) {
            $query->forTenant($filters['tenant_id']);
        }
        
        if (!empty($filters['status'])) {
            $query->byStatus($filters['status']);
        }
        
        if (!empty($filters['priority'])) {
            $query->byPriority($filters['priority']);
        }
        
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }
        
        if (!empty($filters['overdue'])) {
            $query->overdue();
        }
        
        if (!empty($filters['date_from']) && !empty($filters['date_to'])) {
            $query->inDateRange($filters['date_from'], $filters['date_to']);
        }
        
        // Apply user access control
        if (!empty($filters['user_id'])) {
            $user = User::find($filters['user_id']);
            if ($user && !$user->hasRole(['SuperAdmin', 'Admin'])) {
                $query->whereHas('teamMembers', function ($q) use ($user) {
                    $q->where('user_id', $user->id);
                });
            }
        }
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Lấy project metrics
     */
    public function getProjectMetrics(Project $project): array
    {
        $tasks = $project->tasks();
        $documents = $project->documents();
        
        return [
            'overview' => [
                'progress' => $project->progress,
                'budget_utilization' => $project->getBudgetUtilization(),
                'timeline_status' => $this->getTimelineStatus($project),
                'team_size' => $project->teamMembers()->count(),
                'is_overdue' => $project->isOverdue(),
                'days_remaining' => $project->getDaysRemaining()
            ],
            'tasks' => [
                'total' => $tasks->count(),
                'completed' => $tasks->where('status', 'completed')->count(),
                'in_progress' => $tasks->where('status', 'in_progress')->count(),
                'pending' => $tasks->where('status', 'pending')->count(),
                'overdue' => $tasks->where('due_date', '<', now())->count()
            ],
            'documents' => [
                'total' => $documents->count(),
                'pending_approval' => $documents->where('status', 'pending')->count(),
                'approved' => $documents->where('status', 'approved')->count(),
                'rejected' => $documents->where('status', 'rejected')->count()
            ]
        ];
    }

    /**
     * Validate project data
     */
    private function validateProjectData(array $data, ?string $projectId = null): void
    {
        // Kiểm tra date overlap với projects khác
        if (!empty($data['start_date']) && !empty($data['end_date'])) {
            $query = Project::where('tenant_id', $data['tenant_id'] ?? auth()->user()->tenant_id)
                           ->where('id', '!=', $projectId)
                           ->where(function ($q) use ($data) {
                               $q->whereBetween('start_date', [$data['start_date'], $data['end_date']])
                                 ->orWhereBetween('end_date', [$data['start_date'], $data['end_date']])
                                 ->orWhere(function ($q2) use ($data) {
                                     $q2->where('start_date', '<=', $data['start_date'])
                                        ->where('end_date', '>=', $data['end_date']);
                                 });
                           });
            
            if ($query->exists()) {
                throw new \InvalidArgumentException('Project date range overlaps with existing project');
            }
        }
        
        // Kiểm tra budget hợp lệ
        if (!empty($data['budget_planned']) && $data['budget_planned'] < 0) {
            throw new \InvalidArgumentException('Budget cannot be negative');
        }
        
        if (!empty($data['budget_actual']) && $data['budget_actual'] < 0) {
            throw new \InvalidArgumentException('Actual budget cannot be negative');
        }
    }

    /**
     * Generate project code
     */
    private function generateProjectCode(): string
    {
        $year = date('Y');
        $count = Project::whereYear('created_at', $year)->count() + 1;
        return "PRJ-{$year}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Get timeline status
     */
    private function getTimelineStatus(Project $project): string
    {
        if (!$project->start_date || !$project->end_date) {
            return 'no_timeline';
        }
        
        $now = now();
        
        if ($now->lt($project->start_date)) {
            return 'not_started';
        } elseif ($now->gt($project->end_date)) {
            return 'overdue';
        } else {
            $totalDays = $project->start_date->diffInDays($project->end_date);
            $elapsedDays = $project->start_date->diffInDays($now);
            $progress = $totalDays > 0 ? ($elapsedDays / $totalDays) * 100 : 0;
            
            if ($progress < 25) return 'early';
            if ($progress < 75) return 'on_track';
            return 'late';
        }
    }

    /**
     * Log audit
     */
    private function logAudit(string $action, Project $project, array $oldData = [], array $newData = []): void
    {
        if (class_exists('\App\Services\AuditService')) {
            \App\Services\AuditService::log(
                $action,
                'Project',
                $project->id,
                $oldData,
                $newData,
                $project->id, // project_id
                $project->tenant_id
            );
        }
    }
}