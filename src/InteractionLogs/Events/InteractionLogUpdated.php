<?php declare(strict_types=1);

namespace App\InteractionLogs\Events;

use App\InteractionLogs\Models\InteractionLog;
use App\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi cập nhật interaction log
 */
class InteractionLogUpdated extends BaseEvent
{
    public function __construct(
        public InteractionLog $interactionLog,
        public array $originalData
    ) {
        parent::__construct(
            entityId: $this->interactionLog->id,
            projectId: $this->interactionLog->project_id,
            actorId: $this->resolveActorId(),
            changedFields: $this->getChangedFields(),
            eventName: 'InteractionLog.Updated'
        );
    }

    /**
     * Lấy các field đã thay đổi
     */
    private function getChangedFields(): array
    {
        $changed = [];
        $current = $this->interactionLog->toArray();
        
        foreach ($this->originalData as $key => $value) {
            if (isset($current[$key]) && $current[$key] !== $value) {
                $changed[] = $key;
            }
        }
        
        return $changed;
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
                'changes' => $this->getChangedFields()
            ],
            'original_data' => $this->originalData
        ]);
    }
}