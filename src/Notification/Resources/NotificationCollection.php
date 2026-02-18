<?php declare(strict_types=1);

namespace Src\Notification\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Notification Collection Resource
 * Transform collection of Notification models for API responses
 * 
 * @package Src\Notification\Resources
 */
class NotificationCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = NotificationResource::class;

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
            'summary' => [
                'total' => $this->collection->count(),
                'unread_count' => $this->collection->where('read_at', null)->count(),
                'critical_count' => $this->collection->where('priority', 'critical')->count(),
                'normal_count' => $this->collection->where('priority', 'normal')->count(),
                'low_count' => $this->collection->where('priority', 'low')->count(),
                'by_channel' => [
                    'inapp' => $this->collection->where('channel', 'inapp')->count(),
                    'email' => $this->collection->where('channel', 'email')->count(),
                    'webhook' => $this->collection->where('channel', 'webhook')->count(),
                ],
            ],
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request): array
    {
        $meta = [
            'version' => '1.0',
            'timestamp' => now()->toISOString(),
            'collection_type' => 'notifications',
        ];

        if ($this->resource instanceof LengthAwarePaginator) {
            $meta['pagination'] = [
                'page' => $this->resource->currentPage(),
                'per_page' => $this->resource->perPage(),
                'total' => $this->resource->total(),
                'last_page' => $this->resource->lastPage(),
            ];
        }

        return ['meta' => $meta];
    }
}
