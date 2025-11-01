<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Request;

/**
 * ProjectActivity Model - Activity feed cho projects
 * 
 * @property string $id ULID primary key
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
    use HasUlids, HasFactory;

    protected $table = 'project_activities';
    
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'project_id',
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
    public const ACTION_TEAM_MEMBER_JOINED = 'team_member_joined';
    public const ACTION_TEAM_MEMBER_LEFT = 'team_member_left';
    public const ACTION_DOCUMENT_UPLOADED = 'document_uploaded';
    public const ACTION_DOCUMENT_APPROVED = 'document_approved';
    public const ACTION_COMMENT_ADDED = 'comment_added';

    public const VALID_ACTIONS = [
        self::ACTION_CREATED,
        self::ACTION_UPDATED,
        self::ACTION_DELETED,
        self::ACTION_STATUS_CHANGED,
        self::ACTION_MILESTONE_COMPLETED,
        self::ACTION_TASK_UPDATED,
        self::ACTION_TASK_COMPLETED,
        self::ACTION_TEAM_MEMBER_JOINED,
        self::ACTION_TEAM_MEMBER_LEFT,
        self::ACTION_DOCUMENT_UPLOADED,
        self::ACTION_DOCUMENT_APPROVED,
        self::ACTION_COMMENT_ADDED,
    ];

    /**
     * Entity type constants
     */
    public const ENTITY_PROJECT = 'Project';
    public const ENTITY_TASK = 'Task';
    public const ENTITY_MILESTONE = 'Milestone';
    public const ENTITY_DOCUMENT = 'Document';
    public const ENTITY_COMMENT = 'Comment';
    public const ENTITY_TEAM_MEMBER = 'TeamMember';

    public const VALID_ENTITY_TYPES = [
        self::ENTITY_PROJECT,
        self::ENTITY_TASK,
        self::ENTITY_MILESTONE,
        self::ENTITY_DOCUMENT,
        self::ENTITY_COMMENT,
        self::ENTITY_TEAM_MEMBER,
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
        return self::log(
            $document->project_id,
            $userId,
            self::ACTION_DOCUMENT_UPLOADED,
            self::ENTITY_DOCUMENT,
            "Document '{$document->title}' was uploaded",
            $document->id,
            [
                'document_title' => $document->title,
                'document_type' => $document->document_type,
                'file_size' => $document->file_size
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
            self::ACTION_TEAM_MEMBER_JOINED => 'Team Member Joined',
            self::ACTION_TEAM_MEMBER_LEFT => 'Team Member Left',
            self::ACTION_DOCUMENT_UPLOADED => 'Document Uploaded',
            self::ACTION_DOCUMENT_APPROVED => 'Document Approved',
            self::ACTION_COMMENT_ADDED => 'Comment Added',
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
            self::ACTION_TEAM_MEMBER_JOINED => 'green',
            self::ACTION_TEAM_MEMBER_LEFT => 'red',
            self::ACTION_DOCUMENT_UPLOADED => 'blue',
            self::ACTION_DOCUMENT_APPROVED => 'green',
            self::ACTION_COMMENT_ADDED => 'purple',
            default => 'gray'
        };
    }

    public function getTimeAgoAttribute(): string
    {
        return $this->created_at->diffForHumans();
    }
}