<?php declare(strict_types=1);

namespace Src\Notification\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi một Notification Rule được cập nhật
 */
class NotificationRuleUpdated extends BaseEvent
{
    /**
     * Dữ liệu cũ của Notification Rule
     * 
     * @var array
     */
    public array $oldData;
    
    /**
     * Dữ liệu mới của Notification Rule
     * 
     * @var array
     */
    public array $newData;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của Notification Rule
     * @param string $projectId ID của Project
     * @param string $actorId ID của người cập nhật
     * @param array $oldData Dữ liệu cũ
     * @param array $newData Dữ liệu mới
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        array $oldData,
        array $newData,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->oldData = $oldData;
        $this->newData = $newData;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'Notification.NotificationRule.Updated';
    }
    
    /**
     * Chuyển đổi event thành array payload
     * 
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'oldData' => $this->oldData,
            'newData' => $this->newData,
        ]);
    }
}