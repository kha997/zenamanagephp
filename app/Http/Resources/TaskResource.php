<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Task API Resource
 * 
 * Transforms Task model for JSON responses according to JSend specification
 * 
 * @property \App\Models\Task $resource
 */
class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'component_id' => $this->component_id,
            'phase_id' => $this->phase_id,
            'name' => $this->name,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
            'dependencies' => $this->dependency_ids,
            'conditional_tag' => $this->conditional_tag,
            'is_hidden' => (bool) $this->is_hidden,
            
            // Computed fields
            'duration_days' => $this->getDurationDays(),
            'is_overdue' => $this->isOverdue(),
            'can_start' => $this->canStart(),
            'dependency_status' => $this->getDependencyStatus(),
            
            // Relationships - conditional loading
            'project' => new ProjectResource($this->whenLoaded('project')),
            'component' => new ComponentResource($this->whenLoaded('component')),
            'assignments' => TaskAssignmentResource::collection($this->whenLoaded('assignments')),
            'interaction_logs' => InteractionLogResource::collection($this->whenLoaded('interactionLogs')),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
