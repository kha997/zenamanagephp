<?php declare(strict_types=1);

namespace Src\DocumentManagement\Events;

use Src\DocumentManagement\Models\Document;
use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi cập nhật document
 */
class DocumentUpdated extends BaseEvent
{
    public function __construct(
        public Document $document,
        public array $changedFields,
        public int $userId
    ) {
        parent::__construct(
            entityId: $this->document->id,
            projectId: $this->document->project_id,
            actorId: $this->userId,
            changedFields: $this->changedFields,
            eventName: 'Document.Updated'
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
                'client_approved' => $this->document->client_approved
            ],
            'changes' => $this->changedFields
        ]);
    }
}