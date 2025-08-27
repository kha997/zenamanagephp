<?php declare(strict_types=1);

namespace Src\ChangeRequest\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi một Change Request được tạo mới
 */
class ChangeRequestCreated extends BaseEvent
{
    /**
     * Dữ liệu của Change Request được tạo
     * 
     * @var array
     */
    public array $changeRequestData;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của Change Request
     * @param string $projectId ID của Project
     * @param string $actorId ID của người tạo
     * @param array $changeRequestData Dữ liệu của Change Request
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        array $changeRequestData,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->changeRequestData = $changeRequestData;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'ChangeRequest.ChangeRequest.Created';
    }
    
    /**
     * Chuyển đổi event thành array payload
     * 
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'changeRequestData' => $this->changeRequestData,
        ]);
    }
}