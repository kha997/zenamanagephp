<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ChangeRequest API Resource
 * 
 * Transforms ChangeRequest model for JSON responses according to JSend specification
 * 
 * @property \App\Models\ChangeRequest $resource
 */
class ChangeRequestResource extends JsonResource
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
            'code' => $this->code,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'impact_days' => $this->impact_days,
            'impact_cost' => (float) $this->impact_cost,
            'impact_kpi' => $this->impact_kpi ?? [],
            'created_by' => $this->created_by,
            'decided_by' => $this->decided_by,
            'decided_at' => $this->decided_at?->toISOString(),
            'decision_note' => $this->decision_note,
            
            // Computed fields
            'is_pending' => $this->isPending(),
            'is_approved' => $this->isApproved(),
            'is_rejected' => $this->isRejected(),
            'days_since_created' => $this->getDaysSinceCreated(),
            'total_impact_summary' => $this->getTotalImpactSummary(),
            
            // Relationships - conditional loading
            'project' => new ProjectResource($this->whenLoaded('project')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'decider' => new UserResource($this->whenLoaded('decider')),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}