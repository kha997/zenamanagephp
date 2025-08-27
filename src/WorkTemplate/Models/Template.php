<?php declare(strict_types=1);

namespace Src\WorkTemplate\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Src\Foundation\Traits\HasAuditFields;

/**
 * Template Model
 * 
 * Quản lý các mẫu công việc với versioning và categorization
 * Hỗ trợ JSON structure cho phases và tasks
 * 
 * @property string $template_name
 * @property string $category
 * @property array $json_body
 * @property int $version
 * @property bool $is_active
 * @property string|null $created_by
 * @property string|null $updated_by
 */
class Template extends Model
{
    use HasFactory, HasUlids, HasAuditFields, SoftDeletes;

    protected $table = 'templates';
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Các trường có thể mass assignment
     */
    protected $fillable = [
        'template_name',
        'category',
        'json_body',
        'version',
        'is_active',
        'created_by',
        'updated_by'
    ];

    /**
     * Các trường cần cast kiểu dữ liệu
     */
    protected $casts = [
        'json_body' => 'array',
        'is_active' => 'boolean',
        'version' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Các category được phép
     */
    public const CATEGORIES = [
        'Design',
        'Construction', 
        'QC',
        'Inspection'
    ];

    /**
     * Relationship với template versions
     * Một template có nhiều versions để theo dõi lịch sử thay đổi
     */
    public function versions(): HasMany
    {
        return $this->hasMany(TemplateVersion::class, 'template_id')
                    ->orderBy('version', 'desc');
    }

    /**
     * Relationship với project phases được tạo từ template này
     */
    public function projectPhases(): HasMany
    {
        return $this->hasMany(ProjectPhase::class, 'template_id');
    }

    /**
     * Relationship với project tasks được tạo từ template này
     */
    public function projectTasks(): HasMany
    {
        return $this->hasMany(ProjectTask::class, 'template_id');
    }

    /**
     * Scope để lấy các template đang active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope để lọc theo category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Tạo version mới khi update template
     * Đảm bảo không mất dữ liệu lịch sử
     */
    public function createNewVersion(array $jsonBody, string $note = null, string $createdBy = null): TemplateVersion
    {
        $newVersion = $this->version + 1;
        
        // Tạo version mới
        $templateVersion = $this->versions()->create([
            'version' => $newVersion,
            'json_body' => $jsonBody,
            'note' => $note,
            'created_by' => $createdBy
        ]);
        
        // Cập nhật template với version và json_body mới
        $this->update([
            'version' => $newVersion,
            'json_body' => $jsonBody,
            'updated_by' => $createdBy
        ]);
        
        return $templateVersion;
    }

    /**
     * Validate JSON structure của template
     * Đảm bảo có đúng format phases và tasks
     */
    public function validateJsonStructure(array $jsonBody): bool
    {
        // Kiểm tra có template_name
        if (!isset($jsonBody['template_name'])) {
            return false;
        }
        
        // Kiểm tra có phases array
        if (!isset($jsonBody['phases']) || !is_array($jsonBody['phases'])) {
            return false;
        }
        
        // Kiểm tra structure của từng phase
        foreach ($jsonBody['phases'] as $phase) {
            if (!isset($phase['name']) || !isset($phase['tasks']) || !is_array($phase['tasks'])) {
                return false;
            }
            
            // Kiểm tra structure của từng task
            foreach ($phase['tasks'] as $task) {
                $requiredFields = ['name', 'duration_days', 'role', 'contract_value_percent'];
                foreach ($requiredFields as $field) {
                    if (!isset($task[$field])) {
                        return false;
                    }
                }
            }
        }
        
        return true;
    }

    /**
     * Lấy tổng số tasks trong template
     */
    public function getTotalTasksAttribute(): int
    {
        $total = 0;
        if (isset($this->json_body['phases'])) {
            foreach ($this->json_body['phases'] as $phase) {
                if (isset($phase['tasks'])) {
                    $total += count($phase['tasks']);
                }
            }
        }
        return $total;
    }

    /**
     * Lấy tổng duration của template (tính theo critical path)
     */
    public function getEstimatedDurationAttribute(): int
    {
        $totalDuration = 0;
        if (isset($this->json_body['phases'])) {
            foreach ($this->json_body['phases'] as $phase) {
                $phaseDuration = 0;
                if (isset($phase['tasks'])) {
                    foreach ($phase['tasks'] as $task) {
                        $phaseDuration = max($phaseDuration, $task['duration_days'] ?? 0);
                    }
                }
                $totalDuration += $phaseDuration;
            }
        }
        return $totalDuration;
    }
}