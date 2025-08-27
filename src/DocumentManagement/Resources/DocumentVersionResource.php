<?php declare(strict_types=1);

namespace Src\DocumentManagement\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Document Version API Resource
 * Transform DocumentVersion model data for API responses
 */
class DocumentVersionResource extends JsonResource
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
            'document_id' => $this->document_id,
            'version_number' => $this->version_number,
            'file_path' => $this->file_path,
            'file_name' => $this->file_name,
            'file_size' => $this->file_size,
            'file_type' => $this->file_type,
            'storage_driver' => $this->storage_driver,
            'comment' => $this->comment,
            'reverted_from_version_number' => $this->reverted_from_version_number,
            'created_at' => $this->created_at->toISOString(),
            
            // Relationships
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email
                ];
            }),
            'document' => $this->whenLoaded('document', function () {
                return [
                    'id' => $this->document->id,
                    'title' => $this->document->title,
                    'ulid' => $this->document->ulid
                ];
            }),
            
            // Computed properties
            'file_url' => $this->getFileUrl(),
            'download_url' => $this->getDownloadUrl(),
            'is_current' => $this->isCurrentVersion(),
            'is_reverted' => $this->isReverted(),
            'file_size_formatted' => $this->getFormattedFileSize()
        ];
    }
}