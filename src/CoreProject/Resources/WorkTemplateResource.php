<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * WorkTemplate API Resource
 * Transform WorkTemplate model data for API responses
 */
class WorkTemplateResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'category_label' => $this->getCategoryLabel(),
            'template_data' => $this->template_data,
            'version' => $this->version,
            'is_active' => $this->is_active,
            'tags' => $this->tags,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Computed properties
            'tasks_count' => $this->getTasksCount(),
            'estimated_duration' => $this->getEstimatedDuration(),
            'total_estimated_hours' => $this->getTotalEstimatedHours(),
        ];
    }
    
    /**
     * Get category label
     */
    private function getCategoryLabel(): string
    {
        $categories = [
            'design' => 'Thiết kế',
            'construction' => 'Thi công',
            'qc' => 'Kiểm soát chất lượng',
            'inspection' => 'Nghiệm thu'
        ];
        
        return $categories[$this->category] ?? $this->category;
    }
    
    /**
     * Get number of tasks in template
     */
    private function getTasksCount(): int
    {
        if (!isset($this->template_data['tasks']) || !is_array($this->template_data['tasks'])) {
            return 0;
        }
        
        return count($this->template_data['tasks']);
    }
    
    /**
     * Get estimated duration from template metadata
     */
    private function getEstimatedDuration(): ?int
    {
        return $this->template_data['metadata']['estimated_duration'] ?? null;
    }
    
    /**
     * Get total estimated hours from all tasks
     */
    private function getTotalEstimatedHours(): float
    {
        if (!isset($this->template_data['tasks']) || !is_array($this->template_data['tasks'])) {
            return 0.0;
        }
        
        $totalHours = 0.0;
        foreach ($this->template_data['tasks'] as $task) {
            $totalHours += (float) ($task['estimated_hours'] ?? 0);
        }
        
        return $totalHours;
    }
}