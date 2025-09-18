<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Project API Resource
 * 
 * Transforms Project model for JSON responses according to JSend specification
 * 
 * @property \App\Models\Project $resource
 */
class ProjectResource extends JsonResource
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
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
            'progress' => (float) $this->progress,
            'actual_cost' => (float) $this->actual_cost,
            
            // Computed fields
            'duration_days' => $this->getDurationDays(),
            'is_overdue' => $this->isOverdue(),
            'completion_status' => $this->getCompletionStatus(),
            
            // Relationships - conditional loading
            'components' => ComponentResource::collection($this->whenLoaded('components')),
            'tasks' => TaskResource::collection($this->whenLoaded('tasks')),
            'change_requests' => ChangeRequestResource::collection($this->whenLoaded('changeRequests')),
            'interaction_logs' => InteractionLogResource::collection($this->whenLoaded('interactionLogs')),
            'baselines' => BaselineResource::collection($this->whenLoaded('baselines')),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}