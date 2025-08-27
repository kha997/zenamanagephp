<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Task API Resource
 * Transform Task model data for API responses
 */
class TaskResource extends JsonResource
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
            'project_id' => $this->project_id,
            'component_id' => $this->component_id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'priority' => $this->priority,
            'priority_label' => $this->getPriorityLabel(),
            'dependencies' => $this->dependencies,
            'conditional_tag' => $this->conditional_tag,
            'is_hidden' => $this->is_hidden,
            'estimated_hours' => (float) $this->estimated_hours,
            'actual_hours' => (float) $this->actual_hours,
            'progress_percent' => (float) $this->progress_percent,
            'tags' => $this->tags,
            'visibility' => $this->visibility,
            'client_approved' => $this->client_approved,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships
            'project' => new ProjectResource($this->whenLoaded('project')),
            'component' => new ComponentResource($this->whenLoaded('component')),
            'assignments' => TaskAssignmentResource::collection($this->whenLoaded('assignments')),
            'assigned_users' => UserResource::collection($this->whenLoaded('assignedUsers')),
            
            // Counts
            'assignments_count' => $this->whenCounted('assignments'),
            
            // Computed properties
            'duration_days' => $this->getDurationDays(),
            'hours_variance' => $this->getHoursVariance(),
            'can_start' => $this->canStart(),
            'is_overdue' => $this->isOverdue(),
            'completion_percentage' => $this->getCompletionPercentage(),
        ];
    }
    
    /**
     * Get status label
     */
    private function getStatusLabel(): string
    {
        return static::STATUSES[$this->status] ?? $this->status;
    }
    
    /**
     * Get priority label
     */
    private function getPriorityLabel(): string
    {
        return static::PRIORITIES[$this->priority] ?? $this->priority;
    }
    
    /**
     * Get task duration in days
     */
    private function getDurationDays(): ?int
    {
        if (!$this->start_date || !$this->end_date) {
            return null;
        }
        
        return $this->start_date->diffInDays($this->end_date);
    }
    
    /**
     * Get hours variance (actual - estimated)
     */
    private function getHoursVariance(): float
    {
        return (float) ($this->actual_hours - $this->estimated_hours);
    }
    
    /**
     * Get completion percentage based on status and progress
     */
    private function getCompletionPercentage(): float
    {
        if ($this->status === 'completed') {
            return 100.0;
        }
        
        return (float) $this->progress_percent;
    }
}