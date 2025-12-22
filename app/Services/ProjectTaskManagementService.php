<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectActivity;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Services\Concerns\RecordsAuditLogs;
use App\Services\NotificationService;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Arr;

/**
 * ProjectTaskManagementService
 * 
 * Service for managing project tasks with tenant isolation
 * Round 202: Auto-generate ProjectTasks from TaskTemplates when creating projects from templates
 */
class ProjectTaskManagementService
{
    use ServiceBaseTrait, RecordsAuditLogs;

    /**
     * List tasks for a project (scoped by tenant and project)
     * 
     * @param string $tenantId
     * @param string $projectId
     * @param array $filters
     * @param int $perPage
     * @param string $sortBy
     * @param string $sortDirection
     * @return LengthAwarePaginator|Collection
     */
    public function listTasksForProject(
        string $tenantId,
        string $projectId,
        array $filters = [],
        int $perPage = 15,
        string $sortBy = 'sort_order',
        string $sortDirection = 'asc'
    ) {
        $this->validateTenantAccess($tenantId);
        
        // Verify project belongs to tenant
        $project = Project::withoutGlobalScope('tenant')
            ->where('id', $projectId)
            ->where('tenant_id', (string) $tenantId)
            ->first();
        
        if (!$project) {
            abort(404, 'Project not found');
        }
        
        // Query project tasks scoped by tenant and project
        // SoftDeletes trait automatically filters out soft-deleted records
        $query = ProjectTask::withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $tenantId)
            ->where('project_id', $projectId)
            ->whereNull('deleted_at'); // Explicitly exclude soft-deleted records

        // Filter by status
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Filter by is_milestone
        if (isset($filters['is_milestone'])) {
            $query->where('is_milestone', (bool) $filters['is_milestone']);
        }

        // Filter by is_hidden
        if (isset($filters['is_hidden'])) {
            $query->where('is_hidden', (bool) $filters['is_hidden']);
        }

        // Search on name and description
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Default sorting by sort_order, then name
        if ($sortBy === 'sort_order') {
            $query->orderBy('sort_order', $sortDirection)
                  ->orderBy('name', 'asc');
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        if ($perPage > 0) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Create a task for a project
     * 
     * @param string $tenantId
     * @param string $projectId
     * @param array $data
     * @return ProjectTask
     */
    public function createTaskForProject(string $tenantId, string $projectId, array $data): ProjectTask
    {
        $this->validateTenantAccess($tenantId);
        
        // Verify project belongs to tenant
        $project = Project::withoutGlobalScope('tenant')
            ->where('id', $projectId)
            ->where('tenant_id', (string) $tenantId)
            ->first();
        
        if (!$project) {
            abort(404, 'Project not found');
        }
        
        $taskData = [
            'tenant_id' => (string) $tenantId,
            'project_id' => $projectId,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'sort_order' => $data['sort_order'] ?? 0,
            'is_milestone' => $data['is_milestone'] ?? false,
            'status' => $data['status'] ?? ProjectTask::STATUS_PENDING,
            'due_date' => isset($data['due_date']) ? $data['due_date'] : null,
            'duration_days' => $data['duration_days'] ?? 0,
            'progress_percent' => $data['progress_percent'] ?? 0,
            'conditional_tag' => $data['conditional_tag'] ?? null,
            'is_hidden' => $data['is_hidden'] ?? false,
            'template_id' => $data['template_id'] ?? null,
            'template_task_id' => $data['template_task_id'] ?? null,
            'metadata' => $data['metadata'] ?? [],
            'assignee_id' => $data['assignee_id'] ?? null, // Round 213: Task assignment
            'created_by' => Auth::id(),
            'updated_by' => Auth::id(),
        ];

        // Use withoutGlobalScope to ensure tenant_id is set correctly
        $task = ProjectTask::withoutGlobalScope('tenant')->create($taskData);
        
        $this->logCrudOperation('created', $task);
        
        // Round 238: Audit log for task creation
        try {
            $this->audit(
                'task.created',
                $task,
                null,
                [
                    'id' => $task->id,
                    'name' => $task->name,
                    'status' => $task->status,
                    'due_date' => $task->due_date?->toISOString(),
                    'assignee_id' => $task->assignee_id,
                    'is_completed' => $task->is_completed,
                ],
                $projectId
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to create audit log for task creation', [
                'error' => $e->getMessage(),
                'task_id' => $task->id,
            ]);
        }
        
        // Round 252: Notification for task assignment
        if ($task->assignee_id) {
            try {
                $notificationService = app(NotificationService::class);
                $notificationService->notifyUser(
                    userId: (string) $task->assignee_id,
                    module: 'tasks',
                    type: 'task.assigned',
                    title: 'Bạn được giao một công việc mới',
                    message: sprintf("Task \"%s\" đã được giao cho bạn trong dự án \"%s\".", $task->name, $project->name),
                    entityType: 'task',
                    entityId: $task->id,
                    metadata: [
                        'project_id' => $project->id,
                        'project_name' => $project->name,
                    ],
                    tenantId: (string) $tenantId // Pass tenant_id explicitly
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to create notification for task assignment', [
                    'error' => $e->getMessage(),
                    'task_id' => $task->id,
                    'assignee_id' => $task->assignee_id,
                    'tenant_id' => $tenantId,
                ]);
            }
        }
        
