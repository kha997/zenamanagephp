<?php declare(strict_types=1);

namespace Src\ChangeRequest\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Change Request Collection Resource
 * Transform collection of ChangeRequest models for API responses
 */
class ChangeRequestCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = ChangeRequestResource::class;
    
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->collection->count(),
                'status_summary' => $this->getStatusSummary(),
                'priority_summary' => $this->getPrioritySummary(),
                'total_impact_cost' => $this->getTotalImpactCost(),
                'total_impact_days' => $this->getTotalImpactDays(),
            ],
        ];
    }
    
    /**
     * Get status summary
     */
    private function getStatusSummary(): array
    {
        $summary = [
            'draft' => 0,
            'awaiting_approval' => 0,
            'approved' => 0,
            'rejected' => 0,
        ];
        
        foreach ($this->collection as $changeRequest) {
            $summary[$changeRequest->status] = ($summary[$changeRequest->status] ?? 0) + 1;
        }
        
        return $summary;
    }
    
    /**
     * Get priority summary
     */
    private function getPrioritySummary(): array
    {
        $summary = [
            'low' => 0,
            'medium' => 0,
            'high' => 0,
            'critical' => 0,
        ];
        
        foreach ($this->collection as $changeRequest) {
            $summary[$changeRequest->priority] = ($summary[$changeRequest->priority] ?? 0) + 1;
        }
        
        return $summary;
    }
    
    /**
     * Get total impact cost
     */
    private function getTotalImpactCost(): float
    {
        return (float) $this->collection->sum('impact_cost');
    }
    
    /**
     * Get total impact days
     */
    private function getTotalImpactDays(): int
    {
        return (int) $this->collection->sum('impact_days');
    }
}