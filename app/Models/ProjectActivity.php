<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

/**
 * ProjectActivity Model - Activity feed cho projects
 * 
 * @property int $id Auto-increment primary key
 * @property string $project_id ID dự án
 * @property string $user_id ID người thực hiện
 * @property string $action Hành động
 * @property string $entity_type Loại entity
 * @property string|null $entity_id ID entity
 * @property string $description Mô tả
 * @property array|null $metadata Dữ liệu bổ sung
 * @property string|null $ip_address IP address
 * @property string|null $user_agent User agent
 */
class ProjectActivity extends Model
{
    use HasFactory;

    protected $table = 'project_activities';
    
    protected $keyType = 'int';
    public $incrementing = true;
    
    protected $fillable = [
        'project_id',
        'tenant_id',
        'user_id',
        'action',
        'entity_type',
        'entity_id',
        'description',
        'metadata',
        'ip_address',
        'user_agent'
    ];

    protected $casts = [
        'metadata' => 'array'
    ];

    /**
     * Action constants
     */
    public const ACTION_CREATED = 'created';
    public const ACTION_UPDATED = 'updated';
    public const ACTION_DELETED = 'deleted';
    public const ACTION_STATUS_CHANGED = 'status_changed';
    public const ACTION_MILESTONE_COMPLETED = 'milestone_completed';
    public const ACTION_TASK_UPDATED = 'task_updated';
    public const ACTION_TASK_COMPLETED = 'task_completed';
    public const ACTION_PROJECT_TASK_UPDATED = 'project_task_updated';
    public const ACTION_PROJECT_TASK_COMPLETED = 'project_task_completed';
    public const ACTION_PROJECT_TASK_MARKED_INCOMPLETE = 'project_task_marked_incomplete';
    public const ACTION_PROJECT_TASKS_GENERATED_FROM_TEMPLATE = 'project_tasks_generated_from_template';
    public const ACTION_PROJECT_TASKS_REORDERED = 'project_tasks_reordered';
    public const ACTION_PROJECT_TASK_ASSIGNED = 'project_task_assigned';
    public const ACTION_PROJECT_TASK_UNASSIGNED = 'project_task_unassigned';
    public const ACTION_PROJECT_TASK_REASSIGNED = 'project_task_reassigned';
    public const ACTION_TEAM_MEMBER_JOINED = 'team_member_joined';
    public const ACTION_TEAM_MEMBER_LEFT = 'team_member_left';
    public const ACTION_DOCUMENT_UPLOADED = 'document_uploaded';
    public const ACTION_DOCUMENT_UPDATED = 'document_updated';
    public const ACTION_DOCUMENT_DELETED = 'document_deleted';
    public const ACTION_DOCUMENT_DOWNLOADED = 'document_downloaded';
    public const ACTION_DOCUMENT_APPROVED = 'document_approved';
    public const ACTION_DOCUMENT_VERSION_RESTORED = 'document_version_restored';
    public const ACTION_COMMENT_ADDED = 'comment_added';
    // Round 230: Cost Workflow Actions
    public const ACTION_CHANGE_ORDER_PROPOSED = 'change_order_proposed';
    public const ACTION_CHANGE_ORDER_APPROVED = 'change_order_approved';
    public const ACTION_CHANGE_ORDER_REJECTED = 'change_order_rejected';
    public const ACTION_CERTIFICATE_SUBMITTED = 'certificate_submitted';
    public const ACTION_CERTIFICATE_APPROVED = 'certificate_approved';
    public const ACTION_PAYMENT_MARKED_PAID = 'payment_marked_paid';

