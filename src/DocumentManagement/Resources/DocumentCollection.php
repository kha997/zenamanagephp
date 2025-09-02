<?php declare(strict_types=1);

namespace Src\DocumentManagement\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Document Collection Resource
 * Transform collection of Document models for API responses
 */
class DocumentCollection extends ResourceCollection
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
                'has_more_pages' => $this->hasMorePages()
            ]
        ];
    }
}