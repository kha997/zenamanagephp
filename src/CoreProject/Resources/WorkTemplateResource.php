<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use App\Http\Resources\BaseApiResource;

/**
 * Work Template API Resource
 * Transform WorkTemplate model data for API responses
 */
class WorkTemplateResource extends BaseApiResource
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
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'category_label' => $this->getCategoryLabel(),
            'template_data' => $this->template_data,
            'version' => $this->version,
            'is_active' => $this->is_active,
            'tags' => $this->tags,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Computed properties
            'tasks_count' => $this->getTasksCount(),
            'estimated_duration' => $this->getEstimatedDuration(),
            'total_estimated_hours' => $this->formatDecimal($this->getTotalEstimatedHours()),
        ];
    }
    
    /**
     * Get category label
     */
    private function getCategoryLabel(): string
    {
        return static::CATEGORIES[$this->category] ?? $this->category;
    }
    
    /**
     * Get number of tasks in template
     */
    private function getTasksCount(): int
    {
        return count($this->template_data['tasks'] ?? []);
    }
    
    /**
     * Get estimated duration from template data
     */
    private function getEstimatedDuration(): ?int
    {
        return $this->template_data['estimated_duration'] ?? null;
    }
    
    /**
     * Get total estimated hours from all tasks
     */
    private function getTotalEstimatedHours(): float
    {
        $tasks = $this->template_data['tasks'] ?? [];
        $totalHours = 0.0;
        
        foreach ($tasks as $task) {
            $totalHours += (float) ($task['estimated_hours'] ?? 0);
        }
        
        return $totalHours;
    }
}