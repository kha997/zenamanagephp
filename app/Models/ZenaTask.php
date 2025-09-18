<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Src\Foundation\Traits\HasTimestamps;
use Src\Foundation\Traits\HasOwnership;
use Src\Foundation\Traits\HasAuditLog;

/**
 * ZenaTask Model - Alias for Task model with Zena-specific features
 * 
 * @property string $id ULID primary key
 * @property string $tenant_id ID công ty (ULID)
 * @property string $project_id ID dự án (ULID)
 * @property string|null $parent_id ID task cha (ULID)
 * @property string $title Tiêu đề task
 * @property string|null $description Mô tả task
 * @property string $status Trạng thái task
 * @property string $priority Mức độ ưu tiên
 * @property string|null $assignee_id ID người được giao
 * @property string|null $created_by ID người tạo
 * @property \Carbon\Carbon|null $start_date Ngày bắt đầu
 * @property \Carbon\Carbon|null $end_date Ngày kết thúc
 * @property \Carbon\Carbon|null $completed_at Ngày hoàn thành
 * @property float $estimated_hours Số giờ ước tính
 * @property float $actual_hours Số giờ thực tế
 * @property int $progress Tiến độ % (0-100)
 * @property array|null $tags Tags
 * @property array|null $watchers Danh sách người theo dõi
 * @property array|null $dependencies Danh sách dependencies
 * @property int $order Thứ tự sắp xếp
 * @property string $visibility Mức độ hiển thị
 * @property bool $is_hidden Ẩn task
 * @property bool $client_approved Được khách hàng phê duyệt
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class ZenaTask extends Model
{
    use HasFactory, HasUlids, HasTimestamps, HasOwnership, HasAuditLog, SoftDeletes;

    protected $table = 'tasks';

    protected $fillable = [
        'id',
        'tenant_id',
        'project_id',
        'parent_id',
        'title',
        'description',
        'status',
        'priority',
        'assignee_id',
        'created_by',
        'start_date',
        'end_date',
        'completed_at',
        'estimated_hours',
        'actual_hours',
        'progress',
        'tags',
        'watchers',
        'dependencies',
        'order',
        'visibility',
        'is_hidden',
        'client_approved',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'completed_at' => 'datetime',
        'estimated_hours' => 'decimal:2',
        'actual_hours' => 'decimal:2',
        'progress' => 'integer',
        'tags' => 'array',
        'watchers' => 'array',
        'dependencies' => 'array',
        'is_hidden' => 'boolean',
        'client_approved' => 'boolean',
    ];

    protected $attributes = [
        'status' => 'pending',
        'priority' => 'medium',
        'progress' => 0,
        'estimated_hours' => 0.00,
        'actual_hours' => 0.00,
        'order' => 0,
        'visibility' => 'team',
        'is_hidden' => false,
        'client_approved' => false,
    ];

    /**
     * Relationship: Task belongs to a tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Task belongs to a project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ZenaProject::class, 'project_id');
    }

    /**
     * Relationship: Task belongs to a parent task
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ZenaTask::class, 'parent_id');
    }

    /**
     * Relationship: Task has many subtasks
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(ZenaTask::class, 'parent_id');
    }

    /**
     * Relationship: Task belongs to an assignee
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Relationship: Task belongs to a creator
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Relationship: Task has many assignments
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class, 'task_id');
    }

    /**
     * Relationship: Task has many dependencies
     */
    public function taskDependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'task_id');
    }

    /**
     * Relationship: Task has many dependents
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'dependency_id');
    }

    /**
     * Relationship: Task has many documents
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ZenaDocument::class, 'task_id');
    }

    /**
     * Relationship: Task has many watchers
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_watchers', 'task_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Scope: Active tasks
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }

    /**
     * Scope: Completed tasks
     */
    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope: Overdue tasks
     */
    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('end_date', '<', now())
                    ->where('status', '!=', 'completed');
    }

    /**
     * Scope: High priority tasks
     */
    public function scopeHighPriority(Builder $query): Builder
    {
        return $query->where('priority', 'high');
    }

    /**
     * Scope: Tasks assigned to user
     */
    public function scopeAssignedTo(Builder $query, string $userId): Builder
    {
        return $query->where('assignee_id', $userId);
    }

    /**
     * Check if task is overdue
     */
    public function isOverdue(): bool
    {
        return $this->end_date && 
               $this->end_date->isPast() && 
               $this->status !== 'completed';
    }

    /**
     * Check if task is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Calculate progress percentage
     */
    public function calculateProgressPercentage(): int
    {
        if ($this->estimated_hours === 0) {
            return $this->progress;
        }

        $actualProgress = ($this->actual_hours / $this->estimated_hours) * 100;
        return min(100, max(0, round($actualProgress)));
    }

    /**
     * Get duration in days
     */
    public function getDurationInDays(): int
    {
        if (!$this->start_date || !$this->end_date) {
            return 0;
        }

        return $this->start_date->diffInDays($this->end_date);
    }

    /**
     * Get remaining hours
     */
    public function getRemainingHours(): float
    {
        return max(0, $this->estimated_hours - $this->actual_hours);
    }

    /**
     * Get effort variance
     */
    public function getEffortVariance(): float
    {
        return $this->actual_hours - $this->estimated_hours;
    }

    /**
     * Get effort variance percentage
     */
    public function getEffortVariancePercentage(): float
    {
        if ($this->estimated_hours === 0) {
            return 0;
        }

        return round(($this->getEffortVariance() / $this->estimated_hours) * 100, 2);
    }

    /**
     * Check if task has dependencies
     */
    public function hasDependencies(): bool
    {
        return $this->taskDependencies()->count() > 0;
    }

    /**
     * Get blocked tasks (tasks that depend on this task)
     */
    public function getBlockedTasks(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->dependents()->with('task')->get()->pluck('task');
    }

    /**
     * Check if task is blocked by dependencies
     */
    public function isBlocked(): bool
    {
        $dependencies = $this->taskDependencies()->with('dependsOnTask')->get();
        
        foreach ($dependencies as $dependency) {
            if ($dependency->dependsOnTask && $dependency->dependsOnTask->status !== 'completed') {
                return true;
            }
        }

        return false;
    }

    /**
     * Get critical path for this task
     */
    public function getCriticalPath(): array
    {
        $path = [$this];
        $dependencies = $this->taskDependencies()->with('dependsOnTask')->get();
        
        foreach ($dependencies as $dependency) {
            if ($dependency->dependsOnTask) {
                $path = array_merge($dependency->dependsOnTask->getCriticalPath(), $path);
            }
        }

        return $path;
    }

    /**
     * Mark task as completed
     */
    public function markAsCompleted(): bool
    {
        return $this->update([
            'status' => 'completed',
            'progress' => 100,
            'completed_at' => now(),
        ]);
    }

    /**
     * Add watcher to task
     */
    public function addWatcher(User $user): bool
    {
        if (!$this->watchers()->where('user_id', $user->id)->exists()) {
            $this->watchers()->attach($user->id);
            return true;
        }

        return false;
    }

    /**
     * Remove watcher from task
     */
    public function removeWatcher(User $user): bool
    {
        return $this->watchers()->detach($user->id) > 0;
    }
}
