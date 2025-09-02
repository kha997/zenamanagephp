<?php declare(strict_types=1);

namespace Src\Compensation\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Src\CoreProject\Resources\TaskResource;
use Src\CoreProject\Resources\UserResource;

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
            'split_percent' => (float) $this->split_percent,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships (loaded when available)
            'task' => new TaskResource($this->whenLoaded('task')),
            'user' => new UserResource($this->whenLoaded('user')),
            'compensation' => new TaskCompensationResource($this->whenLoaded('taskCompensation')),
            
            // Computed properties
            'compensation_value' => $this->calculateCompensationValue(),
            'has_compensation' => $this->hasCompensation(),
            'assignment_status' => $this->getAssignmentStatus(),
            'split_label' => $this->getSplitLabel(),
        ];
    }
    
    /**
     * Get assignment status based on compensation
     * 
     * @return string
     */
    private function getAssignmentStatus(): string
    {
        if (!$this->hasCompensation()) {
            return 'no_compensation';
        }
        
        $compensation = $this->taskCompensation;
        
        if ($compensation && $compensation->is_locked) {
            return 'locked';
        }
        
        if ($compensation && $compensation->final_compensation > 0) {
            return 'calculated';
        }
        
        return 'pending';
    }
    
    /**
     * Get split percentage label
     * 
     * @return string
     */
    private function getSplitLabel(): string
    {
        $percent = $this->split_percent;
        
        if ($percent == 100) {
            return 'Full Assignment';
        } elseif ($percent >= 50) {
            return 'Primary Assignment';
        } elseif ($percent >= 25) {
            return 'Secondary Assignment';
        } else {
            return 'Minor Assignment';
        }
    }
}