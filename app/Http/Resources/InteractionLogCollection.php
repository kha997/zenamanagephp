<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Request;

/**
 * Collection resource for InteractionLog with metadata
 */
class InteractionLogCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'meta' => [
                'total' => $this->resource->total(),
                'per_page' => $this->resource->perPage(),
                'current_page' => $this->resource->currentPage(),
                'last_page' => $this->resource->lastPage(),
                'from' => $this->resource->firstItem(),
                'to' => $this->resource->lastItem(),
                
                // Thống kê bổ sung
                'statistics' => [
                    'by_type' => $this->getStatisticsByType(),
                    'by_visibility' => $this->getStatisticsByVisibility(),
                    'pending_approval' => $this->getPendingApprovalCount(),
                ],
            ],
        ];
    }
    
    /**
     * Get statistics by interaction type
     *
     * @return array<string, int>
     */
    private function getStatisticsByType(): array
    {
        return $this->collection->groupBy('type')
            ->map(fn($group) => $group->count())
            ->toArray();
    }
    
    /**
     * Get statistics by visibility
     *
     * @return array<string, int>
     */
    private function getStatisticsByVisibility(): array
    {
        return $this->collection->groupBy('visibility')
            ->map(fn($group) => $group->count())
            ->toArray();
    }
    
    /**
     * Get count of logs pending client approval
     *
     * @return int
     */
    private function getPendingApprovalCount(): int
    {
        return $this->collection->where('visibility', 'client')
            ->where('client_approved', false)
            ->count();
    }
}