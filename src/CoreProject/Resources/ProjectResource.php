<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Project API Resource
 * Transform Project model data for API responses
 */
class ProjectResource extends JsonResource
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
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'progress' => (float) $this->progress,
            'planned_cost' => (float) $this->planned_cost,
            'actual_cost' => (float) $this->actual_cost,
            'tags' => $this->tags,
            'visibility' => $this->visibility,
            'client_approved' => $this->client_approved,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships (loaded when available)
            'components_count' => $this->whenCounted('components'),
            'tasks_count' => $this->whenCounted('tasks'),
            'root_components' => ComponentResource::collection($this->whenLoaded('rootComponents')),
            'components' => ComponentResource::collection($this->whenLoaded('components')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            
            // Computed properties
            'is_active' => $this->isActive(),
            'is_completed' => $this->isCompleted(),
            'duration_days' => $this->getDurationDays(),
            'cost_variance' => $this->getCostVariance(),
        ];
    }
    
    /**
     * Get status label
     */
    private function getStatusLabel(): string
    {
        return static::STATUSES[$this->status] ?? $this->status;
    }
    
    /**
     * Get project duration in days
     */
    private function getDurationDays(): ?int
    {
        if (!$this->start_date || !$this->end_date) {
            return null;
        }
        
        return $this->start_date->diffInDays($this->end_date);
    }
    
    /**
     * Get cost variance (actual - planned)
     */
    private function getCostVariance(): float
    {
        return (float) ($this->actual_cost - $this->planned_cost);
    }
}