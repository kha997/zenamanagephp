<?php declare(strict_types=1);

namespace Src\RBAC\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Role API Resource
 * Transform Role model data for API responses
 */
class RoleResource extends JsonResource
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
            'name' => $this->name,
            'scope' => $this->scope,
            'scope_label' => $this->getScopeLabel(),
            'allow_override' => $this->allow_override,
            'description' => $this->description,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships (loaded when available)
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'permissions_count' => $this->whenCounted('permissions'),
            
            // Computed properties
            'is_system_role' => $this->isSystemRole(),
            'is_project_role' => $this->isProjectRole(),
            'is_custom_role' => $this->isCustomRole(),
        ];
    }
    
    /**
     * Get scope label in Vietnamese
     */
    private function getScopeLabel(): string
    {
        $labels = [
            'system' => 'Hệ thống',
            'custom' => 'Tùy chỉnh', 
            'project' => 'Dự án'
        ];
        
        return $labels[$this->scope] ?? $this->scope;
    }
    
    /**
     * Check if this is a system role
     */
    private function isSystemRole(): bool
    {
        return $this->scope === 'system';
    }
    
    /**
     * Check if this is a project role
     */
    private function isProjectRole(): bool
    {
        return $this->scope === 'project';
    }
    
    /**
     * Check if this is a custom role
     */
    private function isCustomRole(): bool
    {
        return $this->scope === 'custom';
    }
}