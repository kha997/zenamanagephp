<?php declare(strict_types=1);

namespace Src\WorkTemplate\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

/**
 * Template API Resource
 * 
 * Transform Template model thành JSON response theo chuẩn JSend
 * Bao gồm thông tin cơ bản, statistics và relationships
 */
class TemplateResource extends JsonResource
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
            'id' => $this->id,
            'template_name' => $this->template_name,
            'category' => $this->category,
            'version' => $this->version,
            'is_active' => $this->is_active,
            
            // Statistics từ computed attributes
            'statistics' => [
                'total_tasks' => $this->total_tasks,
                'estimated_duration' => $this->estimated_duration,
                'phases_count' => count($this->json_body['phases'] ?? []),
                'usage_count' => $this->project_phases_count ?? 0
            ],
            
            // Template structure (có thể ẩn để giảm payload)
            'structure' => $this->when(
                $request->get('include_structure', false),
                $this->json_body
            ),
            
            // Audit fields
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships (chỉ load khi cần)
            'versions' => TemplateVersionResource::collection(
                $this->whenLoaded('versions')
            ),
            'project_phases' => ProjectPhaseResource::collection(
                $this->whenLoaded('projectPhases')
            )
        ];
    }
    
    /**
     * Customize response wrapper theo JSend format
     */
    public function with($request): array
    {
        return [
            'status' => 'success',
            'message' => 'Template retrieved successfully'
        ];
    }
}