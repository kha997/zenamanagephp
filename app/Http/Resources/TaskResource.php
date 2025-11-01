<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->name, // Standardize: name → title
            'description' => $this->description,
            'project_id' => $this->project_id,
            'assignee_id' => $this->assignee_id,
            'priority' => $this->priority,
            'status' => $this->status,
            'due_date' => $this->end_date?->toDateString(), // Standardize: end_date → due_date
            'start_date' => $this->start_date?->toDateString(),
            'estimated_hours' => $this->estimated_hours,
            'actual_hours' => $this->actual_hours,
            'progress_percent' => $this->progress_percent,
            'progress' => $this->progress_percent, // Alias for convenience
            'tags' => $this->tags,
            'dependencies' => $this->dependencies,
            'is_milestone' => $this->is_milestone ?? false,
            'requires_approval' => $this->requires_approval ?? false,
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                    'status' => $this->project->status,
                ];
            }),
            'assignee' => $this->whenLoaded('assignee', function () {
                return [
                    'id' => $this->assignee->id,
                    'name' => $this->assignee->name,
                    'email' => $this->assignee->email,
                ];
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email,
                ];
            }),
        ];
    }
}