<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * InteractionLogResource
 * 
 * Transform InteractionLog model cho JSON API responses
 */
class InteractionLogResource extends JsonResource
{
    /**
     * Transform the resource into an array
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'linked_task_id' => $this->linked_task_id,
            'type' => $this->type,
            'description' => $this->description,
            'tag_path' => $this->tag_path,
            'visibility' => $this->visibility,
            'client_approved' => (bool) $this->client_approved,
            'created_by' => $this->created_by,
            
            // Computed fields
            'is_client_visible' => $this->visibility === 'client' && $this->client_approved,
            'tag_hierarchy' => $this->tag_path ? explode('/', $this->tag_path) : [],
            
            // Relationships (conditional loading)
            'project' => new ProjectResource($this->whenLoaded('project')),
            'linked_task' => new TaskResource($this->whenLoaded('linkedTask')),
            'creator' => new UserResource($this->whenLoaded('creator')),
            
            // Metadata
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}