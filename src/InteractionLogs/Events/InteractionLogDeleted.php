<?php declare(strict_types=1);

namespace App\InteractionLogs\Events;

use App\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi xóa interaction log
 */
class InteractionLogDeleted extends BaseEvent
{
    public function __construct(
        public array $deletedLogData
    ) {
        parent::__construct(
            entityId: $this->deletedLogData['id'],
            projectId: $this->deletedLogData['project_id'],
            actorId: auth()->id(),
            changedFields: ['deleted'],
            eventName: 'InteractionLog.Deleted'
        );
    }

    /**
     * Lấy payload cho event
     */
    public function getPayload(): array
    {
        return array_merge(parent::getPayload(), [
            'deleted_log_data' => $this->deletedLogData
        ]);
    }
}