        return $task->fresh();
    }

    /**
     * Bulk create tasks for a project from TaskTemplates
     * 
     * Round 202: Auto-generate ProjectTasks from TaskTemplates when creating projects from templates
     * Round 206: Added activity logging
     * 
     * @param string $tenantId
     * @param Project $project
     * @param EloquentCollection $taskTemplates Collection of TaskTemplate models
     * @param \App\Models\Template|null $template Optional template for activity logging
     * @return Collection Collection of created ProjectTask models
     */
    public function bulkCreateTasksForProjectFromTemplates(
        string $tenantId,
        Project $project,
        EloquentCollection $taskTemplates,
        ?\App\Models\Template $template = null
    ): Collection {
        $this->validateTenantAccess($tenantId);
        
        // Ensure project belongs to tenant
        if ((string) $project->tenant_id !== (string) $tenantId) {
            abort(404, 'Project not found');
        }
        
        if ($taskTemplates->isEmpty()) {
            return collect([]);
        }
        
        $createdTasks = collect([]);
        $projectStartDate = $project->start_date ? Carbon::parse($project->start_date) : null;
        
        foreach ($taskTemplates as $taskTemplate) {
            // Skip soft-deleted task templates (should already be filtered, but double-check)
            if ($taskTemplate->trashed()) {
                continue;
            }
            
            // Calculate due_date from project.start_date + default_due_days_offset
            $dueDate = null;
            if ($projectStartDate) {
                // Get default_due_days_offset from metadata or use 0
                $defaultDueDaysOffset = data_get($taskTemplate->metadata, 'default_due_days_offset', null);
                if (!is_null($defaultDueDaysOffset)) {
                    $dueDate = $projectStartDate->copy()->addDays((int) $defaultDueDaysOffset);
                }
            }
            
            // Get additional fields from metadata
            $isMilestone = data_get($taskTemplate->metadata, 'is_milestone', false);
            $defaultStatus = data_get($taskTemplate->metadata, 'default_status', ProjectTask::STATUS_PENDING);
            
            // Map TaskTemplate fields to ProjectTask
            $taskData = [
                'tenant_id' => (string) $tenantId,
                'project_id' => $project->id,
                'template_task_id' => $taskTemplate->id,
                'phase_code' => $taskTemplate->phase_code,
                'phase_label' => $taskTemplate->phase_label,
                'group_label' => $taskTemplate->group_label,
                'name' => $taskTemplate->name,
                'description' => $taskTemplate->description,
                'sort_order' => $taskTemplate->order_index ?? 0,
                'is_milestone' => (bool) $isMilestone,
                'status' => $defaultStatus,
                'due_date' => $dueDate,
                'duration_days' => 0, // Can be calculated later or from metadata
                'progress_percent' => 0,
                'is_hidden' => false,
                'template_id' => $project->template_id, // Link to project template
                'metadata' => [
                    'source' => 'template',
                    'template_id' => $project->template_id,
                    'task_template_id' => $taskTemplate->id,
                ],
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ];
            
            $task = ProjectTask::withoutGlobalScope('tenant')->create($taskData);
            $createdTasks->push($task);
        }
        
        // Log bulk operation
        if ($createdTasks->isNotEmpty()) {
            $this->logCrudOperation('created', $createdTasks->first(), [
                'count' => $createdTasks->count(),
                'project_id' => $project->id,
                'template_id' => $project->template_id,
            ]);
            
            // Round 206: Log activity for tasks generated from template
            if ($template && Auth::id()) {
                ProjectActivity::logProjectTasksGeneratedFromTemplate(
                    $project->id,
                    (string) Auth::id(),
                    $template,
                    $createdTasks
                );
            }
        }
        
        return $createdTasks;
    }

    /**
     * Find task for project or fail
     * 
     * Round 206: Helper method for tenant-aware, project-aware task lookup
     * 
     * @param string $tenantId
     * @param Project $project
     * @param string $taskId
     * @param bool $withTrashed
     * @return ProjectTask
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function findTaskForProjectOrFail(
        string $tenantId,
        Project $project,
        string $taskId,
        bool $withTrashed = false
    ): ProjectTask {
        $this->validateTenantAccess($tenantId);
        
        // Ensure project belongs to tenant
        if ((string) $project->tenant_id !== (string) $tenantId) {
            abort(404, 'Project not found');
        }
        
        $query = ProjectTask::withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $tenantId)
            ->where('project_id', $project->id)
            ->where('id', $taskId);
        
        if ($withTrashed) {
            $query->withTrashed();
        } else {
            $query->whereNull('deleted_at');
        }
        
        return $query->firstOrFail();
    }

    /**
     * Update task for project
     * 
     * Round 206: Update task fields (name, description, status, due_date, sort_order, is_milestone)
     * Round 213: Added assignee_id for task assignment
     * 
     * @param string $tenantId
     * @param Project $project
     * @param string $taskId
     * @param array $data
     * @return ProjectTask
     */
    public function updateTaskForProject(
        string $tenantId,
        Project $project,
        string $taskId,
        array $data
    ): ProjectTask {
        $task = $this->findTaskForProjectOrFail($tenantId, $project, $taskId, false);
        
        // Round 238: Capture before state for audit logs
        $beforeState = [
            'status' => $task->status,
            'is_completed' => $task->is_completed,
            'completed_at' => $task->completed_at?->toISOString(),
            'due_date' => $task->due_date?->toISOString(),
            'assignee_id' => $task->assignee_id,
        ];
        
        // Round 214: Capture old assignee before update
        $oldAssignee = null;
        $oldAssigneeId = $task->assignee_id;
        if ($oldAssigneeId) {
            // Query user directly to avoid relationship loading issues
            // Filter by tenant_id explicitly for safety
            $oldAssignee = User::where('id', $oldAssigneeId)
                ->where('tenant_id', $tenantId)
                ->first();
        }
        
        // Only allow specific fields to be updated
        $allowed = [
            'name',
            'description',
            'status',
            'due_date',
            'sort_order',
            'is_milestone',
            'phase_code',
            'phase_label',
            'group_label',
            'assignee_id', // Round 213: Task assignment
        ];
        
        $updateData = Arr::only($data, $allowed);
        
        // Filter out null values to avoid overwriting with null unless explicitly set
        // Exception: assignee_id can be explicitly set to null to unassign
        $updateData = array_filter($updateData, function ($value, $key) {
            if ($key === 'assignee_id') {
                return true; // Allow null for unassignment
            }
            return $value !== null;
        }, ARRAY_FILTER_USE_BOTH);
        
        if (!empty($updateData)) {
            $updateData['updated_by'] = Auth::id();
            $task->update($updateData);
        }
        
        // Round 214: Capture new assignee after update and log assignment change if different
        $task->refresh();
        $newAssignee = null;
        $newAssigneeId = $task->assignee_id;
        if ($newAssigneeId) {
            // Query user directly to avoid relationship loading issues
            // Filter by tenant_id explicitly for safety
            $newAssignee = User::where('id', $newAssigneeId)
                ->where('tenant_id', $tenantId)
                ->first();
        }
        
        // Round 238: Audit logs for specific changes
        try {
            // Status change audit log
            if (isset($updateData['status']) && $updateData['status'] !== $beforeState['status']) {
                $this->audit(
                    'task.status_changed',
                    $task,
                    [
                        'status' => $beforeState['status'],
                        'is_completed' => $beforeState['is_completed'],
                        'completed_at' => $beforeState['completed_at'],
                    ],
                    [
                        'status' => $task->status,
                        'is_completed' => $task->is_completed,
                        'completed_at' => $task->completed_at?->toISOString(),
                    ],
                    $project->id
                );
            }
            
            // Due date change audit log
            if (isset($updateData['due_date']) && $updateData['due_date'] !== $beforeState['due_date']) {
                $this->audit(
                    'task.due_date_changed',
                    $task,
                    ['due_date' => $beforeState['due_date']],
                    ['due_date' => $task->due_date?->toISOString()],
                    $project->id
                );
            }
            
            // Assignee change audit log
            if ((string) $oldAssigneeId !== (string) $newAssigneeId) {
                $this->audit(
                    'task.assignee_changed',
                    $task,
                    ['assignee_id' => $oldAssigneeId],
                    ['assignee_id' => $newAssigneeId],
                    $project->id
                );
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to create audit log for task update', [
                'error' => $e->getMessage(),
                'task_id' => $task->id,
            ]);
        }
        
        // Log assignment change if assignee_id actually changed
        // Compare as strings to handle type differences
        if ((string) $oldAssigneeId !== (string) $newAssigneeId) {
            ProjectActivity::logProjectTaskAssignmentChange(
                $tenantId,
                $project,
                $task,
                $oldAssignee,
                $newAssignee
            );
            
            // Round 252: Notification for assignee change (notify new assignee)
            if ($newAssigneeId) {
                try {
                    $notificationService = app(NotificationService::class);
                    $notificationService->notifyUser(
                        userId: (string) $newAssigneeId,
                        module: 'tasks',
                        type: 'task.assignee_changed',
                        title: 'Bạn được giao một công việc',
                        message: sprintf("Task \"%s\" vừa được giao cho bạn trong dự án \"%s\".", $task->name, $project->name),
                        entityType: 'task',
                        entityId: $task->id,
                        metadata: [
                            'project_id' => $project->id,
                            'project_name' => $project->name,
                        ],
                        tenantId: (string) $tenantId // Pass tenant_id explicitly
                    );
                } catch (\Exception $e) {
                    \Log::warning('Failed to create notification for assignee change', [
                        'error' => $e->getMessage(),
                        'task_id' => $task->id,
                        'new_assignee_id' => $newAssigneeId,
                        'tenant_id' => $tenantId,
                    ]);
                }
            }
        }
        
        return $task->fresh();
    }

    /**
     * Mark task as completed for project
     * 
     * Round 206: Mark task as completed with timestamp
     * 
     * @param string $tenantId
     * @param Project $project
     * @param string $taskId
     * @return ProjectTask
     */
    public function markTaskCompletedForProject(
        string $tenantId,
        Project $project,
        string $taskId
    ): ProjectTask {
        $task = $this->findTaskForProjectOrFail($tenantId, $project, $taskId, false);
        
        $now = now();
        
        $task->is_completed = true;
        $task->completed_at = $now;
        
        // Optional: auto-set status to 'completed' if not already set
        if (!$task->status || $task->status === ProjectTask::STATUS_PENDING) {
            $task->status = ProjectTask::STATUS_COMPLETED;
        }
        
        $task->updated_by = Auth::id();
        $task->save();
        
        return $task->fresh();
    }

    /**
     * Mark task as incomplete for project
     * 
     * Round 206: Mark task as incomplete, clear completion timestamp
     * 
     * @param string $tenantId
     * @param Project $project
     * @param string $taskId
     * @return ProjectTask
     */
    public function markTaskIncompleteForProject(
        string $tenantId,
        Project $project,
        string $taskId
    ): ProjectTask {
        $task = $this->findTaskForProjectOrFail($tenantId, $project, $taskId, false);
        
        $task->is_completed = false;
        $task->completed_at = null;
        
        $task->updated_by = Auth::id();
        $task->save();
        
        return $task->fresh();
    }

    /**
     * Reorder tasks for project
     * 
     * Round 210: Reorder tasks within a project by updating sort_order
     * Round 211: Added activity logging for task reordering
     * 
     * @param string $tenantId
     * @param Project $project
     * @param array $taskIdsInOrder Array of task IDs in the desired order
     * @return void
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function reorderTasksForProject(
        string $tenantId,
        Project $project,
        array $taskIdsInOrder
    ): void {
        $this->validateTenantAccess($tenantId);
        
        // Ensure project belongs to tenant
        if ((string) $project->tenant_id !== (string) $tenantId) {
            abort(404, 'Project not found');
        }
        
        if (empty($taskIdsInOrder)) {
            return; // Nothing to reorder
        }
        
        \Illuminate\Support\Facades\DB::transaction(function () use ($tenantId, $project, $taskIdsInOrder) {
            // Fetch all tasks matching the IDs, tenant, and project
            $tasks = ProjectTask::withoutGlobalScope('tenant')
                ->where('tenant_id', (string) $tenantId)
                ->where('project_id', $project->id)
                ->whereIn('id', $taskIdsInOrder)
                ->whereNull('deleted_at')
                ->get();
            
            // Verify we found all tasks
            if ($tasks->count() !== count($taskIdsInOrder)) {
                $foundIds = $tasks->pluck('id')->toArray();
                $missingIds = array_diff($taskIdsInOrder, $foundIds);
                abort(404, 'One or more tasks not found in this project: ' . implode(', ', $missingIds));
            }
            
            // Round 211: Capture phase info and before state for activity logging
            $firstTask = $tasks->first();
            $phaseCode = $firstTask?->phase_code;
            $phaseLabel = $firstTask?->phase_label;
            
            // Build taskIdsBefore: IDs sorted by current sort_order (ascending)
            $taskIdsBefore = $tasks
                ->sortBy('sort_order')
                ->pluck('id')
                ->values()
                ->toArray();
            
            // Build taskIdsAfter: normalize to zero-based array
            $taskIdsAfter = array_values($taskIdsInOrder);
            
            // Assign new sort_order values based on the order in taskIdsInOrder
            // Use step of 10 (10, 20, 30, ...) for flexibility
            $sortOrder = 10;
            foreach ($taskIdsInOrder as $taskId) {
                $task = $tasks->firstWhere('id', $taskId);
                if ($task) {
                    $task->sort_order = $sortOrder;
                    $task->updated_by = Auth::id();
                    $task->save();
                    $sortOrder += 10;
                }
            }
            
            // Round 211: Log activity after successful reorder
            ProjectActivity::logProjectTasksReordered(
                $tenantId,
                $project,
                $phaseCode,
                $phaseLabel,
                $taskIdsBefore,
                $taskIdsAfter
            );
        });
    }

    /**
     * List tasks assigned to a user
     * 
     * Round 213: List tasks assigned to the current user across projects
     * Round 217: Added range filter support (today, next_7_days, overdue, all)
     * 
     * @param string $tenantId
     * @param string $userId
     * @param array $filters Optional filters:
     *   - status: 'open' (default), 'completed', or 'all'
     *   - range: 'today', 'next_7_days', 'overdue', or 'all' (default)
     * @return Collection
     */
    public function listTasksAssignedToUser(
        string $tenantId,
        string $userId,
        array $filters = []
    ): Collection {
        $this->validateTenantAccess($tenantId);
        
        $query = ProjectTask::where('tenant_id', $tenantId)
            ->where('assignee_id', $userId)
            ->with(['project:id,name,code,status'])
            ->orderBy('due_date', 'asc')
            ->orderBy('sort_order', 'asc');
        
        // Apply status filter
        $statusFilter = $filters['status'] ?? 'open';
        if ($statusFilter === 'open') {
            $query->where('is_completed', false);
        } elseif ($statusFilter === 'completed') {
            $query->where('is_completed', true);
        }
        // 'all' means no filter
        
        // Apply range filter (Round 217)
        $rangeFilter = $filters['range'] ?? 'all';
        $today = Carbon::today();
        
        if ($rangeFilter === 'today') {
            $query->whereDate('due_date', $today);
        } elseif ($rangeFilter === 'next_7_days') {
            $endDate = $today->copy()->addDays(7);
            $query->whereBetween('due_date', [$today, $endDate]);
        } elseif ($rangeFilter === 'overdue') {
            $query->where('due_date', '<', $today)
                  ->where('is_completed', false);
        }
        // 'all' means no date filter
        
        return $query->get();
    }
}

