<?php declare(strict_types=1);

namespace Src\DocumentManagement\Events;

use Src\DocumentManagement\Models\DocumentVersion;
use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi revert document về version cũ
 */
class DocumentVersionReverted extends BaseEvent
{
    public function __construct(
        public DocumentVersion $newVersion,
        public int $revertedFromVersionNumber,
        public string $userId
    ) {
        parent::__construct(
            entityId: $this->newVersion->document_id,
            projectId: $this->newVersion->document->project_id,
            actorId: $this->userId,
            changedFields: ['version_reverted']
        );
    }

    /**
     * Lấy payload cho event
     */
    public function getPayload(): array
    {
        return array_merge(parent::getPayload(), [
            'new_version' => [
                'id' => $this->newVersion->id,
                'version_number' => $this->newVersion->version_number,
                'reverted_from_version_number' => $this->revertedFromVersionNumber
            ]
        ]);
    }

    public function getEventName(): string
    {
        return 'Document.Version.Reverted';
    }
}
