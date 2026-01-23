<?php declare(strict_types=1);

namespace Src\DocumentManagement\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Document API Resource
 * Transform Document model data for API responses
 */
class DocumentResource extends JsonResource
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
            'ulid' => $this->ulid,
            'project_id' => $this->project_id,
            'title' => $this->title,
            'document_type' => $this->file_type,
            'description' => $this->description,
            'linked_entity_type' => $this->linked_entity_type,
            'linked_entity_id' => $this->linked_entity_id,
            'tags' => $this->tags,
            'visibility' => $this->visibility,
            'client_approved' => $this->client_approved,
            'current_version_id' => $this->current_version_id,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships (loaded when available)
            'project' => $this->whenLoaded('project', function () {
                return [
                    'id' => $this->project->id,
                    'name' => $this->project->name,
                    'ulid' => $this->project->ulid
                ];
            }),
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email
                ];
            }),
            'current_version' => new DocumentVersionResource($this->whenLoaded('currentVersion')),
            'versions' => DocumentVersionResource::collection($this->whenLoaded('versions')),
            'versions_count' => $this->whenCounted('versions'),
            
            // Computed properties
            'file_url' => $this->currentVersion?->getFileUrl(),
            'file_name' => $this->currentVersion?->getFileName(),
            'file_size' => $this->currentVersion?->file_size,
            'file_type' => $this->currentVersion?->file_type,
            'version' => $this->currentVersion?->version_number,
            'latest_version_number' => $this->currentVersion?->version_number,
            'can_download' => $this->canDownload(),
            'is_client_visible' => $this->isVisibleToClient()
        ];
    }

    private function canDownload(): bool
    {
        return $this->currentVersion?->fileExists() ?? false;
    }
}
