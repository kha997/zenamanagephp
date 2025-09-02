<?php declare(strict_types=1);

namespace Src\Compensation\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * TaskCompensation Collection Resource
 * Transform collection of TaskCompensation models for API responses
 */
class TaskCompensationCollection extends ResourceCollection
{
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
                'total' => $this->total(),
                'count' => $this->count(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'total_pages' => $this->lastPage(),
                'has_more_pages' => $this->hasMorePages(),
                
                // Aggregated data
                'total_compensation' => $this->collection->sum('final_compensation'),
                'average_efficiency' => $this->collection->avg('efficiency_percent'),
                'locked_count' => $this->collection->where('is_locked', true)->count(),
                'pending_count' => $this->collection->where('is_locked', false)->count(),
            ],
            'links' => [
                'first' => $this->url(1),
                'last' => $this->url($this->lastPage()),
                'prev' => $this->previousPageUrl(),
                'next' => $this->nextPageUrl()
            ]
        ];
    }
}