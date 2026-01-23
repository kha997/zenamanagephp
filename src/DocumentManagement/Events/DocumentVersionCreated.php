<?php declare(strict_types=1);

namespace Src\DocumentManagement\Events;

use Src\DocumentManagement\Models\DocumentVersion;
use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi tạo version mới cho document
 */
class DocumentVersionCreated extends BaseEvent
{
    public function __construct(
        public DocumentVersion $version,
        public string $userId
    ) {
        parent::__construct(
            entityId: $this->version->document_id,
            projectId: $this->version->document->project_id,
            actorId: $this->userId,
            changedFields: ['version_created']
        );
    }

    /**
     * Lấy payload cho event
     */
    public function getPayload(): array
    {
        return array_merge(parent::getPayload(), [
            'version' => [
                'id' => $this->version->id,
                'document_id' => $this->version->document_id,
                'version_number' => $this->version->version_number,
                'file_name' => $this->version->file_name,
                'file_size' => $this->version->file_size,
                'comment' => $this->version->comment
            ]
        ]);
    }

    public function getEventName(): string
    {
        return 'Document.Version.Created';
    }
}
