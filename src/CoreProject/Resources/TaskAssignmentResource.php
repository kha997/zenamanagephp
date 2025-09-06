<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use App\Http\Resources\BaseApiResource;

/**
 * Task Assignment API Resource
 * Transform TaskAssignment model data for API responses
 */
class TaskAssignmentResource extends BaseApiResource
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
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'split_percentage' => $this->formatDecimal($this->split_percentage),
            'assigned_at' => $this->formatDateTime($this->assigned_at),
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Relationships
            'task' => $this->includeRelationship('task', TaskResource::class),
            'user' => $this->includeRelationship('user', UserResource::class),
            
            // Computed properties
            'estimated_hours' => $this->formatDecimal($this->getEstimatedHours()),
            'actual_hours' => $this->formatDecimal($this->getActualHours()),
        ];
    }
    
    /**
     * Get estimated hours for this assignment
     */
    private function getEstimatedHours(): float
    {
        if (!$this->task || !$this->task->estimated_hours) {
            return 0.0;
        }
        
        return (float) ($this->task->estimated_hours * $this->split_percentage / 100);
    }
    
    /**
     * Get actual hours for this assignment
     */
    private function getActualHours(): float
    {
        if (!$this->task || !$this->task->actual_hours) {
            return 0.0;
        }
        
        return (float) ($this->task->actual_hours * $this->split_percentage / 100);
    }
}