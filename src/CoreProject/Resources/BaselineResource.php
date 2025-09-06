<?php declare(strict_types=1);

namespace Src\CoreProject\Resources;

use App\Http\Resources\BaseApiResource;

/**
 * Baseline API Resource
 * Transform Baseline model data for API responses
 */
class BaselineResource extends BaseApiResource
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
            'type' => $this->type,
            'type_label' => $this->getTypeLabel(),
            'start_date' => $this->formatDate($this->start_date),
            'end_date' => $this->formatDate($this->end_date),
            'cost' => $this->formatDecimal($this->cost),
            'version' => $this->version,
            'note' => $this->note,
            'created_by' => $this->created_by,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
            
            // Relationships
            'project' => $this->includeRelationship('project', ProjectResource::class),
            'creator' => $this->includeRelationship('creator', UserResource::class),
            
            // Computed properties
            'duration_days' => $this->getDurationDays(),
            'is_current' => $this->isCurrent(),
        ];
    }
    
    /**
     * Get type label
     */
    private function getTypeLabel(): string
    {
        return static::TYPES[$this->type] ?? $this->type;
    }
    
    /**
     * Get baseline duration in days
     */
    private function getDurationDays(): ?int
    {
        if (!$this->start_date || !$this->end_date) {
            return null;
        }
        
        return $this->start_date->diffInDays($this->end_date);
    }
    
    /**
     * Check if this is the current baseline
     */
    private function isCurrent(): bool
    {
        return $this->project && $this->project->current_baseline_id === $this->id;
    }
}