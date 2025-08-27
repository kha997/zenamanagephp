<?php declare(strict_types=1);

namespace App\InteractionLogs\Events;

use App\InteractionLogs\Models\InteractionLog;
use App\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi phê duyệt interaction log cho client
 */
class InteractionLogApprovedForClient extends BaseEvent
{
    public function __construct(
        public InteractionLog $interactionLog
    ) {
        parent::__construct(
            entityId: $this->interactionLog->id,
            projectId: $this->interactionLog->project_id,
            actorId: auth()->id(),
            changedFields: ['client_approved', 'visibility'],
            eventName: 'InteractionLog.ApprovedForClient'
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
                'visibility' => $this->interactionLog->visibility,
                'client_approved' => $this->interactionLog->client_approved
            ]
        ]);
    }
}