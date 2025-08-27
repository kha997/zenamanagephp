<?php declare(strict_types=1);

namespace Src\ChangeRequest\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi một Change Request bị xóa
 */
class ChangeRequestDeleted extends BaseEvent
{
    /**
     * Dữ liệu của Change Request bị xóa
     * 
     * @var array
     */
    public array $deletedChangeRequestData;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của Change Request
     * @param string $projectId ID của Project
     * @param string $actorId ID của người xóa
     * @param array $deletedChangeRequestData Dữ liệu của Change Request bị xóa
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        array $deletedChangeRequestData,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->deletedChangeRequestData = $deletedChangeRequestData;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'ChangeRequest.ChangeRequest.Deleted';
    }
    
    /**
     * Chuyển đổi event thành array payload
     * 
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'deletedChangeRequestData' => $this->deletedChangeRequestData,
        ]);
    }
}