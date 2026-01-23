<?php declare(strict_types=1);

namespace Src\DocumentManagement\Events;

use Src\DocumentManagement\Models\Document;
use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi tạo document mới
 */
class DocumentCreated extends BaseEvent
{
    public function __construct(
        public Document $document,
        public string $userId
    ) {
        parent::__construct(
            entityId: $this->document->id,
            projectId: $this->document->project_id,
            actorId: $this->userId,
            changedFields: ['created']
        );
    }

    /**
     * Lấy payload cho event
     */
    public function getPayload(): array
    {
        return array_merge(parent::getPayload(), [
            'document' => [
                'id' => $this->document->id,
                'ulid' => $this->document->ulid,
                'title' => $this->document->title,
                'visibility' => $this->document->visibility,
                'client_approved' => $this->document->client_approved,
                'linked_entity_type' => $this->document->linked_entity_type,
                'linked_entity_id' => $this->document->linked_entity_id,
                'current_version_id' => $this->document->current_version_id
            ]
        ]);
    }

    public function getEventName(): string
    {
        return 'Document.Document.Created';
    }
}
