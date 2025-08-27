<?php declare(strict_types=1);

namespace Src\WorkTemplate\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

/**
 * Apply Template Result Resource
 * 
 * Transform kết quả của việc apply template vào project
 * Bao gồm thống kê và danh sách items được tạo
 */
class ApplyTemplateResultResource extends JsonResource
{
    /**
     * Transform resource thành array
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'template_id' => $this->resource['template_id'],
            'project_id' => $this->resource['project_id'],
            'apply_mode' => $this->resource['apply_mode'], // 'full' hoặc 'partial'
            
            // Kết quả apply
            'result' => [
                'phases_created' => count($this->resource['created_phases'] ?? []),
                'tasks_created' => count($this->resource['created_tasks'] ?? []),
                'tasks_updated' => count($this->resource['updated_tasks'] ?? []),
                'conditional_tags_applied' => count($this->resource['conditional_tags'] ?? [])
            ],
            
            // Chi tiết items được tạo/cập nhật
            'created_phases' => ProjectPhaseResource::collection(
                $this->resource['created_phases'] ?? []
            ),
            'created_tasks' => ProjectTaskResource::collection(
                $this->resource['created_tasks'] ?? []
            ),
            'updated_tasks' => ProjectTaskResource::collection(
                $this->resource['updated_tasks'] ?? []
            ),
            
            // Conditional tags info
            'conditional_tags' => $this->resource['conditional_tags'] ?? [],
            
            // Timing info
            'applied_at' => $this->resource['applied_at'],
            'applied_by' => $this->resource['applied_by']
        ];
    }
    
    /**
     * Customize response wrapper theo JSend format
     */
    public function with($request): array
    {
        return [
            'status' => 'success',
            'message' => 'Template applied successfully'
        ];
    }
}