<?php declare(strict_types=1);

namespace Src\Compensation\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Src\CoreProject\Resources\TaskResource;
use Src\CoreProject\Resources\UserResource;

/**
 * TaskCompensation API Resource
 * Transform TaskCompensation model data for API responses
 */
class TaskCompensationResource extends JsonResource
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
            'contract_id' => $this->contract_id,
            'base_value' => (float) $this->base_value,
            'efficiency_percent' => (float) $this->efficiency_percent,
            'final_compensation' => (float) $this->final_compensation,
            'is_locked' => $this->is_locked,
            'locked_at' => $this->locked_at?->toISOString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships (loaded when available)
            'task' => new TaskResource($this->whenLoaded('task')),
            'user' => new UserResource($this->whenLoaded('user')),
            'contract' => new ContractResource($this->whenLoaded('contract')),
            
            // Computed properties
            'can_edit' => !$this->is_locked,
            'efficiency_label' => $this->getEfficiencyLabel(),
            'compensation_status' => $this->getCompensationStatus(),
        ];
    }
    
    /**
     * Get efficiency label based on percentage
     * 
     * @return string
     */
    private function getEfficiencyLabel(): string
    {
        if ($this->efficiency_percent >= 100) {
            return 'Excellent';
        } elseif ($this->efficiency_percent >= 80) {
            return 'Good';
        } elseif ($this->efficiency_percent >= 60) {
            return 'Average';
        } else {
            return 'Below Average';
        }
    }
    
    /**
     * Get compensation status
     * 
     * @return string
     */
    private function getCompensationStatus(): string
    {
        if ($this->is_locked) {
            return 'locked';
        }
        
        if ($this->final_compensation > 0) {
            return 'calculated';
        }
        
        return 'pending';
    }
}