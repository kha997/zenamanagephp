<?php declare(strict_types=1);

namespace Src\CoreProject\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Src\Foundation\Traits\HasTimestamps;
use Src\Foundation\Traits\HasOwnership;
use Src\Foundation\Traits\HasTags;
use Src\Foundation\Events\EventBus;

/**
 * Model WorkTemplate - Mẫu công việc
 * 
 * @property string $id ULID của template (primary key)
 * @property string $name Tên template
 * @property string|null $description Mô tả
 * @property string $category Loại template
 * @property array $template_data Dữ liệu template
 * @property int $version Phiên bản
 * @property bool $is_active Trạng thái active
 */
class WorkTemplate extends Model
{
    use HasUlids, HasTimestamps, HasOwnership, HasTags;

    protected $table = 'work_templates';
    
    // Cấu hình ULID primary key
    protected $keyType = 'string';
    public $incrementing = false;
    
    protected $fillable = [
        'name',
        'description',
        'category',
        'template_data',
        'version',
        'is_active',
        'tags'
    ];

    protected $casts = [
        'template_data' => 'array',
        'version' => 'integer',
        'is_active' => 'boolean',
        'tags' => 'array'
    ];

    protected $attributes = [
        'version' => 1,
        'is_active' => true
    ];

    /**
     * Các category hợp lệ
     */
    public const CATEGORIES = [
        'design' => 'Thiết kế',
        'construction' => 'Thi công',
        'qc' => 'Kiểm soát chất lượng',
        'inspection' => 'Nghiệm thu'
    ];

    /**
     * Tạo tasks từ template cho project
     */
    public function createTasksForProject(Project $project, ?Component $component = null): array
    {
        $createdTasks = [];
        $taskMapping = []; // Map template task IDs to actual task ULIDs
        
        foreach ($this->template_data['tasks'] ?? [] as $taskData) {
            $task = Task::create([
                'project_id' => $project->id,
                'component_id' => $component?->id,
                'name' => $taskData['name'],
                'description' => $taskData['description'] ?? null,
                'estimated_hours' => $taskData['estimated_hours'] ?? 0,
                'priority' => $taskData['priority'] ?? 'normal',
                'conditional_tag' => $taskData['conditional_tag'] ?? null,
                'tags' => $taskData['tags'] ?? []
            ]);
            
            $createdTasks[] = $task;
            $taskMapping[$taskData['id']] = $task->ulid;
        }
        
        // Update dependencies with actual ULIDs
        foreach ($this->template_data['tasks'] ?? [] as $index => $taskData) {
            if (!empty($taskData['dependencies'])) {
                $actualDependencies = [];
                foreach ($taskData['dependencies'] as $depId) {
                    if (isset($taskMapping[$depId])) {
                        $actualDependencies[] = $taskMapping[$depId];
                    }
                }
                
                if (!empty($actualDependencies)) {
                    $createdTasks[$index]->update([
                        'dependencies' => $actualDependencies
                    ]);
                }
            }
        }
        
        // Dispatch event
        EventBus::dispatch('WorkTemplate.Applied', [
            'template_id' => $this->ulid,
            'project_id' => $project->ulid,
            'component_id' => $component?->ulid,
            'tasks_created' => count($createdTasks),
            'actor_id' => auth()->id() ?? 'system'
        ]);
        
        return $createdTasks;
    }

    /**
     * Validate template data structure
     */
    public function validateTemplateData(): bool
    {
        $data = $this->template_data;
        
        if (!isset($data['tasks']) || !is_array($data['tasks'])) {
            return false;
        }
        
        foreach ($data['tasks'] as $task) {
            if (!isset($task['id']) || !isset($task['name'])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Scope: Lọc theo category
     */
    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: Chỉ lấy templates active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Lấy version mới nhất của template
     */
    public function scopeLatestVersion($query, string $name)
    {
        return $query->where('name', $name)
                    ->orderBy('version', 'desc')
                    ->limit(1);
    }
}