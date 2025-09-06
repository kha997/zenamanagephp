<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use App\Http\Resources\BaseApiResource;

/**
 * Component API Resource
 * Transform Component model data for API responses
 */
class ComponentResource extends BaseApiResource
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
            'ulid' => $this->formatUlid($this->ulid),
            'project_id' => $this->project_id,
            'parent_component_id' => $this->parent_component_id,
            'name' => $this->name,
            'description' => $this->description,
            'progress_percent' => $this->formatDecimal($this->progress_percent),
            'planned_cost' => $this->formatDecimal($this->planned_cost),
            'actual_cost' => $this->formatDecimal($this->actual_cost),
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Relationships
            'project' => $this->includeRelationship('project', ProjectResource::class),
            'parent_component' => $this->includeRelationship('parentComponent', ComponentResource::class),
            'child_components' => $this->includeRelationship('childComponents', ComponentResource::class),
            'tasks' => $this->includeRelationship('tasks', TaskResource::class),
            
            // Computed properties
            'cost_variance' => $this->formatDecimal($this->getCostVariance()),
            'has_children' => $this->hasChildren(),
            'level' => $this->getLevel(),
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
     * Check if component has children
     */
    private function hasChildren(): bool
    {
        return $this->childComponents()->exists();
    }
    
    /**
     * Get component level in hierarchy
     */
    private function getLevel(): int
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