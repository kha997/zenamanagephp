<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Project;
use App\Models\TaskTemplate;
use App\Models\User;
use App\Models\Document;
use App\Services\AuditLogService;
use App\Services\Concerns\RecordsAuditLogs;
use App\Services\ProjectTaskManagementService;
use App\Traits\ServiceBaseTrait;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ProjectManagementService
 * 
 * Unified service for all project management operations
 * Replaces multiple project controllers and services
 */
class ProjectManagementService
{
    use ServiceBaseTrait, RecordsAuditLogs;

    protected string $modelClass = Project::class;

    /**
     * Large file threshold in bytes (5MB)
     * Files larger than this will use signed URL instead of direct stream
     */
    public const LARGE_FILE_THRESHOLD_BYTES = 5242880; // 5MB

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

        if (isset($filters['client_id']) && $filters['client_id']) {
            $query->where('client_id', $filters['client_id']);
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
        
        // Debug: log query parameters
        \Log::debug('[ProjectManagementService] getProjectById', [
            'project_id' => $id,
            'tenant_id' => $tenantId,
            'id_type' => gettype($id),
        ]);
        
        $query = Project::with(['owner', 'tasks'])
            ->where('id', $id);
        
        // Filter by tenant_id if provided (explicit check to handle empty strings)
        // Convert tenant_id to string for consistent comparison
        if ($tenantId !== null && $tenantId !== '') {
            $query->where('tenant_id', (string) $tenantId);
        }
        
        $project = $query->first();
        
        if (!$project) {
            // Debug: check if project exists without tenant filter
            $projectWithoutTenant = Project::where('id', $id)->first();
            \Log::warning('[ProjectManagementService] Project not found', [
                'project_id' => $id,
                'tenant_id' => $tenantId,
                'project_exists' => $projectWithoutTenant ? true : false,
                'project_tenant_id' => $projectWithoutTenant?->tenant_id,
            ]);
        }
        
        return $project;
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
     * Create project from template
     * 
     * @param string|int $tenantId
     * @param \App\Models\Template $template
     * @param array $data Project data (will override template defaults)
     * @return Project
     */
    public function createProjectFromTemplate(string|int $tenantId, \App\Models\Template $template, array $data): Project
    {
        $this->validateTenantAccess($tenantId);
        
        // Ensure template belongs to the same tenant
        if ((string) $template->tenant_id !== (string) $tenantId) {
            abort(404, 'Template not found');
        }
        
        // Ensure template is for project type
        if ($template->category !== 'project') {
            abort(422, 'Template is not a project template');
        }
        
        // Merge template defaults with request data (request data overrides)
        // Template metadata structure:
        // {
        //   "project_defaults": {
        //     "status": "planning",
        //     "priority": "normal",
        //     "start_date": "2025-01-01",
        //     // ... other project fields
        //   }
        // }
        // Request data always overrides template defaults when both are present
        $templateDefaults = data_get($template->metadata, 'project_defaults', []);
        $mergedData = array_merge($templateDefaults, $data);
        
        // Set template_id to remember the relationship
        $mergedData['template_id'] = $template->id;
        
        // Use template name as default if name not provided
        if (empty($mergedData['name']) && !empty($template->name)) {
            $mergedData['name'] = $template->name;
        }
        
        // Create project using existing method
        $project = $this->createProject($mergedData, $tenantId);
        
        // Ensure template_id is set (in case createProject didn't preserve it)
        if ($project->template_id !== $template->id) {
            $project->template_id = $template->id;
            $project->save();
        }
        
        // Round 202: Auto-generate ProjectTasks from TaskTemplates
        $this->generateProjectTasksFromTemplate($tenantId, $project, $template);
        
        return $project->load(['owner', 'template']);
    }

    /**
     * Generate ProjectTasks from TaskTemplates for a project
     * 
     * Round 202: Auto-generate tasks when creating project from template
     * 
     * @param string|int $tenantId
     * @param Project $project
     * @param \App\Models\Template $template
     * @return void
     */
    protected function generateProjectTasksFromTemplate(
        string|int $tenantId,
        Project $project,
        \App\Models\Template $template
    ): void {
        // Fetch active (non-soft-deleted) TaskTemplates for this template
        $taskTemplates = TaskTemplate::withoutGlobalScope('tenant')
            ->where('tenant_id', (string) $tenantId)
            ->where('template_id', $template->id)
            ->whereNull('deleted_at') // Only active task templates
            ->orderBy('order_index', 'asc')
            ->orderBy('name', 'asc')
            ->get();
        
        if ($taskTemplates->isEmpty()) {
            // No task templates to generate, skip
            return;
        }
        
        // Use ProjectTaskManagementService to bulk create tasks
        $projectTaskService = app(ProjectTaskManagementService::class);
        $projectTaskService->bulkCreateTasksForProjectFromTemplates(
            (string) $tenantId,
            $project,
            $taskTemplates,
            $template // Round 206: Pass template for activity logging
        );
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
     * Get project timeline
     * 
     * Returns timeline of project milestones, tasks, and events
     * 
     * @param string $projectId Project ID
     * @param string|int|null $tenantId Tenant ID for multi-tenant isolation
     * @return array|null Timeline data with project_id and timeline items
     */
    public function getProjectTimeline(string $projectId, string|int|null $tenantId = null): ?array
    {
        $this->validateTenantAccess($tenantId);
        
        // Get project with tenant validation
        $project = Project::query()
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->find($projectId);
            
        if (!$project) {
            return null;
        }
        
        // Get timeline items from various sources
        $timelineItems = collect();
        
        // Add project milestones (start_date, end_date)
        if ($project->start_date) {
            $timelineItems->push([
                'id' => 'project_start_' . $project->id,
                'title' => 'Project Start',
                'date' => $project->start_date,
                'type' => 'milestone',
                'status' => $project->start_date <= now() ? 'completed' : 'pending',
                'description' => 'Project start date'
            ]);
        }
        
        if ($project->end_date) {
            $timelineItems->push([
                'id' => 'project_end_' . $project->id,
                'title' => 'Project End',
                'date' => $project->end_date,
                'type' => 'milestone',
                'status' => $project->end_date <= now() ? ($project->status === 'completed' ? 'completed' : 'overdue') : 'pending',
                'description' => 'Project end date'
            ]);
        }
        
        // Add project creation event
        $timelineItems->push([
            'id' => 'project_created_' . $project->id,
            'title' => 'Project Created',
            'date' => $project->created_at,
            'type' => 'event',
            'status' => 'completed',
            'description' => 'Project was created'
        ]);
        
        // Add project status changes
        if ($project->updated_at && $project->updated_at != $project->created_at) {
            $timelineItems->push([
                'id' => 'project_updated_' . $project->id,
                'title' => 'Project Updated',
                'date' => $project->updated_at,
                'type' => 'event',
                'status' => 'completed',
                'description' => 'Project was last updated'
            ]);
        }
        
        // Get tasks for this project
        $tasks = \App\Models\Task::query()
            ->where('project_id', $projectId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->orderBy('due_date', 'asc')
            ->get();
            
        foreach ($tasks as $task) {
            $timelineItems->push([
                'id' => 'task_' . $task->id,
                'title' => $task->title,
                'date' => $task->due_date ?? $task->created_at,
                'type' => 'task',
                'status' => $task->status === 'completed' ? 'completed' : 
                           ($task->due_date && $task->due_date < now() ? 'overdue' : 'pending'),
                'description' => $task->description
            ]);
        }
        
        // Sort timeline items by date
        $timelineItems = $timelineItems->sortBy('date')->values();
        
        return [
            'project_id' => $projectId,
            'project_name' => $project->name,
            'timeline' => $timelineItems->toArray()
        ];
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

    /**
     * Bulk archive projects
     */
    public function bulkArchiveProjects(array $projectIds, ?int $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        $projects = Project::whereIn('id', $projectIds)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->get();
        
        if ($projects->isEmpty()) {
            abort(404, 'No projects found');
        }
        
        $archivedCount = 0;
        foreach ($projects as $project) {
            $project->update(['status' => 'archived']);
            $this->logCrudOperation('bulk_archived', $project);
            $archivedCount++;
        }
        
        return [
            'archived_count' => $archivedCount,
            'project_ids' => $projectIds
        ];
    }

    /**
     * Bulk export projects
     */
    public function bulkExportProjects(array $projectIds, ?int $tenantId = null): array
    {
        $this->validateTenantAccess($tenantId);
        
        $projects = Project::whereIn('id', $projectIds)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId))
            ->with(['owner', 'client'])
            ->get();
        
        if ($projects->isEmpty()) {
            abort(404, 'No projects found');
        }
        
        // For now, return project data for export
        // In a real implementation, this would generate a file
        $exportData = $projects->map(function($project) {
            return [
                'id' => $project->id,
                'name' => $project->name,
                'description' => $project->description,
                'status' => $project->status,
                'priority' => $project->priority,
                'progress' => $project->progress,
                'start_date' => $project->start_date,
                'end_date' => $project->end_date,
                'budget_total' => $project->budget_total,
                'owner' => $project->owner->name ?? 'N/A',
                'client' => $project->client?->name ?? 'N/A'
            ];
        });
        
        $this->logCrudOperation('bulk_exported', null, [
            'exported_count' => $projects->count(),
            'project_ids' => $projectIds
        ]);
        
        return [
            'exported_count' => $projects->count(),
            'data' => $exportData,
            'project_ids' => $projectIds
        ];
    }

    /**
     * Get project documents
     * 
     * Returns all documents associated with a project, with tenant isolation
     * 
     * @param string $projectId Project ID
     * @param string|int|null $tenantId Tenant ID for multi-tenant isolation
     * @param array $filters Optional filters (category, status, search)
     * @return array Documents data with consistent structure
     */
    public function getProjectDocuments(string $projectId, string|int|null $tenantId = null, array $filters = []): array
    {
        $this->validateTenantAccess($tenantId);
        
        // First, validate project exists and belongs to tenant
        $project = $this->getProjectById($projectId, $tenantId);
        
        if (!$project) {
            return [];
        }
        
        // Query documents with tenant isolation
        $query = \App\Models\Document::query()
            ->where('project_id', $projectId)
            ->when($tenantId, fn($q) => $q->where('tenant_id', $tenantId));
        
        // Apply filters
        if (isset($filters['category']) && $filters['category']) {
            $query->where('category', $filters['category']);
        }
        
        if (isset($filters['status']) && $filters['status']) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['search']) && $filters['search']) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        $documents = $query->with(['uploader:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Return structured data
        return $documents->map(function($document) {
            return [
                'id' => $document->id,
                'name' => $document->name,
                'title' => $document->name, // For backward compatibility
                'description' => $document->description,
                'category' => $document->category,
                'status' => $document->status,
                'file_type' => $document->file_type,
                'mime_type' => $document->mime_type,
                'file_size' => $document->file_size,
                'file_path' => $document->file_path,
                'uploaded_by' => $document->uploader ? [
                    'id' => $document->uploader->id,
                    'name' => $document->uploader->name,
                    'email' => $document->uploader->email,
                ] : null,
                'created_at' => $document->created_at?->toISOString(),
                'updated_at' => $document->updated_at?->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Create project document
     * 
     * Creates a new document for a project with tenant isolation
     * 
     * @param string $projectId Project ID
     * @param string|int|null $tenantId Tenant ID for multi-tenant isolation
     * @param UploadedFile $file Uploaded file
     * @param array $payload Optional metadata (name, description, category, status)
     * @return Document Created document instance
     * @throws \Exception If project not found or tenant mismatch
     */
    public function createProjectDocument(
        string $projectId,
        string|int|null $tenantId,
        UploadedFile $file,
        array $payload = []
    ): Document {
        $this->validateTenantAccess($tenantId);
        
        // Validate project exists and belongs to tenant
        $project = $this->getProjectById($projectId, $tenantId);
        
        if (!$project) {
            throw new \Exception('Project not found', 404);
        }
        
        // Get current user ID
        $userId = Auth::id();
        if (!$userId) {
            throw new \Exception('User not authenticated', 401);
        }
        
        // Calculate file hash before storing (file will be moved after store)
        $fileHash = hash_file('sha256', $file->getRealPath());
        
        // Prepare file storage path
        $storagePath = "projects/{$projectId}/documents";
        
        // Store file
        $filePath = $file->store($storagePath, 'local');
        
        // Determine document name (use provided name or original filename)
        $documentName = $payload['name'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Create document record
        $documentData = [
            'tenant_id' => $tenantId,
            'project_id' => $projectId,
            'uploaded_by' => $userId,
            'name' => $documentName,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'file_type' => $file->getClientOriginalExtension(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'file_hash' => $fileHash,
            'description' => $payload['description'] ?? null,
            'category' => $payload['category'] ?? 'general',
            'status' => $payload['status'] ?? 'active',
        ];
        
        try {
            $document = Document::create($documentData);
        } catch (\Exception $e) {
            // If document creation fails, clean up stored file
            if (isset($filePath) && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }
            throw new \Exception('Failed to create document: ' . $e->getMessage(), 0, $e);
        }
        
        // Load uploader relationship for response
        $document->load('uploader:id,name,email');
        
        // Log activity for document upload
        try {
            \App\Models\ProjectActivity::logDocumentUploaded($document, $userId);
        } catch (\Exception $e) {
            // Logging failure should not break the operation
            \Log::warning('Failed to log document upload activity', [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'project_id' => $projectId
            ]);
        }
        
        return $document;
    }

    /**
     * Update project document metadata
     * 
     * Updates metadata fields (name, description, category, status) for a document
     * 
     * @param string $projectId Project ID
     * @param string|int|null $tenantId Tenant ID for multi-tenant isolation
     * @param string $documentId Document ID
     * @param array $data Update data (name, description, category, status)
     * @return Document Updated document instance
     * @throws \Exception If document not found or tenant mismatch
     */
    public function updateProjectDocument(
        string $projectId,
        string|int|null $tenantId,
        string $documentId,
        array $data
    ): Document {
        $this->validateTenantAccess($tenantId);
        
        // Find document with tenant and project validation
        $document = $this->findProjectDocumentForTenant($projectId, $tenantId, $documentId);
        
        if (!$document) {
            throw new \Exception('Document not found', 404);
        }
        
        // Only update allowed fields
        $allowedFields = ['name', 'description', 'category', 'status'];
        $updateData = [];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }
        
        // Get current user ID for activity logging
        $userId = \Illuminate\Support\Facades\Auth::id();
        if (!$userId) {
            throw new \Exception('User not authenticated', 401);
        }
        
        // Update document
        $document->update($updateData);
        
        // Load uploader relationship for response
        $document->load('uploader:id,name,email');
        
        // Log activity for document update
        try {
            \App\Models\ProjectActivity::logDocumentUpdated($document->fresh(), $userId, $updateData);
        } catch (\Exception $e) {
            // Logging failure should not break the operation
            \Log::warning('Failed to log document update activity', [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'project_id' => $projectId
            ]);
        }
        
        return $document->fresh();
    }

    /**
     * Delete project document
     * 
     * Deletes a document record and its associated file from storage
     * 
     * @param string $projectId Project ID
     * @param string|int|null $tenantId Tenant ID for multi-tenant isolation
     * @param string $documentId Document ID
     * @return bool True if deletion was successful
     * @throws \Exception If document not found or tenant mismatch
     */
    public function deleteProjectDocument(
        string $projectId,
        string|int|null $tenantId,
        string $documentId
    ): bool {
        $this->validateTenantAccess($tenantId);
        
        // Find document with tenant and project validation
        $document = $this->findProjectDocumentForTenant($projectId, $tenantId, $documentId);
        
        if (!$document) {
            throw new \Exception('Document not found', 404);
        }
        
        // Store file path and document name before deletion
        $filePath = $document->file_path;
        $documentName = $document->name ?? $document->original_name ?? 'Unknown';
        
        // Get current user ID for activity logging
        $userId = \Illuminate\Support\Facades\Auth::id();
        if (!$userId) {
            throw new \Exception('User not authenticated', 401);
        }
        
        // Log activity before deletion (so we have document data)
        try {
            \App\Models\ProjectActivity::logDocumentDeleted($document, $userId);
        } catch (\Exception $e) {
            // Logging failure should not break the operation
            \Log::warning('Failed to log document deletion activity', [
                'error' => $e->getMessage(),
                'document_id' => $documentId,
                'project_id' => $projectId
            ]);
        }
        
        // Delete file from storage (if it exists)
        if ($filePath) {
            try {
                $disk = Storage::disk('local');
                if ($disk->exists($filePath)) {
                    $disk->delete($filePath);
                }
            } catch (\Exception $e) {
                // Log warning but continue with record deletion
                \Log::warning('Failed to delete document file', [
                    'document_id' => $documentId,
                    'file_path' => $filePath,
                    'error' => $e->getMessage(),
                ]);
            }
        }
        
        // Delete document record
        // Note: Document model does not use SoftDeletes, so this is a hard delete
        $deleted = $document->delete();
        
        return $deleted;
    }

    /**
     * Get project history
     * 
     * Returns activity/history log for a project, with tenant isolation
     * 
     * Round 231: Cost Workflow Timeline - Added entity_id filter support
     * 
     * @param string $projectId Project ID
     * @param string|int|null $tenantId Tenant ID for multi-tenant isolation
     * @param array $filters Optional filters (action, entity_type, entity_id, limit)
     * @return array History/activity data with consistent structure
     */
    public function getProjectHistory(string $projectId, string|int|null $tenantId = null, array $filters = []): array
    {
        $this->validateTenantAccess($tenantId);
        
        // First, validate project exists and belongs to tenant
        $project = $this->getProjectById($projectId, $tenantId);
        
        if (!$project) {
            return [];
        }
        
        // Query project activities
        // Note: ProjectActivity doesn't have tenant_id directly, but we validate via project
        $query = \App\Models\ProjectActivity::query()
            ->where('project_id', $projectId);
        
        // Apply filters
        if (isset($filters['action']) && $filters['action']) {
            $query->where('action', $filters['action']);
        }
        
        if (isset($filters['entity_type']) && $filters['entity_type']) {
            $query->where('entity_type', $filters['entity_type']);
        }
        
        // Round 231: Support filtering by entity_id for cost workflow timeline
        if (isset($filters['entity_id']) && $filters['entity_id']) {
            $query->where('entity_id', $filters['entity_id']);
        }
        
        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 50;
        $limit = min($limit, 100); // Cap at 100 for performance
        
        $activities = $query->with(['user:id,name,email'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
        
        // Return structured data
        return $activities->map(function($activity) {
            return [
                'id' => $activity->id,
                'type' => $activity->action,
                'action' => $activity->action,
                'action_label' => $activity->action_label,
                'entity_type' => $activity->entity_type,
                'entity_id' => $activity->entity_id,
                'message' => $activity->description,
                'description' => $activity->description,
                'metadata' => $activity->metadata,
                'user' => $activity->user ? [
                    'id' => $activity->user->id,
                    'name' => $activity->user->name,
                    'email' => $activity->user->email,
                ] : null,
                'created_at' => $activity->created_at?->toISOString(),
                'time_ago' => $activity->time_ago,
            ];
        })->toArray();
    }

    /**
     * Find project document for tenant
     * 
     * Validates that document exists, belongs to project, and belongs to tenant
     * 
     * @param string $projectId Project ID
     * @param string|int|null $tenantId Tenant ID for multi-tenant isolation
     * @param string $documentId Document ID
     * @return Document|null Document instance or null if not found
     * @throws \Exception If project not found or tenant mismatch
     */
    public function findProjectDocumentForTenant(
        string $projectId,
        string|int|null $tenantId,
        string $documentId
    ): ?Document {
        // Ensure tenant_id is always treated as a non-empty ULID string
        $tenantId = $tenantId !== null && $tenantId !== '' ? (string) $tenantId : null;
        
        $this->validateTenantAccess($tenantId);
        
        // Validate project exists and belongs to tenant
        $project = $this->getProjectById($projectId, $tenantId);
        
        if (!$project) {
            return null;
        }
        
        // Find document with tenant and project validation
        // Must filter by BOTH project_id AND tenant_id for security
        $query = Document::query()
            ->where('id', $documentId)
            ->where('project_id', $project->id); // Use project model ID for consistency
        
        // Always filter by tenant_id if provided (required for multi-tenant isolation)
        if ($tenantId !== null && $tenantId !== '') {
            $query->where('tenant_id', $tenantId);
        }
        
        $document = $query->first();
        
        return $document;
    }

    /**
     * Stream document file
     * 
     * Returns a response that streams the file for download
     * 
     * @param Document $document Document instance
     * @return StreamedResponse|BinaryFileResponse File download response
     * @throws \Exception If file not found on disk
     */
    public function streamDocumentFile(Document $document): StreamedResponse|BinaryFileResponse
    {
        $disk = Storage::disk('local');
        
        // Check if file exists
        if (!$disk->exists($document->file_path)) {
            throw new \Exception('File not found', 404);
        }
        
        // Get file path
        $fullPath = $disk->path($document->file_path);
        
        // Use Storage::download for proper headers
        return $disk->download(
            $document->file_path,
            $document->original_name ?? $document->name,
            [
                'Content-Type' => $document->mime_type ?? 'application/octet-stream',
            ]
        );
    }

    /**
     * Create signed document download URL
     * 
     * Generates a temporary signed route URL for document download
     * 
     * @param Document $document Document instance
     * @param \DateTimeInterface $expiresAt Expiration time
     * @return string Signed URL
     */
    public function createSignedDocumentDownloadUrl(
        Document $document,
        \DateTimeInterface $expiresAt
    ): string {
        return URL::temporarySignedRoute(
            'app.projects.documents.signed-download',
            $expiresAt,
            ['doc' => $document->getKey()] // Route parameter is 'doc', not 'document'
        );
    }

    /**
     * Stream document version file
     * 
     * Returns a response that streams the version file for download
     * 
     * @param \App\Models\ProjectDocumentVersion $version Version instance
     * @return StreamedResponse|BinaryFileResponse File download response
     * @throws \Exception If file not found on disk
     */
    public function streamVersionFile(\App\Models\ProjectDocumentVersion $version): StreamedResponse|BinaryFileResponse
    {
        $disk = Storage::disk('local');
        
        // Check if file exists
        if (!$disk->exists($version->file_path)) {
            throw new \Exception('File not found', 404);
        }
        
        // Use Storage::download for proper headers
        return $disk->download(
            $version->file_path,
            $version->original_name ?? $version->name ?? 'version-' . $version->version_number,
            [
                'Content-Type' => $version->mime_type ?? 'application/octet-stream',
            ]
        );
    }

    /**
     * Create a document version snapshot
     * 
     * Creates a version entry from the current document state before updating
     * 
     * Round 190: Added note parameter for version notes
     * 
     * @param Document $document Document instance to snapshot
     * @param string|null $userId User ID creating the version (defaults to document's uploaded_by)
     * @param string|null $note Optional note for this version snapshot. If null, auto-generates a note.
     * @return \App\Models\ProjectDocumentVersion Created version instance
     */
    protected function createDocumentVersionSnapshot(Document $document, ?string $userId = null, ?string $note = null): \App\Models\ProjectDocumentVersion
    {
        // Determine next version number
        $maxVersion = \App\Models\ProjectDocumentVersion::where('document_id', $document->id)
            ->max('version_number') ?? 0;
        $nextVersionNumber = $maxVersion + 1;

        // Use provided userId or fall back to document's uploaded_by (cast to string)
        $uploadedBy = $userId ? (string) $userId : (string) $document->uploaded_by;

        // Auto-generate note if not provided
        if ($note === null) {
            $note = 'Automatic snapshot';
        }

        // Create version snapshot
        $versionData = [
            'document_id' => $document->id,
            'project_id' => $document->project_id,
            'tenant_id' => $document->tenant_id,
            'version_number' => $nextVersionNumber,
            'name' => $document->name,
            'original_name' => $document->original_name,
            'file_path' => $document->file_path,
            'file_type' => $document->file_type,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'file_hash' => $document->file_hash,
            'note' => $note,
            'uploaded_by' => $uploadedBy,
        ];

        return \App\Models\ProjectDocumentVersion::create($versionData);
    }

    /**
     * Upload a new version for an existing document
     * 
     * Creates a snapshot of the current document state, then updates the document with the new file
     * 
     * @param string $projectId Project ID
     * @param string|int|null $tenantId Tenant ID for multi-tenant isolation
     * @param string $documentId Document ID
     * @param UploadedFile $file New file to upload
     * @param array $payload Optional metadata (name, description, category, status)
     * @return Document Updated document instance
     * @throws \Exception If document not found or tenant mismatch
     */
    public function uploadDocumentNewVersion(
        string $projectId,
        string|int|null $tenantId,
        string $documentId,
        UploadedFile $file,
        array $payload = []
    ): Document {
        $this->validateTenantAccess($tenantId);
        
        // Find document with tenant and project validation
        $document = $this->findProjectDocumentForTenant($projectId, $tenantId, $documentId);
        
        if (!$document) {
            throw new \Exception('Document not found', 404);
        }
        
        // Get current user ID
        $userId = Auth::id();
        if (!$userId) {
            throw new \Exception('User not authenticated', 401);
        }
        
        // Extract version note from payload description (Round 190)
        $versionNote = $payload['description'] ?? null;
        
        // Create snapshot of current document state with auto-generated note
        $this->createDocumentVersionSnapshot($document, (string) $userId, 'Snapshot before new version upload');
        
        // Calculate file hash before storing
        $fileHash = hash_file('sha256', $file->getRealPath());
        
        // Prepare file storage path (same pattern as createProjectDocument)
        $storagePath = "projects/{$projectId}/documents";
        
        // Store new file
        $filePath = $file->store($storagePath, 'local');
        
        // Determine document name (use provided name or keep existing, or use filename)
        $documentName = $payload['name'] ?? $document->name ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        
        // Update document record with new file information
        $updateData = [
            'name' => $documentName,
            'original_name' => $file->getClientOriginalName(),
            'file_path' => $filePath,
            'file_type' => $file->getClientOriginalExtension(),
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'file_hash' => $fileHash,
        ];
        
        // Optionally update metadata fields if provided
        if (isset($payload['description'])) {
            $updateData['description'] = $payload['description'];
        }
        if (isset($payload['category'])) {
            $updateData['category'] = $payload['category'];
        }
        if (isset($payload['status'])) {
            $updateData['status'] = $payload['status'];
        }
        
        try {
            $document->update($updateData);
        } catch (\Exception $e) {
            // If update fails, clean up stored file
            if (isset($filePath) && Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
            }
            throw new \Exception('Failed to update document: ' . $e->getMessage(), 0, $e);
        }
        
        // Create version entry for the new file with user's note (Round 190)
        // This ensures the new version appears in version history with the user's note
        $document->refresh();
        $maxVersion = \App\Models\ProjectDocumentVersion::where('document_id', $document->id)
            ->max('version_number') ?? 0;
        $newVersionNumber = $maxVersion + 1;
        
        $newVersion = \App\Models\ProjectDocumentVersion::create([
            'document_id' => $document->id,
            'project_id' => $document->project_id,
            'tenant_id' => $document->tenant_id,
            'version_number' => $newVersionNumber,
            'name' => $document->name,
            'original_name' => $document->original_name,
            'file_path' => $document->file_path,
            'file_type' => $document->file_type,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'file_hash' => $document->file_hash,
            'note' => $versionNote, // User's note for the new version
            'uploaded_by' => (string) $userId,
        ]);
        
        // Load uploader relationship for response
        $document->load('uploader:id,name,email');
        
        // Round 238: Audit log for document version creation
        try {
            $this->audit(
                'document.version_created',
                $document,
                null,
                [
                    'document_id' => $document->id,
                    'version_id' => $newVersion->id,
                    'version_number' => $newVersion->version_number,
                    'version_note' => $newVersion->note,
                    'file_size' => $newVersion->file_size,
                    'file_path' => $newVersion->file_path,
                    'file_type' => $newVersion->file_type,
                ],
                $projectId
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to create audit log for document version', [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'version_id' => $newVersion->id,
            ]);
        }
        
        // Log activity for document version creation
        // Round 191: Include version metadata when logging document update for new version
        try {
            $updateMetadata = [
                'version_created' => true,
                'new_file_path' => $filePath,
            ];
            
            // Add version metadata if version entry exists
            if ($newVersion) {
                $updateMetadata['version_id'] = $newVersion->id;
                $updateMetadata['version_number'] = $newVersion->version_number;
                $updateMetadata['version_note'] = $newVersion->note;
            }
            
            \App\Models\ProjectActivity::logDocumentUpdated($document->fresh(), (string) $userId, $updateMetadata);
        } catch (\Exception $e) {
            // Logging failure should not break the operation
            \Log::warning('Failed to log document version activity', [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'project_id' => $projectId
            ]);
        }
        
        return $document->fresh();
    }

    /**
     * Get document versions
     * 
     * Returns all versions for a given document with tenant isolation
     * 
     * @param string $projectId Project ID
     * @param string|int|null $tenantId Tenant ID for multi-tenant isolation
     * @param string $documentId Document ID
     * @return array Array of version data
     * @throws \Exception If document not found or tenant mismatch
     */
    public function getDocumentVersions(
        string $projectId,
        string|int|null $tenantId,
        string $documentId
    ): array {
        $this->validateTenantAccess($tenantId);
        
        // Find document with tenant and project validation
        $document = $this->findProjectDocumentForTenant($projectId, $tenantId, $documentId);
        
        if (!$document) {
            throw new \Exception('Document not found', 404);
        }
        
        // Query versions for this document (implicitly same project_id and tenant_id)
        $versions = \App\Models\ProjectDocumentVersion::where('document_id', $documentId)
            ->with(['uploader:id,name,email'])
            ->orderBy('version_number', 'desc')
            ->get();
        
        // Return structured data
        // Round 190: Include note in response
        return $versions->map(function($version) {
            return [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'name' => $version->name,
                'original_name' => $version->original_name,
                'file_size' => $version->file_size,
                'mime_type' => $version->mime_type,
                'file_type' => $version->file_type,
                'note' => $version->note, // Round 190: Version note
                'uploaded_by' => $version->uploader ? [
                    'id' => $version->uploader->id,
                    'name' => $version->uploader->name,
                    'email' => $version->uploader->email,
                ] : null,
                'created_at' => $version->created_at?->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Restore document to a specific version
     * 
     * Creates a snapshot of the current document state, then restores the document
     * to match the selected version's file and metadata
     * 
     * Round 189: Restore Document Version
     * 
     * @param string $projectId Project ID
     * @param string|int|null $tenantId Tenant ID for multi-tenant isolation
     * @param string $documentId Document ID
     * @param string $versionId Version ID to restore to
     * @param int|null $userId User ID performing the restore (defaults to authenticated user)
     * @return Document Updated document instance
     * @throws \Exception If document or version not found, or tenant mismatch
     */
    public function restoreDocumentVersion(
        string $projectId,
        string|int|null $tenantId,
        string $documentId,
        string $versionId,
        ?int $userId = null
    ): Document {
        $this->validateTenantAccess($tenantId);
        
        // Find document with tenant and project validation
        $document = $this->findProjectDocumentForTenant($projectId, $tenantId, $documentId);
        
        if (!$document) {
            throw new \Exception('Document not found', 404);
        }
        
        // Find the version that belongs to this document
        $version = \App\Models\ProjectDocumentVersion::where('document_id', $document->id)
            ->where('id', $versionId)
            ->first();
        
        if (!$version) {
            throw new \Exception('Version not found', 404);
        }
        
        // Get current user ID if not provided
        if ($userId === null) {
            $userId = Auth::id();
            if (!$userId) {
                throw new \Exception('User not authenticated', 401);
            }
        }
        
        // Capture current document state before restore (for audit log)
        $beforeState = [
            'file_path' => $document->file_path,
            'file_type' => $document->file_type,
            'mime_type' => $document->mime_type,
            'file_size' => $document->file_size,
            'file_hash' => $document->file_hash,
            'original_name' => $document->original_name,
            'name' => $document->name,
        ];
        
        // Create a snapshot of the current document state before restoring
        // This ensures the "pre-restore" state is also saved as a version
        // Round 190: Add auto-generated note explaining the restore
        $restoreNote = sprintf('Snapshot before restoring from version %d', $version->version_number);
        $this->createDocumentVersionSnapshot($document, (string) $userId, $restoreNote);
        
        // Copy file-related fields from version to document
        $updateData = [
            'file_path' => $version->file_path,
            'file_type' => $version->file_type,
            'mime_type' => $version->mime_type,
            'file_size' => $version->file_size,
            'file_hash' => $version->file_hash,
            'original_name' => $version->original_name,
        ];
        
        // Optionally update name if version has a name (preserve document name if version name is null)
        if ($version->name !== null) {
            $updateData['name'] = $version->name;
        }
        
        // Update document
        try {
            $document->update($updateData);
        } catch (\Exception $e) {
            throw new \Exception('Failed to restore document: ' . $e->getMessage(), 0, $e);
        }
        
        // Load uploader relationship for response
        $document->load('uploader:id,name,email');
        
        // Round 238: Audit log for document version restore
        try {
            $this->audit(
                'document.version_restored',
                $document,
                [
                    'document_id' => $document->id,
                    'file_path' => $beforeState['file_path'],
                    'file_size' => $beforeState['file_size'],
                    'version_number' => null, // Current version before restore
                ],
                [
                    'document_id' => $document->id,
                    'version_id' => $version->id,
                    'version_number' => $version->version_number,
                    'file_path' => $version->file_path,
                    'file_size' => $version->file_size,
                ],
                $projectId
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to create audit log for document version restore', [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'version_id' => $versionId,
            ]);
        }
        
        // Log activity for document version restore
        try {
            \App\Models\ProjectActivity::logDocumentVersionRestored($document->fresh(), $version, (string) $userId);
        } catch (\Exception $e) {
            // Logging failure should not break the operation
            \Log::warning('Failed to log document version restore activity', [
                'error' => $e->getMessage(),
                'document_id' => $document->id,
                'version_id' => $versionId,
                'project_id' => $projectId
            ]);
        }
        
        return $document->fresh();
    }
}
