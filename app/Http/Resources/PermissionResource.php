<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Permission API Resource
 * 
 * Transforms Permission model for JSON responses according to JSend specification
 * 
 * @property \App\Models\Permission $resource
 */
class PermissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'module' => $this->module,
            'action' => $this->action,
            'description' => $this->description,
            
            // Computed fields
            'full_permission_name' => $this->getFullPermissionName(),
            'is_critical' => $this->isCritical(),
            
            // Relationships - conditional loading
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}