<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * TaskAssignment API Resource
 * 
 * Transforms TaskAssignment model for JSON responses according to JSend specification
 * 
 * @property \App\Models\TaskAssignment $resource
 */
class TaskAssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'task_id' => $this->task_id,
            'user_id' => $this->user_id,
            'split_percentage' => (float) $this->split_percentage,
            
            // Computed fields
            'workload_hours' => $this->getWorkloadHours(),
            'is_primary_assignee' => $this->isPrimaryAssignee(),
            
            // Relationships - conditional loading
            'task' => new TaskResource($this->whenLoaded('task')),
            'user' => new UserResource($this->whenLoaded('user')),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}