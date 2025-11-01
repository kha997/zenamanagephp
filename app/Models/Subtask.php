<?php declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model Subtask - Quản lý subtask
 * 
 * @property string $id ULID của subtask (primary key)
 * @property string $tenant_id ID tenant
 * @property string $task_id ID task cha
 * @property string $name Tên subtask
 * @property string|null $description Mô tả
 * @property string $status Trạng thái
 * @property string $priority Độ ưu tiên
 * @property string|null $assignee_id ID người được assign
 * @property string $created_by ID người tạo
 * @property \Carbon\Carbon|null $start_date Ngày bắt đầu
 * @property \Carbon\Carbon|null $end_date Ngày kết thúc
 * @property float $estimated_hours Số giờ ước tính
 * @property float $actual_hours Số giờ thực tế
 * @property float $progress_percent Tiến độ %
 * @property array|null $tags Tags
 * @property int $sort_order Thứ tự sắp xếp
 */
class Subtask extends Model
{
    use HasUlids, HasFactory, BelongsToTenant, SoftDeletes;
    
    protected $table = 'subtasks';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'tenant_id',
        'task_id',
        'name',
        'description',
        'status',
        'priority',
        'assignee_id',
        'created_by',
        'start_date',
        'end_date',
        'estimated_hours',
        'actual_hours',
        'progress_percent',
        'tags',
        'sort_order'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'estimated_hours' => 'float',
        'actual_hours' => 'float',
        'progress_percent' => 'float',
        'tags' => 'array',
        'sort_order' => 'integer'
    ];

    protected $attributes = [
        'status' => 'pending',
        'priority' => 'normal',
        'estimated_hours' => 0.0,
        'actual_hours' => 0.0,
        'progress_percent' => 0.0,
        'sort_order' => 0
    ];

    /**
     * Các trạng thái hợp lệ
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELED = 'canceled';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELED,
    ];

    /**
     * Các mức độ ưu tiên
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
     * Relationship: Subtask thuộc về tenant
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Relationship: Subtask thuộc về task
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Relationship: Subtask được assign cho user
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Relationship: Subtask được tạo bởi user
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Cập nhật tiến độ subtask
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
        
        // Update parent task progress if needed
        $this->updateParentTaskProgress();
    }

    /**
     * Cập nhật tiến độ task cha dựa trên subtasks
     */
    private function updateParentTaskProgress(): void
    {
        $task = $this->task;
        if (!$task) return;

        $subtasks = $task->subtasks;
        if ($subtasks->isEmpty()) return;

        $totalProgress = $subtasks->sum('progress_percent');
        $averageProgress = $totalProgress / $subtasks->count();
        
        $task->updateProgress($averageProgress);
    }

    /**
     * Scope: Lọc theo task
     */
    public function scopeForTask($query, string $taskId)
    {
        return $query->where('task_id', $taskId);
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
     * Scope: Sắp xếp theo thứ tự
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    /**
     * Tạo factory instance mới cho model
     */
    protected static function newFactory()
    {
        return \Database\Factories\SubtaskFactory::new();
    }
}