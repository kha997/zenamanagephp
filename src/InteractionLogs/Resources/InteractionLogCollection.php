<?php declare(strict_types=1);

namespace App\InteractionLogs\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * API Resource Collection cho InteractionLog
 */
class InteractionLogCollection extends ResourceCollection
{
    /**
     * Transform collection thành array
     * 
     * @param \Illuminate\Http\Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'data' => InteractionLogResource::collection($this->collection),
            'meta' => [
                'total' => $this->total(),
                'per_page' => $this->perPage(),
                'current_page' => $this->currentPage(),
                'last_page' => $this->lastPage(),
                'from' => $this->firstItem(),
                'to' => $this->lastItem()
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