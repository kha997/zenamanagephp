<?php declare(strict_types=1);

namespace Src\DocumentManagement\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi xóa document
 */
class DocumentDeleted extends BaseEvent
{
    public function __construct(
        public string $documentId,
        public string $documentUlid,
        public string $documentTitle,
        public string $projectId,
        public string $userId
    ) {
        parent::__construct(
            entityId: $this->documentId,
            projectId: $this->projectId,
            actorId: $this->userId,
            changedFields: ['deleted']
        );
    }

    /**
     * Lấy payload cho event
     */
    public function getPayload(): array
    {
        return array_merge(parent::getPayload(), [
            'document' => [
                'id' => $this->documentId,
                'ulid' => $this->documentUlid,
                'title' => $this->documentTitle
            ]
        ]);
    }

    public function getEventName(): string
    {
        return 'Document.Document.Deleted';
    }
}
