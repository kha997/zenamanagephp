<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use App\Http\Resources\BaseApiResource;

/**
 * Project API Resource
 * Transform Project model data for API responses
 */
class ProjectResource extends BaseApiResource
{
    private const STATUSES = [
        'planning' => 'Planning',
        'active' => 'Active',
        'in_progress' => 'In Progress',
        'on_hold' => 'On Hold',
        'completed' => 'Completed',
        'cancelled' => 'Cancelled',
    ];

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
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->formatDate($this->start_date),
            'end_date' => $this->formatDate($this->end_date),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'progress' => $this->formatDecimal($this->progress),
            'planned_cost' => $this->formatDecimal($this->planned_cost),
            'actual_cost' => $this->formatDecimal($this->actual_cost),
            'tags' => $this->tags,
            'visibility' => $this->visibility,
            'client_approved' => $this->client_approved,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Relationships (loaded when available)
            'components_count' => $this->whenCounted('components'),
            'tasks_count' => $this->whenCounted('tasks'),
            'root_components' => $this->includeRelationship('rootComponents', ComponentResource::class),
            'components' => $this->includeRelationship('components', ComponentResource::class),
            'tasks' => $this->includeRelationship('tasks', TaskResource::class),
            
            // Computed properties
            'is_active' => $this->isActive(),
            'is_completed' => $this->isCompleted(),
            'duration_days' => $this->getDurationDays(),
            'cost_variance' => $this->formatDecimal($this->getCostVariance()),
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

    private function isActive(): bool
    {
        return in_array($this->status, ['active', 'in_progress'], true);
    }

    private function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
