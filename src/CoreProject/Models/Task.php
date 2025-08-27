<?php declare(strict_types=1);

namespace Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Src\Foundation\Traits\HasTimestamps;
use Src\Foundation\Traits\HasOwnership;
use Src\Foundation\Traits\HasTags;
use Src\Foundation\Traits\HasVisibility;
use Src\Foundation\Traits\HasAuditLog;
use Src\Foundation\Events\EventBus;
use Src\Compensation\Models\TaskCompensation;

/**
 * Model Task - Quản lý công việc
 * 
 * @property string $id ULID của task (primary key)
 * @property string $project_id ID dự án (ULID)
 * @property string|null $component_id ID component (ULID)
 * @property string|null $phase_id ID phase (ULID)
 * @property string $name Tên task
 * @property string|null $description Mô tả
 * @property \Carbon\Carbon|null $start_date Ngày bắt đầu
 * @property \Carbon\Carbon|null $end_date Ngày kết thúc
 * @property string $status Trạng thái
 * @property string $priority Độ ưu tiên
 * @property array|null $dependencies Mảng task_ids phụ thuộc
 * @property string|null $conditional_tag Tag điều kiện
 * @property bool $is_hidden Ẩn task
 * @property float $estimated_hours Số giờ ước tính
 * @property float $actual_hours Số giờ thực tế
 * @property float $progress_percent Tiến độ %
 */
class Task extends Model
{
    use HasUlids, HasTimestamps, HasOwnership, HasTags, HasVisibility, HasAuditLog;

    protected $table = 'tasks';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'project_id',
        'component_id',
        'phase_id',
        'name',
        'description',
        'start_date',
        'end_date',
        'status',
        'priority',
        'dependencies',
        'conditional_tag',
        'is_hidden',
        'estimated_hours',
        'actual_hours',
        'progress_percent',
        'tags',
        'visibility',
        'client_approved'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'dependencies' => 'array',
        'is_hidden' => 'boolean',
        'estimated_hours' => 'float',
        'actual_hours' => 'float',
        'progress_percent' => 'float',
        'tags' => 'array',
        'client_approved' => 'boolean'
    ];

    protected $attributes = [
        'status' => 'pending',
        'priority' => 'normal',
        'is_hidden' => false,
        'estimated_hours' => 0.0,
        'actual_hours' => 0.0,
        'progress_percent' => 0.0,
        'visibility' => 'internal',
        'client_approved' => false
    ];

    /**
     * Các trạng thái hợp lệ
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ON_HOLD = 'on_hold';
    public const STATUS_CANCELLED = 'cancelled';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_ON_HOLD,
        self::STATUS_CANCELLED,
    ];

    /**
     * Các mức độ ưu tiên
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_CRITICAL = 'critical';

    public const VALID_PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_CRITICAL,
    ];

    /**
     * Relationship: Task thuộc về project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relationship: Task có thể thuộc về component
     */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Component::class);
    }

    /**
     * Relationship: Task có nhiều assignments
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
    }

    /**
     * Relationship: Task được assign cho nhiều users
     */
    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'task_assignments',
            'task_id',
            'user_id'
        )->withPivot(['split_percentage', 'role'])
          ->withTimestamps();
    }

    /**
     * Relationship: Task có nhiều interaction logs
     */
    public function interactionLogs(): HasMany
    {
        return $this->hasMany(\Src\InteractionLogs\Models\InteractionLog::class, 'linked_task_id');
    }

    /**
     * Relationship: Task có thể có compensation
     */
    public function compensation(): HasOne
    {
        return $this->hasOne(TaskCompensation::class);
    }

    /**
     * Cập nhật tiến độ task
     */
    public function updateProgress(float $newProgress): void
    {
        $oldProgress = $this->progress_percent;
        $this->update(['progress_percent' => $newProgress]);
        
        // Auto update status based on progress
        if ($newProgress >= 100 && $this->status !== self::STATUS_COMPLETED) {
            $this->update(['status' => self::STATUS_COMPLETED]);
        } elseif ($newProgress > 0 && $this->status === self::STATUS_PENDING) {
            $this->update(['status' => self::STATUS_IN_PROGRESS]);
        }
        
        // Dispatch event
        EventBus::dispatch('Task.Progress.Updated', [
            'task_id' => $this->ulid,
            'project_id' => $this->project->ulid,
            'component_id' => $this->component?->ulid,
            'old_progress' => $oldProgress,
            'new_progress' => $newProgress,
            'actor_id' => auth()->id() ?? 'system'
        ]);
    }

    /**
     * Kiểm tra task có thể bắt đầu không (dependencies đã hoàn thành)
     */
    public function canStart(): bool
    {
        if (empty($this->dependencies)) {
            return true;
        }
        
        $dependentTasks = Task::whereIn('ulid', $this->dependencies)->get();
        
        return $dependentTasks->every(function ($task) {
            return $task->status === self::STATUS_COMPLETED;
        });
    }

    /**
     * Lấy các tasks phụ thuộc vào task này
     */
    public function getDependentTasks()
    {
        return Task::where('project_id', $this->project_id)
                   ->whereJsonContains('dependencies', $this->ulid)
                   ->get();
    }

    /**
     * Scope: Lọc theo project
     */
    public function scopeForProject($query, int $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope: Lọc theo component
     */
    public function scopeForComponent($query, int $componentId)
    {
        return $query->where('component_id', $componentId);
    }

    /**
     * Scope: Chỉ lấy tasks không ẩn
     */
    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    /**
     * Scope: Lọc theo status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: Lọc theo priority
     */
    public function scopeByPriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope: Tasks có thể bắt đầu
     */
    public function scopeCanStart($query)
    {
        return $query->where('status', self::STATUS_PENDING)
                    ->where(function($q) {
                        $q->whereNull('dependencies')
                          ->orWhereJsonLength('dependencies', 0);
                    });
    }
}