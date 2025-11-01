<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ProjectPhase Model
 * 
 * Quản lý các giai đoạn (phases) trong dự án
 * Được tạo từ template hoặc thêm thủ công
 * 
 * @property string $id
 * @property string $project_id
 * @property string $name
 * @property int $order
 * @property string|null $template_id
 * @property string|null $template_phase_id
 * @property string|null $created_by
 * @property string|null $updated_by
 */
class ProjectPhase extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'project_phases';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Các trường có thể mass assignment
     */
    protected $fillable = [
        'project_id',
        'name',
        'order',
        'template_id',
        'template_phase_id',
        'created_by',
        'updated_by'
    ];

    /**
     * Các trường cần cast kiểu dữ liệu
     */
    protected $casts = [
        'order' => 'integer',
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
     * Relationship với template nếu được tạo từ template
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class, 'template_id');
    }

    /**
     * Relationship với các tasks trong phase này
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'phase_id')
                    ->orderBy('created_at');
    }

    /**
     * Relationship với các tasks visible (không bị ẩn bởi conditional tags)
     */
    public function visibleTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'phase_id')
                    ->where('is_hidden', false)
                    ->orderBy('created_at');
    }

    /**
     * Scope để sắp xếp theo thứ tự
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order');
    }

    /**
     * Scope để lọc theo project
     */
    public function scopeByProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    /**
     * Tính progress của phase dựa trên tasks
     */
    public function getProgressPercentAttribute(): float
    {
        $visibleTasks = $this->visibleTasks;
        
        if ($visibleTasks->count() === 0) {
            return 0.0;
        }
        
        $totalProgress = $visibleTasks->sum('progress_percent');
        return round($totalProgress / $visibleTasks->count(), 2);
    }

    /**
     * Kiểm tra xem phase có được tạo từ template không
     */
    public function isFromTemplate(): bool
    {
        return !is_null($this->template_id);
    }

    /**
     * Lấy số lượng tasks trong phase
     */
    public function getTaskCountAttribute(): int
    {
        return $this->tasks()->count();
    }

    /**
     * Lấy số lượng tasks visible trong phase
     */
    public function getVisibleTaskCountAttribute(): int
    {
        return $this->visibleTasks()->count();
    }

    /**
     * Lấy duration ước tính của phase
     */
    public function getEstimatedDurationAttribute(): int
    {
        return $this->visibleTasks()->sum('duration_days');
    }

    /**
     * Tạo factory instance mới cho model
     * 
     * @return \Database\Factories\Src\WorkTemplate\Models\ProjectPhaseFactory
     */
    protected static function newFactory()
    {
        return \Database\Factories\Src\WorkTemplate\Models\ProjectPhaseFactory::new();
    }
}