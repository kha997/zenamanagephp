<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TaskAssignment API Resource
 * Transform TaskAssignment model data for API responses
 */
class TaskAssignmentResource extends JsonResource
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
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'split_percentage' => (float) $this->split_percentage,
            'role' => $this->role,
            'role_label' => $this->getRoleLabel(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships
            'task' => new TaskResource($this->whenLoaded('task')),
            'user' => new UserResource($this->whenLoaded('user')),
            
            // Computed properties
            'is_primary' => $this->role === 'primary',
            'estimated_hours' => $this->getEstimatedHours(),
            'actual_hours' => $this->getActualHours(),
        ];
    }
    
    /**
     * Get role label
     */
    private function getRoleLabel(): string
    {
        $roles = [
            'primary' => 'Chính',
            'secondary' => 'Phụ',
            'reviewer' => 'Kiểm duyệt',
            'observer' => 'Theo dõi'
        ];
        
        return $roles[$this->role] ?? $this->role;
    }
    
    /**
     * Get estimated hours for this assignment
     */
    private function getEstimatedHours(): float
    {
        if (!$this->task) {
            return 0.0;
        }
        
        return (float) ($this->task->estimated_hours * $this->split_percentage / 100);
    }
    
    /**
     * Get actual hours for this assignment
     */
    private function getActualHours(): float
    {
        if (!$this->task) {
            return 0.0;
        }
        
        return (float) ($this->task->actual_hours * $this->split_percentage / 100);
    }
}