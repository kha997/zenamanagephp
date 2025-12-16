<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProjectTaskResource
 * 
 * Round 206: API resource for ProjectTask model
 * Round 213: Added assignee_id for task assignment
 * Round 217: Added project relationship and phase_label for My Tasks grouping
 */
class ProjectTaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'project_id' => $this->project_id,
            'phase_id' => $this->phase_id,
            'template_task_id' => $this->template_task_id,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_milestone' => $this->is_milestone,
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'is_completed' => $this->is_completed,
            'completed_at' => $this->completed_at?->toISOString(),
            'duration_days' => $this->duration_days,
            'progress_percent' => $this->progress_percent,
            'conditional_tag' => $this->conditional_tag,
            'is_hidden' => $this->is_hidden,
            'template_id' => $this->template_id,
            'metadata' => $this->metadata,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'assignee_id' => $this->assignee_id, // Round 213: Task assignment
            'phase_label' => $this->phase_label, // Round 217: For grouping in My Tasks
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            // Round 217: Include project relationship for My Tasks grouping
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                    'code' => $this->project->code,
                    'status' => $this->project->status,
                ];
            }),
        ];
    }
}
