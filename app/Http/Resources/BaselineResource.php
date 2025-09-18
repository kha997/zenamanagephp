<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Baseline API Resource
 * 
 * Transforms Baseline model for JSON responses according to JSend specification
 * 
 * @property \App\Models\Baseline $resource
 */
class BaselineResource extends JsonResource
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
            'project_id' => $this->project_id,
            'type' => $this->type,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'cost' => (float) $this->cost,
            'version' => $this->version,
            'note' => $this->note,
            'created_by' => $this->created_by,
            
            // Computed fields
            'duration_days' => $this->getDurationDays(),
            'is_current_version' => $this->isCurrentVersion(),
            'variance_from_actual' => $this->getVarianceFromActual(),
            
            // Relationships - conditional loading
            'project' => new ProjectResource($this->whenLoaded('project')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}