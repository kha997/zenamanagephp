<?php declare(strict_types=1);

namespace Src\RBAC\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User API Resource for RBAC context
 * Transform User model data with RBAC information for API responses
 */
class UserResource extends JsonResource
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
            'email' => $this->email,
            'tenant_id' => $this->tenant_id,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // RBAC Relationships (loaded when available)
            'system_roles' => RoleResource::collection($this->whenLoaded('systemRoles')),
            'project_roles' => $this->when(
                $this->relationLoaded('projectRoles'),
                function () {
                    return $this->projectRoles->map(function ($pivot) {
                        return [
                            'project_id' => $pivot->project_id,
                            'role' => new RoleResource($pivot->role),
                            'assigned_at' => $pivot->created_at->toISOString()
                        ];
                    });
                }
            ),
            
            // Counts
            'system_roles_count' => $this->whenCounted('systemRoles'),
            'project_roles_count' => $this->whenCounted('projectRoles'),
            
            // Computed properties
            'has_system_roles' => $this->hasSystemRoles(),
            'has_project_roles' => $this->hasProjectRoles(),
        ];
    }
    
    /**
     * Check if user has system roles
     */
    private function hasSystemRoles(): bool
    {
        return $this->relationLoaded('systemRoles') && $this->systemRoles->isNotEmpty();
    }
    
    /**
     * Check if user has project roles
     */
    private function hasProjectRoles(): bool
    {
        return $this->relationLoaded('projectRoles') && $this->projectRoles->isNotEmpty();
    }
}