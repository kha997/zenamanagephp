<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory, HasUlids, SoftDeletes;

    protected $table = 'tasks';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'project_id',
        'component_id',
        'phase_id',
        'name',
        'title',
        'description',
        'status',
        'priority',
        'assignee_id',
        'watchers',
        'start_date',
        'end_date',
        'progress_percent',
        'estimated_hours',
        'actual_hours',
        'spent_hours',
        'parent_id',
        'order',
        'dependencies',
        'conditional_tag',
        'is_hidden',
        'tags',
        'visibility',
        'client_approved',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'progress_percent' => 'decimal:2',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'spent_hours' => 'decimal:2',
        'dependencies' => 'json',
        'watchers' => 'json',
        'tags' => 'json',
        'is_hidden' => 'boolean',
        'client_approved' => 'boolean',
        'order' => 'integer',
    ];

    /**
     * Các trạng thái hợp lệ
     */
    public const STATUS_TODO = 'todo';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_REVIEW = 'review';
    public const STATUS_DONE = 'done';

    public const VALID_STATUSES = [
        self::STATUS_TODO,
        self::STATUS_IN_PROGRESS,
        self::STATUS_BLOCKED,
        self::STATUS_REVIEW,
        self::STATUS_DONE,
    ];

    /**
     * Các mức độ ưu tiên
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const VALID_PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_MEDIUM,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    /**
     * Get the project that owns the task.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the component that owns the task.
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Get the user assigned to the task.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the parent task (for subtasks).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_id');
    }

    /**
     * Get the subtasks.
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_id')->orderBy('order');
    }

    /**
     * Get the user who created the task.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the task.
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get the tenant that owns the task.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the task assignments.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Get teams assigned to this task.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'task_assignments', 'task_id', 'team_id')
                    ->where('assignment_type', TaskAssignment::TYPE_TEAM)
                    ->withPivot(['role', 'assigned_hours', 'actual_hours', 'status', 'assigned_at', 'started_at', 'completed_at', 'notes'])
                    ->withTimestamps();
    }

    /**
     * Get the task notifications.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Get the task interaction logs.
     */
    public function interactionLogs(): HasMany
    {
        return $this->hasMany(InteractionLog::class);
    }

    /**
     * Get the task documents.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Get the task change requests.
     */
    public function changeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class);
    }

    /**
     * Get the task watchers (users watching this task).
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers', 'task_id', 'user_id')
                    ->withTimestamps();
    }

    /**
     * Get the task dependencies (tasks this task depends on).
     */
    public function dependencies(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'task_id', 'dependency_id')
                    ->withTimestamps();
    }

    /**
     * Get the tasks that depend on this task.
     */
    public function dependents(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_dependencies', 'dependency_id', 'task_id')
                    ->withTimestamps();
    }

    /**
     * SCOPES
     */

    /**
     * Scope: Filter by tenant
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Filter by priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope: Filter by assignee
     */
    public function scopeByAssignee($query, string $assigneeId)
    {
        return $query->where('assignee_id', $assigneeId);
    }

    /**
     * Scope: Filter by project
     */
    public function scopeByProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope: Filter overdue tasks
     */
    public function scopeOverdue($query)
    {
        return $query->where('end_date', '<', now())
                     ->whereNotIn('status', [self::STATUS_DONE, 'cancelled']);
    }

    /**
     * Scope: Filter tasks due soon
     */
    public function scopeDueSoon($query, int $days = 3)
    {
        return $query->whereBetween('end_date', [now(), now()->addDays($days)])
                     ->whereNotIn('status', [self::STATUS_DONE, 'cancelled']);
    }

    /**
     * Scope: Filter by watcher
     */
    public function scopeByWatcher($query, string $userId)
    {
        return $query->whereJsonContains('watchers', $userId);
    }

    /**
     * Scope: Search tasks
     */
    public function scopeSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('title', 'like', "%{$search}%");
        });
    }

    /**
     * ACCESSORS & MUTATORS
     */

    /**
     * Get task status color
     */
    public function getStatusColorAttribute(): string
    {
        return match($this->status) {
            self::STATUS_TODO => 'gray',
            self::STATUS_IN_PROGRESS => 'blue',
            self::STATUS_BLOCKED => 'red',
            self::STATUS_REVIEW => 'yellow',
            self::STATUS_DONE => 'green',
            default => 'gray'
        };
    }

    /**
     * Get task priority color
     */
    public function getPriorityColorAttribute(): string
    {
        return match($this->priority) {
            self::PRIORITY_LOW => 'green',
            self::PRIORITY_MEDIUM => 'blue',
            self::PRIORITY_HIGH => 'orange',
            self::PRIORITY_URGENT => 'red',
            default => 'blue'
        };
    }

    /**
     * Get task progress percentage
     */
    public function getProgressPercentageAttribute(): float
    {
        return (float) $this->progress_percent;
    }

    /**
     * Get task duration in hours
     */
    public function getDurationHoursAttribute(): ?float
    {
        if ($this->start_date && $this->end_date) {
            return $this->start_date->diffInHours($this->end_date);
        }
        return null;
    }

    /**
     * Get task remaining hours
     */
    public function getRemainingHoursAttribute(): ?float
    {
        if ($this->estimated_hours && $this->actual_hours) {
            return max(0, $this->estimated_hours - $this->actual_hours);
        }
        return $this->estimated_hours;
    }

    /**
     * Check if task is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        return $this->end_date && $this->end_date->isPast() && $this->status !== self::STATUS_DONE;
    }

    /**
     * Check if task is due soon
     */
    public function getIsDueSoonAttribute(): bool
    {
        return $this->end_date && $this->end_date->isBetween(now(), now()->addDays(3));
    }

    /**
     * Get task health score (0-100)
     */
    public function getHealthScoreAttribute(): int
    {
        $score = 0;
        
        // Progress score (40%)
        $score += ($this->progress_percent / 100) * 40;
        
        // Time score (30%)
        if ($this->start_date && $this->end_date) {
            $totalDays = $this->start_date->diffInDays($this->end_date);
            $elapsedDays = $this->start_date->diffInDays(now());
            if ($totalDays > 0) {
                $timeProgress = min(1, $elapsedDays / $totalDays);
                $score += $timeProgress * 30;
            }
        }
        
        // Budget score (30%)
        if ($this->estimated_hours && $this->actual_hours) {
            $budgetProgress = min(1, $this->actual_hours / $this->estimated_hours);
            $score += $budgetProgress * 30;
        }
        
        return min(100, (int) $score);
    }

    /**
     * BUSINESS LOGIC METHODS
     */

    /**
     * Check if task can transition to new status
     */
    public function canTransitionTo(string $newStatus): bool
    {
        $transitions = [
            self::STATUS_TODO => [self::STATUS_IN_PROGRESS, self::STATUS_BLOCKED],
            self::STATUS_IN_PROGRESS => [self::STATUS_REVIEW, self::STATUS_BLOCKED],
            self::STATUS_BLOCKED => [self::STATUS_TODO, self::STATUS_IN_PROGRESS],
            self::STATUS_REVIEW => [self::STATUS_DONE, self::STATUS_IN_PROGRESS],
            self::STATUS_DONE => []
        ];
        
        return in_array($newStatus, $transitions[$this->status] ?? []);
    }

    /**
     * Transition task to new status
     */
    public function transitionTo(string $newStatus, string $reason = null): bool
    {
        if (!$this->canTransitionTo($newStatus)) {
            return false;
        }

        $oldStatus = $this->status;
        $this->update(['status' => $newStatus]);

        // Log audit
        if (class_exists('\App\Services\AuditService')) {
            \App\Services\AuditService::log(
                'task_status_changed',
                'Task',
                $this->id,
                ['status' => $oldStatus, 'reason' => $reason],
                ['status' => $newStatus]
            );
        }

        return true;
    }

    /**
     * Add watcher to task
     */
    public function addWatcher(string $userId): bool
    {
        $watchers = $this->watchers ?? [];
        if (!in_array($userId, $watchers)) {
            $watchers[] = $userId;
            $this->update(['watchers' => $watchers]);
            return true;
        }
        return false;
    }

    /**
     * Remove watcher from task
     */
    public function removeWatcher(string $userId): bool
    {
        $watchers = $this->watchers ?? [];
        $key = array_search($userId, $watchers);
        if ($key !== false) {
            unset($watchers[$key]);
            $this->update(['watchers' => array_values($watchers)]);
            return true;
        }
        return false;
    }

    /**
     * Add dependency to task
     */
    public function addDependency(string $dependencyId): bool
    {
        $dependencies = $this->dependencies ?? [];
        if (!in_array($dependencyId, $dependencies)) {
            $dependencies[] = $dependencyId;
            $this->update(['dependencies' => $dependencies]);
            return true;
        }
        return false;
    }

    /**
     * Remove dependency from task
     */
    public function removeDependency(string $dependencyId): bool
    {
        $dependencies = $this->dependencies ?? [];
        $key = array_search($dependencyId, $dependencies);
        if ($key !== false) {
            unset($dependencies[$key]);
            $this->update(['dependencies' => array_values($dependencies)]);
            return true;
        }
        return false;
    }

    /**
     * Check if task has circular dependency
     */
    public function hasCircularDependency(string $dependencyId): bool
    {
        if ($dependencyId === $this->id) {
            return true;
        }

        $dependency = Task::find($dependencyId);
        if (!$dependency) {
            return false;
        }

        $dependencies = $dependency->dependencies ?? [];
        return in_array($this->id, $dependencies);
    }

    /**
     * Calculate task progress based on subtasks
     */
    public function calculateProgress(): float
    {
        $subtasks = $this->subtasks;
        
        if ($subtasks->count() === 0) {
            return $this->progress_percent;
        }
        
        $totalWeight = $subtasks->sum('estimated_hours') ?: $subtasks->count();
        $completedWeight = $subtasks->where('status', self::STATUS_DONE)->sum('estimated_hours') ?: 
                          $subtasks->where('status', self::STATUS_DONE)->count();
        
        if ($totalWeight == 0) {
            return 0.0;
        }
        
        return round(($completedWeight / $totalWeight) * 100, 2);
    }

    /**
     * Update task progress
     */
    public function updateProgress(): void
    {
        $this->update(['progress_percent' => $this->calculateProgress()]);
    }
}