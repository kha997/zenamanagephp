<?php declare(strict_types=1);

namespace Src\Compensation\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Src\CoreProject\Resources\ProjectResource;
use Src\CoreProject\Resources\UserResource;

/**
 * Contract API Resource
 * Transform Contract model data for API responses
 */
class ContractResource extends JsonResource
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
            'contract_number' => $this->contract_number,
            'title' => $this->title,
            'description' => $this->description,
            'total_value' => (float) $this->total_value,
            'currency' => $this->currency,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'status' => $this->status,
            'status_label' => $this->getStatusLabel(),
            'terms' => $this->terms,
            'is_active' => $this->is_active,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships (loaded when available)
            'project' => new ProjectResource($this->whenLoaded('project')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            'compensations_count' => $this->whenCounted('compensations'),
            'compensations' => TaskCompensationResource::collection($this->whenLoaded('compensations')),
            
            // Computed properties
            'is_expired' => $this->isExpired(),
            'days_remaining' => $this->getDaysRemaining(),
            'total_compensations' => $this->getTotalCompensations(),
            'remaining_value' => $this->getRemainingValue(),
        ];
    }
    
    /**
     * Get status label
     * 
     * @return string
     */
    private function getStatusLabel(): string
    {
        return match($this->status) {
            'draft' => 'Draft',
            'active' => 'Active',
            'completed' => 'Completed',
            'cancelled' => 'Cancelled',
            default => 'Unknown'
        };
    }
    
    /**
     * Check if contract is expired
     * 
     * @return bool
     */
    private function isExpired(): bool
    {
        return $this->end_date && $this->end_date->isPast();
    }
    
    /**
     * Get days remaining until contract expires
     * 
     * @return int|null
     */
    private function getDaysRemaining(): ?int
    {
        if (!$this->end_date || $this->end_date->isPast()) {
            return null;
        }
        
        return now()->diffInDays($this->end_date);
    }
    
    /**
     * Get total compensations amount
     * 
     * @return float
     */
    private function getTotalCompensations(): float
    {
        return $this->compensations?->sum('final_compensation') ?? 0.0;
    }
    
    /**
     * Get remaining contract value
     * 
     * @return float
     */
    private function getRemainingValue(): float
    {
        return $this->total_value - $this->getTotalCompensations();
    }
}