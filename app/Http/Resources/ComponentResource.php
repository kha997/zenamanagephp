<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

/**
 * Component API Resource
 * 
 * Transforms Component model for JSON responses according to JSend specification
 * 
 * @property \App\Models\Component $resource
 */
class ComponentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param \Illuminate\Http\Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'parent_component_id' => $this->parent_component_id,
            'name' => $this->name,
            'progress_percent' => (float) $this->progress_percent,
            'planned_cost' => (float) $this->planned_cost,
            'actual_cost' => (float) $this->actual_cost,
            
            // Computed fields
            'cost_variance' => $this->getCostVariance(),
            'cost_variance_percent' => $this->getCostVariancePercent(),
            'is_over_budget' => $this->isOverBudget(),
            'hierarchy_level' => $this->getHierarchyLevel(),
            
            // Relationships - conditional loading
            'project' => new ProjectResource($this->whenLoaded('project')),
            'parent_component' => new ComponentResource($this->whenLoaded('parentComponent')),
            'child_components' => ComponentResource::collection($this->whenLoaded('childComponents')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}