<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Component API Resource
 * Transform Component model data for API responses
 */
class ComponentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'project_id' => $this->project_id,
            'parent_component_id' => $this->parent_component_id,
            'name' => $this->name,
            'description' => $this->description,
            'progress_percent' => (float) $this->progress_percent,
            'planned_cost' => (float) $this->planned_cost,
            'actual_cost' => (float) $this->actual_cost,
            'tags' => $this->tags,
            'visibility' => $this->visibility,
            'client_approved' => $this->client_approved,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships
            'project' => new ProjectResource($this->whenLoaded('project')),
            'parent_component' => new ComponentResource($this->whenLoaded('parentComponent')),
            'child_components' => ComponentResource::collection($this->whenLoaded('childComponents')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            
            // Counts
            'child_components_count' => $this->whenCounted('childComponents'),
            'tasks_count' => $this->whenCounted('tasks'),
            
            // Computed properties
            'is_root' => $this->parent_component_id === null,
            'has_children' => $this->child_components_count > 0,
            'cost_variance' => $this->getCostVariance(),
            'hierarchy_level' => $this->getHierarchyLevel(),
        ];
    }
    
    /**
     * Get cost variance (actual - planned)
     */
    private function getCostVariance(): float
    {
        return (float) ($this->actual_cost - $this->planned_cost);
    }
    
    /**
     * Get hierarchy level (0 for root, 1 for first level children, etc.)
     */
    private function getHierarchyLevel(): int
    {
        $level = 0;
        $parent = $this->parentComponent;
        
        while ($parent) {
            $level++;
            $parent = $parent->parentComponent;
        }
        
        return $level;
    }
}