    public const VALID_ACTIONS = [
        self::ACTION_CREATED,
        self::ACTION_UPDATED,
        self::ACTION_DELETED,
        self::ACTION_STATUS_CHANGED,
        self::ACTION_MILESTONE_COMPLETED,
        self::ACTION_TASK_UPDATED,
        self::ACTION_TASK_COMPLETED,
        self::ACTION_PROJECT_TASK_UPDATED,
        self::ACTION_PROJECT_TASK_COMPLETED,
        self::ACTION_PROJECT_TASK_MARKED_INCOMPLETE,
        self::ACTION_PROJECT_TASKS_GENERATED_FROM_TEMPLATE,
        self::ACTION_PROJECT_TASKS_REORDERED,
        self::ACTION_PROJECT_TASK_ASSIGNED,
        self::ACTION_PROJECT_TASK_UNASSIGNED,
        self::ACTION_PROJECT_TASK_REASSIGNED,
        self::ACTION_TEAM_MEMBER_JOINED,
        self::ACTION_TEAM_MEMBER_LEFT,
        self::ACTION_DOCUMENT_UPLOADED,
        self::ACTION_DOCUMENT_UPDATED,
        self::ACTION_DOCUMENT_DELETED,
        self::ACTION_DOCUMENT_DOWNLOADED,
            self::ACTION_DOCUMENT_APPROVED,
            self::ACTION_DOCUMENT_VERSION_RESTORED,
            self::ACTION_COMMENT_ADDED,
            // Round 230: Cost Workflow Actions
            self::ACTION_CHANGE_ORDER_PROPOSED,
            self::ACTION_CHANGE_ORDER_APPROVED,
            self::ACTION_CHANGE_ORDER_REJECTED,
            self::ACTION_CERTIFICATE_SUBMITTED,
            self::ACTION_CERTIFICATE_APPROVED,
            self::ACTION_PAYMENT_MARKED_PAID,
        ];

    /**
     * Entity type constants
     */
    public const ENTITY_PROJECT = 'Project';
    public const ENTITY_TASK = 'Task';
    public const ENTITY_PROJECT_TASK = 'ProjectTask';
    public const ENTITY_MILESTONE = 'Milestone';
    public const ENTITY_DOCUMENT = 'Document';
    public const ENTITY_COMMENT = 'Comment';
    public const ENTITY_TEAM_MEMBER = 'TeamMember';
    // Round 230: Cost Entities
    public const ENTITY_CHANGE_ORDER = 'ChangeOrder';
    public const ENTITY_PAYMENT_CERTIFICATE = 'ContractPaymentCertificate';
    public const ENTITY_ACTUAL_PAYMENT = 'ContractActualPayment';

    public const VALID_ENTITY_TYPES = [
        self::ENTITY_PROJECT,
        self::ENTITY_TASK,
        self::ENTITY_PROJECT_TASK,
        self::ENTITY_MILESTONE,
        self::ENTITY_DOCUMENT,
        self::ENTITY_COMMENT,
        self::ENTITY_TEAM_MEMBER,
        // Round 230: Cost Entities
        self::ENTITY_CHANGE_ORDER,
        self::ENTITY_PAYMENT_CERTIFICATE,
        self::ENTITY_ACTUAL_PAYMENT,
    ];

    /**
     * Relationships
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scopes
     */
    public function scopeByProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeByUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    public function scopeByEntityType($query, string $entityType)
    {
        return $query->where('entity_type', $entityType);
    }

    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    /**
     * Create activity log
     */
    public static function log(
        string $projectId,
        string $userId,
        string $action,
        string $entityType,
        string $description,
        ?string $entityId = null,
        array $metadata = []
    ): self {
        return self::create([
            'project_id' => $projectId,
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata' => $metadata,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent()
        ]);
    }

    /**
     * Log project creation
     */
    public static function logProjectCreated(Project $project, string $userId): self
    {
        return self::log(
            $project->id,
            $userId,
            self::ACTION_CREATED,
            self::ENTITY_PROJECT,
            "Project '{$project->name}' was created",
            $project->id,
            [
                'project_name' => $project->name,
                'project_code' => $project->code,
                'status' => $project->status
            ]
        );
    }

