<?php declare(strict_types=1);

namespace Src\WorkTemplate\Resources;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Request;

/**
 * Project Task Collection Resource
 *
 * Wraps project tasks with pagination metadata for API responses.
 */
class ProjectTaskCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray($request): array
    {
        return [
            'tasks' => ProjectTaskResource::collection($this->collection),
            'meta' => $this->getPaginationMeta(),
        ];
    }

    /**
     * Customize response wrapper to match JSend success envelope.
     */
    public function with($request): array
    {
        return [
            'status' => 'success',
            'message' => 'Project tasks retrieved successfully'
        ];
    }

    private function getPaginationMeta(): array
    {
        if ($this->collection instanceof LengthAwarePaginator) {
            return [
                'total' => $this->collection->total(),
                'per_page' => $this->collection->perPage(),
                'current_page' => $this->collection->currentPage(),
                'from' => $this->collection->firstItem(),
                'to' => $this->collection->lastItem(),
                'has_more' => $this->collection->hasMorePages(),
            ];
        }

        return [
            'total' => $this->collection->count(),
            'per_page' => null,
            'current_page' => null,
            'from' => null,
            'to' => null,
            'has_more' => false,
        ];
    }
}
