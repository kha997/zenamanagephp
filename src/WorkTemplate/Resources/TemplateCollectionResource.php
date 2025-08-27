<?php declare(strict_types=1);

namespace Src\WorkTemplate\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Http\Request;

/**
 * Template Collection Resource
 * 
 * Transform collection của Templates với pagination và filtering info
 * Tối ưu cho danh sách templates với metadata
 */
class TemplateCollectionResource extends ResourceCollection
{
    /**
     * Transform collection thành array
     *
     * @param Request $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'templates' => TemplateResource::collection($this->collection),
            
            // Collection metadata
            'meta' => [
                'total' => $this->collection->count(),
                'categories' => $this->getCategoriesBreakdown(),
                'active_count' => $this->collection->where('is_active', true)->count(),
                'inactive_count' => $this->collection->where('is_active', false)->count()
            ]
        ];
    }
    
    /**
     * Customize response wrapper theo JSend format
     */
    public function with($request): array
    {
        return [
            'status' => 'success',
            'message' => 'Templates retrieved successfully'
        ];
    }
    
    /**
     * Lấy breakdown theo categories
     */
    private function getCategoriesBreakdown(): array
    {
        $breakdown = [];
        foreach (\Src\WorkTemplate\Models\Template::CATEGORIES as $category) {
            $breakdown[$category] = $this->collection->where('category', $category)->count();
        }
        return $breakdown;
    }
}