    /**
     * Log project update
     */
    public static function logProjectUpdated(Project $project, string $userId, array $changes): self
    {
        $description = "Project '{$project->name}' was updated";
        if (!empty($changes['status'])) {
            $description = "Project '{$project->name}' status changed to {$changes['status']}";
        }

        return self::log(
            $project->id,
            $userId,
            self::ACTION_UPDATED,
            self::ENTITY_PROJECT,
            $description,
            $project->id,
            [
                'project_name' => $project->name,
                'changes' => $changes
            ]
        );
    }

    /**
     * Log milestone completion
     */
    public static function logMilestoneCompleted(ProjectMilestone $milestone, string $userId): self
    {
        return self::log(
            $milestone->project_id,
            $userId,
            self::ACTION_MILESTONE_COMPLETED,
            self::ENTITY_MILESTONE,
            "Milestone '{$milestone->name}' was completed",
            $milestone->id,
            [
                'milestone_name' => $milestone->name,
                'target_date' => $milestone->target_date?->toISOString(),
                'completed_date' => $milestone->completed_date?->toISOString()
            ]
        );
    }

    /**
     * Log task update
     */
    public static function logTaskUpdated(Task $task, string $userId, array $changes): self
    {
        $description = "Task '{$task->name}' was updated";
        if (!empty($changes['status'])) {
            $description = "Task '{$task->name}' status changed to {$changes['status']}";
        }

        return self::log(
            $task->project_id,
            $userId,
            self::ACTION_TASK_UPDATED,
            self::ENTITY_TASK,
            $description,
            $task->id,
            [
                'task_name' => $task->name,
                'changes' => $changes
            ]
        );
    }

    /**
     * Log team member joined
     */
    public static function logTeamMemberJoined(Project $project, User $user, string $role, string $addedBy): self
    {
        return self::log(
            $project->id,
            $addedBy,
            self::ACTION_TEAM_MEMBER_JOINED,
            self::ENTITY_TEAM_MEMBER,
            "{$user->name} joined the project as {$role}",
            $user->id,
            [
                'user_name' => $user->name,
                'user_email' => $user->email,
                'role' => $role
            ]
        );
    }

    /**
     * Log document upload
     */
    public static function logDocumentUploaded(Document $document, string $userId): self
    {
        $documentName = $document->name ?? $document->original_name ?? 'Unknown';
        return self::log(
            $document->project_id,
            $userId,
            self::ACTION_DOCUMENT_UPLOADED,
            self::ENTITY_DOCUMENT,
            "Uploaded document \"{$documentName}\"",
            $document->id,
            [
                'document_name' => $documentName,
                'original_name' => $document->original_name,
                'file_type' => $document->file_type,
                'file_size' => $document->file_size
            ]
        );
    }

    /**
     * Log document update
     */
    public static function logDocumentUpdated(Document $document, string $userId, array $changes = []): self
    {
        $documentName = $document->name ?? $document->original_name ?? 'Unknown';
        return self::log(
            $document->project_id,
            $userId,
            self::ACTION_DOCUMENT_UPDATED,
            self::ENTITY_DOCUMENT,
            "Updated document \"{$documentName}\"",
            $document->id,
            [
                'document_name' => $documentName,
                'updated_fields' => array_keys($changes),
                'changes' => $changes
            ]
        );
    }

    /**
     * Log document deletion
     */
    public static function logDocumentDeleted(Document $document, string $userId): self
    {
        $documentName = $document->name ?? $document->original_name ?? 'Unknown';
        return self::log(
            $document->project_id,
            $userId,
            self::ACTION_DOCUMENT_DELETED,
            self::ENTITY_DOCUMENT,
            "Deleted document \"{$documentName}\"",
            $document->id,
            [
                'document_name' => $documentName,
                'original_name' => $document->original_name,
                'file_path' => $document->file_path
            ]
        );
    }

    /**
     * Log document download
     */
    public static function logDocumentDownloaded(Document $document, string $userId): self
    {
        $documentName = $document->name ?? $document->original_name ?? 'Unknown';
        return self::log(
            $document->project_id,
            $userId,
            self::ACTION_DOCUMENT_DOWNLOADED,
            self::ENTITY_DOCUMENT,
            "Downloaded document \"{$documentName}\"",
            $document->id,
            [
                'document_name' => $documentName,
                'original_name' => $document->original_name,
                'file_type' => $document->file_type,
                'file_size' => $document->file_size
            ]
        );
    }

