<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProjectTask Model
 * 
 * Quản lý các task trong dự án
 * Được tạo từ template hoặc thêm thủ công
 * Hỗ trợ conditional tags để ẩn/hiện task
 * 
 * @property string $id
 * @property string $project_id
 * @property string|null $phase_id
 * @property string $name
 * @property string|null $description
 * @property int $duration_days
 * @property float $progress_percent
 * @property string $status
 * @property string|null $conditional_tag
 * @property bool $is_hidden
 * @property string|null $template_id
 * @property string|null $template_task_id
 * @property string|null $created_by
 * @property string|null $updated_by
 */
class ProjectTask extends Model
{

    protected $table = 'project_tasks';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Các trạng thái task có thể có
     */
    const STATUS_PENDING = 'pending';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_ON_HOLD = 'on_hold';
    const STATUS_CANCELLED = 'cancelled';

    /**
     * Các trường có thể mass assignment
     */
    protected $fillable = [
        'project_id',
        'phase_id',
        'name',
        'description',
        'duration_days',
        'progress_percent',
        'status',
        'conditional_tag',
        'is_hidden',
        'template_id',
        'template_task_id',
        'created_by',
        'updated_by'
    ];

    /**
     * Các trường cần cast kiểu dữ liệu
     */
    protected $casts = [
        'duration_days' => 'integer',
        'progress_percent' => 'float',
        'is_hidden' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Relationship với project (giả sử có Project model)
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    /**
     * Relationship với phase
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'phase_id');
    }

    /**
     * Relationship với template nếu được tạo từ template
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    /**
     * Scope để lọc tasks visible (không bị ẩn)
     */
    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    /**
     * Scope để lọc theo project
     */
    public function scopeByProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Scope để lọc theo phase
     */
    public function scopeByPhase($query, string $phaseId)
    {
        return $query->where('phase_id', $phaseId);
    }

    /**
     * Scope để lọc theo status
     */
    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope để lọc theo conditional tag
     */
    public function scopeByConditionalTag($query, string $tag)
    {
        return $query->where('conditional_tag', $tag);
    }

    /**
     * Kiểm tra xem task có được tạo từ template không
     */
    public function isFromTemplate(): bool
    {
        return !is_null($this->template_id);
    }

    /**
     * Kiểm tra xem task có conditional tag không
     */
    public function hasConditionalTag(): bool
    {
        return !is_null($this->conditional_tag);
    }

    /**
     * Toggle visibility của task dựa trên conditional tag
     */
    public function toggleConditionalVisibility(bool $isVisible): void
    {
        $this->update([
            'is_hidden' => !$isVisible
        ]);
    }

    /**
     * Kiểm tra xem task có đang hoàn thành không
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Kiểm tra xem task có đang trong tiến trình không
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Lấy danh sách tất cả status có thể có
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_ON_HOLD,
            self::STATUS_CANCELLED
        ];
    }

    /**
     * Cập nhật progress và tự động thay đổi status nếu cần
     */
    public function updateProgress(float $progressPercent): void
    {
        $this->progress_percent = max(0, min(100, $progressPercent));
        
        // Tự động cập nhật status dựa trên progress
        if ($this->progress_percent == 0 && $this->status !== self::STATUS_PENDING) {
            $this->status = self::STATUS_PENDING;
        } elseif ($this->progress_percent > 0 && $this->progress_percent < 100 && $this->status === self::STATUS_PENDING) {
            $this->status = self::STATUS_IN_PROGRESS;
        } elseif ($this->progress_percent == 100) {
            $this->status = self::STATUS_COMPLETED;
        }
        
        $this->save();
    }

    /**
     * Tạo factory instance mới cho model
     * 
     * @return \Database\Factories\Src\WorkTemplate\Models\ProjectTaskFactory
     */
    protected static function newFactory()
    {
        return \Database\Factories\Src\WorkTemplate\Models\ProjectTaskFactory::new();
    }
}