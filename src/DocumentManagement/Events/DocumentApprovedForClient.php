<?php declare(strict_types=1);

namespace Src\DocumentManagement\Events;

use Src\DocumentManagement\Models\Document;
use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi phê duyệt document cho client
 */
class DocumentApprovedForClient extends BaseEvent
{
    public function __construct(
        public Document $document,
        public string $userId
    ) {
        parent::__construct(
            entityId: $this->document->id,
            projectId: $this->document->project_id,
            actorId: $this->userId,
            changedFields: ['client_approved']
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
            ]
        ]);
    }

    public function getEventName(): string
    {
        return 'Document.Document.ApprovedForClient';
    }
}