    /**
     * Log document version restore
     * 
     * Round 189: Restore Document Version
     * Round 191: Added version_note to metadata
     */
    public static function logDocumentVersionRestored(Document $document, \App\Models\ProjectDocumentVersion $version, string $userId): self
    {
        $documentName = $document->name ?? $document->original_name ?? 'Unknown';
        return self::log(
            $document->project_id,
            $userId,
            self::ACTION_DOCUMENT_VERSION_RESTORED,
            self::ENTITY_DOCUMENT,
            sprintf(
                'Restored document "%s" to version %d',
                $documentName,
                $version->version_number
            ),
            $document->id,
            [
                'document_name' => $documentName,
                'version_id' => $version->id,
                'version_number' => $version->version_number,
                'version_note' => $version->note, // Round 191: Include version note
                'restored_file_path' => $version->file_path,
                'restored_file_size' => $version->file_size,
            ]
        );
    }

    /**
     * Get activity feed for project
     */
    public static function getProjectFeed(string $projectId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return self::byProject($projectId)
                   ->with(['user:id,name,email,avatar'])
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }

    /**
     * Get recent activities for user
     */
    public static function getUserRecentActivities(string $userId, int $limit = 20): \Illuminate\Database\Eloquent\Collection
    {
        return self::byUser($userId)
                   ->with(['project:id,name,code'])
                   ->orderBy('created_at', 'desc')
                   ->limit($limit)
                   ->get();
    }

    /**
     * Get activity statistics
     */
    public static function getActivityStatistics(string $projectId, int $days = 30): array
    {
        $activities = self::byProject($projectId)
                         ->where('created_at', '>=', now()->subDays($days))
                         ->get();

        return [
            'total_activities' => $activities->count(),
            'activities_by_action' => $activities->groupBy('action')->map->count(),
            'activities_by_user' => $activities->groupBy('user_id')->map->count(),
            'activities_by_day' => $activities->groupBy(function ($activity) {
                return $activity->created_at->format('Y-m-d');
            })->map->count(),
            'most_active_users' => $activities->groupBy('user_id')
                                             ->map->count()
                                             ->sortDesc()
                                             ->take(5)
        ];
    }

