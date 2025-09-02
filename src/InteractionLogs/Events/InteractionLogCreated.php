<?php declare(strict_types=1);

namespace Src\InteractionLogs\Events;

use Src\InteractionLogs\Models\InteractionLog;
use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi tạo interaction log mới
 */
class InteractionLogCreated extends BaseEvent
{
    public function __construct(
        public InteractionLog $interactionLog
    ) {
        parent::__construct(
            entityId: $this->interactionLog->id,
            projectId: $this->interactionLog->project_id,
            actorId: $this->interactionLog->created_by,
            changedFields: ['created'],
            eventName: 'InteractionLog.Created'
        );
    }

    /**
     * Lấy payload cho event
     */
    public function getPayload(): array
    {
        return array_merge(parent::getPayload(), [
            'interaction_log' => [
                'id' => $this->interactionLog->id,
                'ulid' => $this->interactionLog->ulid,
                'type' => $this->interactionLog->type,
                'description' => $this->interactionLog->description,
                'visibility' => $this->interactionLog->visibility,
                'client_approved' => $this->interactionLog->client_approved,
                'tag_path' => $this->interactionLog->tag_path,
                'linked_task_id' => $this->interactionLog->linked_task_id
            ]
        ]);
    }
}