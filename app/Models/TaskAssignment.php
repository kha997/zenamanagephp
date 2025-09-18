<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskAssignment extends Model
{
    use HasFactory, HasUlids;

    protected $table = 'task_assignments';
    
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'task_id',
        'user_id',
        'team_id',
        'assignment_type',
        'role',
        'assigned_hours',
        'actual_hours',
        'status',
        'assigned_at',
        'started_at',
        'completed_at',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'assigned_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'assigned_at' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Task assignment roles
     */
    public const ROLE_ASSIGNEE = 'assignee';
    public const ROLE_REVIEWER = 'reviewer';
    public const ROLE_WATCHER = 'watcher';

    public const VALID_ROLES = [
        self::ROLE_ASSIGNEE,
        self::ROLE_REVIEWER,
        self::ROLE_WATCHER,
    ];

    /**
     * Task assignment statuses
     */
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const VALID_STATUSES = [
        self::STATUS_ASSIGNED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    /**
     * Assignment types
     */
    public const TYPE_USER = 'user';
    public const TYPE_TEAM = 'team';

    public const VALID_TYPES = [
        self::TYPE_USER,
        self::TYPE_TEAM,
    ];

    /**
     * RELATIONSHIPS
     */

    /**
     * Get the task that owns the assignment.
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Get the user assigned to the task.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the team assigned to the task.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user who created the assignment.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the assignment.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * SCOPES
     */

    /**
     * Scope: Filter by role
     */
    public function scopeByRole($query, string $role)
    {
        return $query->where('role', $role);
    }

    /**
     * Scope: Filter by assignment type
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('assignment_type', $type);
    }

    /**
     * Scope: Filter by user assignments
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('assignment_type', self::TYPE_USER)
                    ->where('user_id', $userId);
    }

    /**
     * Scope: Filter by team assignments
     */
    public function scopeForTeam($query, string $teamId)
    {
        return $query->where('assignment_type', self::TYPE_TEAM)
                    ->where('team_id', $teamId);
    }

    /**
     * Scope: Filter by status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Active assignments
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [self::STATUS_ASSIGNED, self::STATUS_IN_PROGRESS]);
    }

    /**
     * Scope: Filter by task
     */
    public function scopeByTask($query, string $taskId)
    {
        return $query->where('task_id', $taskId);
    }

    /**
     * BUSINESS LOGIC METHODS
     */

    /**
     * Check if assignment is for a user
     */
    public function isUserAssignment(): bool
    {
        return $this->assignment_type === self::TYPE_USER;
    }

    /**
     * Check if assignment is for a team
     */
    public function isTeamAssignment(): bool
    {
        return $this->assignment_type === self::TYPE_TEAM;
    }

    /**
     * Get the assignee (user or team)
     */
    public function getAssigneeAttribute()
    {
        return $this->isUserAssignment() ? $this->user : $this->team;
    }

    /**
     * Get assignee name
     */
    public function getAssigneeNameAttribute(): string
    {
        if ($this->isUserAssignment()) {
            return $this->user ? $this->user->name : 'Unknown User';
        } else {
            return $this->team ? $this->team->name : 'Unknown Team';
        }
    }

    /**
     * Mark assignment as started
     */
    public function markAsStarted(): void
    {
        if ($this->status === self::STATUS_ASSIGNED) {
            $this->update([
                'status' => self::STATUS_IN_PROGRESS,
                'started_at' => now(),
            ]);
        }
    }

    /**
     * Mark assignment as completed
     */
    public function markAsCompleted(): void
    {
        if ($this->status === self::STATUS_IN_PROGRESS) {
            $this->update([
                'status' => self::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);
        }
    }

    /**
     * Mark assignment as cancelled
     */
    public function markAsCancelled(): void
    {
        $this->update([
            'status' => self::STATUS_CANCELLED,
            'completed_at' => now(),
        ]);
    }

    /**
     * Update actual hours
     */
    public function updateActualHours(float $hours): void
    {
        $this->update(['actual_hours' => $hours]);
    }

    /**
     * Get assignment duration in hours
     */
    public function getDurationHoursAttribute(): ?float
    {
        if ($this->started_at && $this->completed_at) {
            return $this->started_at->diffInHours($this->completed_at);
        }
        return null;
    }

    /**
     * Get remaining hours
     */
    public function getRemainingHoursAttribute(): float
    {
        return max(0, ($this->assigned_hours ?? 0) - ($this->actual_hours ?? 0));
    }
}