<?php declare(strict_types=1);

namespace Src\WorkTemplate\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

/**
 * Project Task API Resource
 * 
 * Transform ProjectTask model thành JSON response
 * Bao gồm status, progress và conditional tag information
 */
class ProjectTaskResource extends JsonResource
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
            'phase_id' => $this->phase_id,
            'name' => $this->name,
            'description' => $this->description,
            
            // Task properties
            'duration_days' => $this->duration_days,
            'progress_percent' => $this->progress_percent,
            'status' => $this->status,
            
            // Conditional visibility
            'conditional_tag' => $this->conditional_tag,
            'is_hidden' => $this->is_hidden,
            'has_conditional_tag' => $this->hasConditionalTag(),
            
            // Template reference
            'template_id' => $this->template_id,
            'template_task_id' => $this->template_task_id,
            'is_from_template' => $this->isFromTemplate(),
            
            // Status helpers
            'status_info' => [
                'is_completed' => $this->isCompleted(),
                'is_in_progress' => $this->isInProgress(),
                'available_statuses' => $this->when(
                    $request->get('include_status_options', false),
                    self::getAvailableStatuses()
                )
            ],
            
            // Audit fields
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Relationships
            'phase' => new ProjectPhaseResource(
                $this->whenLoaded('phase')
            ),
            'template' => new TemplateResource(
                $this->whenLoaded('template')
            )
        ];
    }
}