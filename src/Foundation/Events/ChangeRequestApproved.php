<?php declare(strict_types=1);

namespace Src\Foundation\Events;

/**
 * Sự kiện khi change request được phê duyệt
 */
class ChangeRequestApproved extends BaseEvent {
    public array $impactData;
    
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        array $impactData,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->impactData = $impactData;
    }
    
    public function getEventName(): string {
        return 'ChangeRequest.ChangeRequest.Approved';
    }
    
    public function toArray(): array {
        return array_merge(parent::toArray(), [
            'impactData' => $this->impactData
        ]);
    }
}