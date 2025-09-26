<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * User API Resource
 * 
 * Transforms User model for JSON responses according to JSend specification
 * 
 * @property \App\Models\User $resource
 */
class UserResource extends JsonResource
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
            'tenant_id' => $this->tenant_id,
            'name' => $this->name,
            'email' => $this->email,
            
            // Security: Never expose password or sensitive data
            
            // Computed fields
            'initials' => $this->getInitials(),
            'full_display_name' => $this->getFullDisplayName(),
            
            // Relationships - conditional loading
            'system_roles' => RoleResource::collection($this->whenLoaded('systemRoles')),
            'project_roles' => $this->when(
                $this->relationLoaded('projectRoles'),
                fn() => $this->projectRoles->map(fn($pivot) => [
                    'project_id' => $pivot->project_id,
                    'role' => new RoleResource($pivot->role),
                ])
            ),
            'task_assignments' => TaskAssignmentResource::collection($this->whenLoaded('taskAssignments')),
            'notifications' => NotificationResource::collection($this->whenLoaded('notifications')),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}