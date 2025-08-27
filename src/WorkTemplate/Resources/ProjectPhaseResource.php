<?php declare(strict_types=1);

namespace Src\WorkTemplate\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

/**
 * Project Phase API Resource
 * 
 * Transform ProjectPhase model thành JSON response
 * Bao gồm progress calculation và task statistics
 */
class ProjectPhaseResource extends JsonResource
{
    /**
     * Transform resource thành array
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'name' => $this->name,
            'order' => $this->order,
            
            // Template reference
            'template_id' => $this->template_id,
            'template_phase_id' => $this->template_phase_id,
            'is_from_template' => $this->isFromTemplate(),
            
            // Progress và statistics
            'progress_percent' => $this->progress_percent,
            'statistics' => [
                'task_count' => $this->task_count,
                'visible_task_count' => $this->visible_task_count,
                'estimated_duration' => $this->estimated_duration,
                'completed_tasks' => $this->tasks()->where('status', 'completed')->count()
            ],
            
            // Audit fields
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'tasks' => ProjectTaskResource::collection(
                $this->whenLoaded('tasks')
            ),
            'visible_tasks' => ProjectTaskResource::collection(
                $this->whenLoaded('visibleTasks')
            ),
            'template' => new TemplateResource(
                $this->whenLoaded('template')
            )
        ];
    }
}