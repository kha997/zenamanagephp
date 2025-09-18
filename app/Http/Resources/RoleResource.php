<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Role API Resource
 * 
 * Transforms Role model for JSON responses according to JSend specification
 * 
 * @property \App\Models\Role $resource
 */
class RoleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scope' => $this->scope,
            'description' => $this->description,
            
            // Computed fields
            'is_system_role' => $this->isSystemRole(),
            'is_project_role' => $this->isProjectRole(),
            'permission_count' => $this->getPermissionCount(),
            
            // Relationships - conditional loading
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')),
            'users' => UserResource::collection($this->whenLoaded('users')),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}