    /**
     * Accessors
     */
    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            self::ACTION_CREATED => 'Created',
            self::ACTION_UPDATED => 'Updated',
            self::ACTION_DELETED => 'Deleted',
            self::ACTION_STATUS_CHANGED => 'Status Changed',
            self::ACTION_MILESTONE_COMPLETED => 'Milestone Completed',
            self::ACTION_TASK_UPDATED => 'Task Updated',
            self::ACTION_TASK_COMPLETED => 'Task Completed',
            self::ACTION_PROJECT_TASK_UPDATED => 'Project Task Updated',
            self::ACTION_PROJECT_TASK_COMPLETED => 'Project Task Completed',
            self::ACTION_PROJECT_TASK_MARKED_INCOMPLETE => 'Project Task Marked Incomplete',
            self::ACTION_PROJECT_TASKS_GENERATED_FROM_TEMPLATE => 'Project Tasks Generated From Template',
            self::ACTION_PROJECT_TASKS_REORDERED => 'Project Tasks Reordered',
            self::ACTION_PROJECT_TASK_ASSIGNED => 'Task Assigned',
            self::ACTION_PROJECT_TASK_UNASSIGNED => 'Task Unassigned',
            self::ACTION_PROJECT_TASK_REASSIGNED => 'Task Reassigned',
            self::ACTION_TEAM_MEMBER_JOINED => 'Team Member Joined',
            self::ACTION_TEAM_MEMBER_LEFT => 'Team Member Left',
            self::ACTION_DOCUMENT_UPLOADED => 'Document Uploaded',
            self::ACTION_DOCUMENT_UPDATED => 'Document Updated',
            self::ACTION_DOCUMENT_DELETED => 'Document Deleted',
            self::ACTION_DOCUMENT_DOWNLOADED => 'Document Downloaded',
            self::ACTION_DOCUMENT_APPROVED => 'Document Approved',
            self::ACTION_DOCUMENT_VERSION_RESTORED => 'Restored Document Version',
            self::ACTION_COMMENT_ADDED => 'Comment Added',
            // Round 230: Cost Workflow Actions
            self::ACTION_CHANGE_ORDER_PROPOSED => 'Change Order Proposed',
            self::ACTION_CHANGE_ORDER_APPROVED => 'Change Order Approved',
            self::ACTION_CHANGE_ORDER_REJECTED => 'Change Order Rejected',
            self::ACTION_CERTIFICATE_SUBMITTED => 'Certificate Submitted',
            self::ACTION_CERTIFICATE_APPROVED => 'Certificate Approved',
            self::ACTION_PAYMENT_MARKED_PAID => 'Payment Marked Paid',
            default => 'Unknown'
        };
    }

    public function getActionColorAttribute(): string
    {
        return match($this->action) {
            self::ACTION_CREATED => 'green',
            self::ACTION_UPDATED => 'blue',
            self::ACTION_DELETED => 'red',
            self::ACTION_STATUS_CHANGED => 'yellow',
            self::ACTION_MILESTONE_COMPLETED => 'green',
            self::ACTION_TASK_UPDATED => 'blue',
            self::ACTION_TASK_COMPLETED => 'green',
            self::ACTION_PROJECT_TASK_UPDATED => 'blue',
            self::ACTION_PROJECT_TASK_COMPLETED => 'green',
            self::ACTION_PROJECT_TASK_MARKED_INCOMPLETE => 'yellow',
            self::ACTION_PROJECT_TASKS_GENERATED_FROM_TEMPLATE => 'purple',
            self::ACTION_PROJECT_TASKS_REORDERED => 'blue',
            self::ACTION_PROJECT_TASK_ASSIGNED => 'purple',
            self::ACTION_PROJECT_TASK_UNASSIGNED => 'purple',
            self::ACTION_PROJECT_TASK_REASSIGNED => 'purple',
            self::ACTION_TEAM_MEMBER_JOINED => 'green',
            self::ACTION_TEAM_MEMBER_LEFT => 'red',
            self::ACTION_DOCUMENT_UPLOADED => 'blue',
            self::ACTION_DOCUMENT_UPDATED => 'blue',
            self::ACTION_DOCUMENT_DELETED => 'red',
            self::ACTION_DOCUMENT_DOWNLOADED => 'purple',
            self::ACTION_DOCUMENT_APPROVED => 'green',
            self::ACTION_DOCUMENT_VERSION_RESTORED => 'orange',
            self::ACTION_COMMENT_ADDED => 'purple',
            default => 'gray'
        };
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }

    /**
     * Log project tasks generated from template
     * 
     * Round 206: Log when tasks are auto-generated from TaskTemplates
     * 
     * @param string $projectId
     * @param string $userId
     * @param \App\Models\Template $template
     * @param \Illuminate\Support\Collection $tasks Collection of ProjectTask models
     * @return self
     */
    public static function logProjectTasksGeneratedFromTemplate(
        string $projectId,
        string $userId,
        \App\Models\Template $template,
        \Illuminate\Support\Collection $tasks
    ): self {
        return self::log(
            $projectId,
            $userId,
            self::ACTION_PROJECT_TASKS_GENERATED_FROM_TEMPLATE,
            self::ENTITY_PROJECT_TASK,
            sprintf(
                'Generated %d task(s) from template "%s"',
                $tasks->count(),
                $template->name
            ),
            null, // entity_id is null for aggregated action
            [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'task_count' => $tasks->count(),
                'task_ids' => $tasks->pluck('id')->all(),
            ]
        );
    }

    /**
     * Log project task update
     * 
     * Round 206: Log when a project task is updated
     * 
     * @param \App\Models\ProjectTask $task
     * @param string $userId
     * @param array $changes Array with 'before' and 'after' keys
     * @return self
     */
    public static function logProjectTaskUpdated(
        \App\Models\ProjectTask $task,
        string $userId,
        array $changes = []
    ): self {
        $description = "Task '{$task->name}' was updated";
        
        // Extract before/after values from changes array
        $beforeStatus = $changes['before']['status'] ?? null;
        $afterStatus = $changes['after']['status'] ?? $task->status;
        $isCompletedBefore = $changes['before']['is_completed'] ?? $task->is_completed;
        $isCompletedAfter = $changes['after']['is_completed'] ?? $task->is_completed;
        $dueDateBefore = $changes['before']['due_date'] ?? null;
        $dueDateAfter = $changes['after']['due_date'] ?? $task->due_date?->toISOString();
        
        if ($beforeStatus !== $afterStatus) {
            $description = "Task '{$task->name}' status changed from {$beforeStatus} to {$afterStatus}";
        }
        
        // Round 208: Enhanced metadata with direct fields for easier frontend access
        $metadata = [
            'task_id' => $task->id,
            'task_name' => $task->name,
            'status_before' => $beforeStatus,
            'status_after' => $afterStatus,
            'is_completed_before' => $isCompletedBefore,
            'is_completed_after' => $isCompletedAfter,
            'changes' => $changes, // Keep for backward compatibility
        ];
        
        // Add due_date fields if they exist
        if ($dueDateBefore !== null || $dueDateAfter !== null) {
            $metadata['due_date_before'] = $dueDateBefore ? (is_string($dueDateBefore) ? $dueDateBefore : $dueDateBefore->toISOString()) : null;
            $metadata['due_date_after'] = $dueDateAfter;
        }
        
        return self::log(
            $task->project_id,
            $userId,
            self::ACTION_PROJECT_TASK_UPDATED,
            self::ENTITY_PROJECT_TASK,
            $description,
            $task->id,
            $metadata
        );
    }

    /**
     * Log project task completed
     * 
     * Round 206: Log when a project task is marked as completed
     * 
     * @param \App\Models\ProjectTask $task
     * @param string $userId
     * @return self
     */
    public static function logProjectTaskCompleted(
        \App\Models\ProjectTask $task,
        string $userId
    ): self {
        return self::log(
            $task->project_id,
            $userId,
            self::ACTION_PROJECT_TASK_COMPLETED,
            self::ENTITY_PROJECT_TASK,
            "Task '{$task->name}' was completed",
            $task->id,
            [
                'task_id' => $task->id,
                'task_name' => $task->name,
                'status' => $task->status,
                'completed_at' => $task->completed_at?->toISOString(),
            ]
        );
    }

    /**
     * Log project task marked incomplete
     * 
     * Round 206: Log when a project task is marked as incomplete
     * Round 208: Added completed_at_before to metadata
     * 
     * @param \App\Models\ProjectTask $task
     * @param string $userId
     * @param string|null $completedAtBefore ISO string of completed_at before marking incomplete
     * @return self
     */
    public static function logProjectTaskMarkedIncomplete(
        \App\Models\ProjectTask $task,
        string $userId,
        ?string $completedAtBefore = null
    ): self {
        return self::log(
            $task->project_id,
            $userId,
            self::ACTION_PROJECT_TASK_MARKED_INCOMPLETE,
            self::ENTITY_PROJECT_TASK,
            "Task '{$task->name}' was marked as incomplete",
            $task->id,
            [
                'task_id' => $task->id,
                'task_name' => $task->name,
                'status' => $task->status,
                'completed_at_before' => $completedAtBefore,
            ]
        );
    }

    /**
     * Log project tasks reordered
     * 
     * Round 211: Log when tasks are reordered within a phase
     * 
     * @param string $tenantId
     * @param Project $project
     * @param string|null $phaseCode
     * @param string|null $phaseLabel
     * @param array $taskIdsBefore Array of task IDs in order before reorder
     * @param array $taskIdsAfter Array of task IDs in order after reorder
     * @return self
     */
    public static function logProjectTasksReordered(
        string $tenantId,
        Project $project,
        ?string $phaseCode,
        ?string $phaseLabel,
        array $taskIdsBefore,
        array $taskIdsAfter
    ): self {
        $taskCount = count($taskIdsAfter);
        $phaseInfo = $phaseLabel ?: ($phaseCode ?: 'Unknown Phase');
        
        $description = sprintf(
            "Reordered %d task(s) in phase '%s'",
            $taskCount,
            $phaseInfo
        );
        
        $userId = \Illuminate\Support\Facades\Auth::id() ?? $project->created_by;
        
        return self::create([
            'project_id' => $project->id,
            'tenant_id' => $tenantId,
            'user_id' => (string) $userId,
            'action' => self::ACTION_PROJECT_TASKS_REORDERED,
            'entity_type' => self::ENTITY_PROJECT_TASK,
            'entity_id' => null, // Bulk action, no single entity
            'description' => $description,
            'metadata' => [
                'phase_code' => $phaseCode,
                'phase_label' => $phaseLabel,
                'task_ids_before' => $taskIdsBefore,
                'task_ids_after' => $taskIdsAfter,
                'task_count' => $taskCount,
            ],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent()
        ]);
    }

    /**
     * Log project task assignment change
     * 
     * Round 214: Log when task assignment changes (assign/unassign/reassign)
     * 
     * @param string $tenantId
     * @param Project $project
     * @param ProjectTask $task
     * @param User|null $oldAssignee
     * @param User|null $newAssignee
     * @return self|null Returns null if no change detected
     */
    public static function logProjectTaskAssignmentChange(
        string $tenantId,
        Project $project,
        \App\Models\ProjectTask $task,
        ?\App\Models\User $oldAssignee,
        ?\App\Models\User $newAssignee
    ): ?self {
        // Determine action type based on old/new assignee
        $action = null;
        $description = '';
        
        if ($oldAssignee === null && $newAssignee !== null) {
            // Assignment: null -> user
            $action = self::ACTION_PROJECT_TASK_ASSIGNED;
            $newName = $newAssignee->name ?? 'Unknown user';
            $description = sprintf(
                "Task '%s' assigned to %s",
                $task->name ?? 'Unknown task',
                $newName
            );
        } elseif ($oldAssignee !== null && $newAssignee === null) {
            // Unassignment: user -> null
            $action = self::ACTION_PROJECT_TASK_UNASSIGNED;
            $oldName = $oldAssignee->name ?? 'Unknown user';
            $description = sprintf(
                "Task '%s' unassigned (was %s)",
                $task->name ?? 'Unknown task',
                $oldName
            );
        } elseif ($oldAssignee !== null && $newAssignee !== null && $oldAssignee->id !== $newAssignee->id) {
            // Reassignment: user A -> user B
            $action = self::ACTION_PROJECT_TASK_REASSIGNED;
            $oldName = $oldAssignee->name ?? 'Unknown user';
            $newName = $newAssignee->name ?? 'Unknown user';
            $description = sprintf(
                "Task '%s' reassigned from %s to %s",
                $task->name ?? 'Unknown task',
                $oldName,
                $newName
            );
        } else {
            // No change (both null or same user)
            return null;
        }
        
        $userId = \Illuminate\Support\Facades\Auth::id() ?? $project->created_by;
        
        return self::create([
            'project_id' => $project->id,
            'tenant_id' => $tenantId,
            'user_id' => (string) $userId,
            'action' => $action,
            'entity_type' => self::ENTITY_PROJECT_TASK,
            'entity_id' => $task->id,
            'description' => $description,
            'metadata' => [
                'task_id' => $task->id,
                'task_name' => $task->name ?? 'Unknown task',
                'old_assignee_id' => $oldAssignee?->id,
                'old_assignee_name' => $oldAssignee?->name ?? null,
                'new_assignee_id' => $newAssignee?->id,
                'new_assignee_name' => $newAssignee?->name ?? null,
            ],
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent()
        ]);
    }
}
