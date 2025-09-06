<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use App\Http\Resources\BaseApiResource;

/**
 * Task API Resource
 * Transform Task model data for API responses
 */
class TaskResource extends BaseApiResource
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
            'ulid' => $this->formatUlid($this->ulid),
            'project_id' => $this->project_id,
            'component_id' => $this->component_id,
            'name' => $this->name,
            'description' => $this->description,
            'start_date' => $this->formatDate($this->start_date),
            'end_date' => $this->formatDate($this->end_date),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'priority' => $this->priority,
            'priority_label' => $this->getPriorityLabel(),
            'dependencies' => $this->dependencies,
            'conditional_tag' => $this->conditional_tag,
            'is_hidden' => $this->is_hidden,
            'estimated_hours' => $this->formatDecimal($this->estimated_hours),
            'actual_hours' => $this->formatDecimal($this->actual_hours),
            'progress_percent' => $this->formatDecimal($this->progress_percent),
            'tags' => $this->tags,
            'visibility' => $this->visibility,
            'client_approved' => $this->client_approved,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Relationships
            'project' => $this->includeRelationship('project', ProjectResource::class),
            'component' => $this->includeRelationship('component', ComponentResource::class),
            'assignments' => $this->includeRelationship('assignments', TaskAssignmentResource::class),
            'assigned_users' => $this->includeRelationship('assignedUsers', UserResource::class),
            
            // Computed properties
            'is_overdue' => $this->isOverdue(),
            'duration_days' => $this->getDurationDays(),
            'completion_percentage' => $this->formatDecimal($this->getCompletionPercentage()),
            'hours_variance' => $this->formatDecimal($this->getHoursVariance()),
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
     * Check if task is overdue
     */
    private function isOverdue(): bool
    {
        return $this->end_date && $this->end_date->isPast() && $this->status !== 'completed';
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
     * Get completion percentage based on progress
     */
    private function getCompletionPercentage(): float
    {
        return (float) $this->progress_percent;
    }
    
    /**
     * Get hours variance (actual - estimated)
     */
    private function getHoursVariance(): float
    {
        return (float) ($this->actual_hours - $this->estimated_hours);
    }
}