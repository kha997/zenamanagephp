<?php declare(strict_types=1);

namespace Src\RBAC\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Role Collection Resource
 * Custom collection for roles with grouping by scope
 */
class RoleCollection extends ResourceCollection
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
                'total' => $this->collection->count(),
                'grouped_by_scope' => $this->getGroupedByScope(),
                'scope_counts' => $this->getScopeCounts()
            ]
        ];
    }
    
    /**
     * Group roles by scope
     */
    private function getGroupedByScope(): array
    {
        return $this->collection->groupBy('scope')->map(function ($roles, $scope) {
            return [
                'scope' => $scope,
                'scope_label' => $this->getScopeLabel($scope),
                'count' => $roles->count(),
                'roles' => RoleResource::collection($roles)
            ];
        })->values()->toArray();
    }
    
    /**
     * Get count by scope
     */
    private function getScopeCounts(): array
    {
        $counts = $this->collection->groupBy('scope')->map->count();
        
        return [
            'system' => $counts->get('system', 0),
            'custom' => $counts->get('custom', 0),
            'project' => $counts->get('project', 0)
        ];
    }
    
    /**
     * Get scope label in Vietnamese
     */
    private function getScopeLabel(string $scope): string
    {
        $labels = [
            'system' => 'Hệ thống',
            'custom' => 'Tùy chỉnh',
            'project' => 'Dự án'
        ];
        
        return $labels[$scope] ?? $scope;
    }
}