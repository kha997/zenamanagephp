<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Log;
use Src\Compensation\Models\TaskCompensation;
use Src\Foundation\EventBus;
use Src\Foundation\Helpers\AuthHelper;

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
    use HasUlids, HasFactory, BelongsToTenant;
    
    protected $table = 'tasks';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'project_id',
        'component_id',
        'phase_id',
        'name',
        'title',
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
        'estimated_cost',
        'actual_cost',
        'progress_percent',
        'tags',
        'visibility',
        'client_approved',
        'assignee_id',
        'assigned_to',
        'created_by'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'dependencies' => 'array',
        'is_hidden' => 'boolean',
        'estimated_hours' => 'float',
        'actual_hours' => 'float',
        'estimated_cost' => 'float',
        'actual_cost' => 'float',
        'progress_percent' => 'float',
        'tags' => 'array',
        'client_approved' => 'boolean'
    ];

    protected $attributes = [
        'status' => 'backlog',
        'priority' => 'normal',
        'is_hidden' => false,
        'estimated_hours' => 0.0,
        'actual_hours' => 0.0,
        'estimated_cost' => 0.0,
        'actual_cost' => 0.0,
        'progress_percent' => 0.0,
        'visibility' => 'internal',
        'client_approved' => false
    ];

    /**
     * Các trạng thái hợp lệ (MVP 1.5)
     */
    public const STATUS_BACKLOG = 'backlog';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_BLOCKED = 'blocked';
    public const STATUS_DONE = 'done';
    public const STATUS_CANCELED = 'canceled';

    /**
     * Accessors for consistent field names
     */
    public function getTitleAttribute(): string
    {
        return $this->name;
    }

    public function getDueDateAttribute(): ?string
    {
        return $this->end_date?->toDateString();
    }

    public function getProgressAttribute(): float
    {
        return $this->progress_percent;
    }

    public const VALID_STATUSES = [
        self::STATUS_BACKLOG,
        self::STATUS_IN_PROGRESS,
        self::STATUS_BLOCKED,
        self::STATUS_DONE,
        self::STATUS_CANCELED,
    ];

    /**
     * Các mức độ ưu tiên (MVP 1.5)
     */
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    public const VALID_PRIORITIES = [
        self::PRIORITY_LOW,
        self::PRIORITY_NORMAL,
        self::PRIORITY_HIGH,
        self::PRIORITY_URGENT,
    ];

    /**
     * Relationship: Task thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Task thuộc về project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relationship: Task được assign cho user
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Relationship: Task được tạo bởi user
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
            User::class,
            'task_assignments',
            'task_id',
            'user_id'
        )->withPivot(['split_percent', 'role'])
          ->withTimestamps();
    }

    /**
     * Relationship: Task có nhiều watchers
     */
    public function watchers(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'task_watchers',
            'task_id',
            'user_id'
        )->withTimestamps();
    }

    /**
     * Relationship: Task có nhiều dependencies
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'task_id');
    }

    /**
     * Relationship: Task có nhiều dependents
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'depends_on_task_id');
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
        if ($newProgress >= 100 && $this->status !== self::STATUS_DONE) {
            $this->update(['status' => self::STATUS_DONE]);
        } elseif ($newProgress > 0 && $this->status === self::STATUS_BACKLOG) {
            $this->update(['status' => self::STATUS_IN_PROGRESS]);
        }
        
        // Dispatch event
        EventBus::dispatch('Task.Progress.Updated', [
            'task_id' => $this->ulid,
            'project_id' => $this->project->ulid,
            'component_id' => $this->component?->ulid,
            'old_progress' => $oldProgress,
            'new_progress' => $newProgress,
            'actor_id' => $this->resolveActorId() ?? 'system'
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
            return $task->status === self::STATUS_DONE;
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
        return $query->where('status', self::STATUS_BACKLOG)
                    ->where(function($q) {
                        $q->whereNull('dependencies')
                          ->orWhereJsonLength('dependencies', 0);
                    });
    }

    /**
     * Resolve actor ID safely
     */
    protected function resolveActorId(): ?int
    {
        try {
            return AuthHelper::id();
        } catch (\Throwable $e) {
            Log::warning('Could not resolve actor ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Tạo factory instance mới cho model
     */
    protected static function newFactory()
    {
        return \Database\Factories\TaskFactory::new();
    }
}