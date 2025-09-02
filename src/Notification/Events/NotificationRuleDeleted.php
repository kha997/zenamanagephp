<?php declare(strict_types=1);

namespace Src\Notification\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi một Notification Rule bị xóa
 */
class NotificationRuleDeleted extends BaseEvent
{
    /**
     * Dữ liệu của Notification Rule bị xóa
     * 
     * @var array
     */
    public array $deletedRuleData;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của Notification Rule
     * @param string $projectId ID của Project
     * @param string $actorId ID của người xóa
     * @param array $deletedRuleData Dữ liệu của Notification Rule bị xóa
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        array $deletedRuleData,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->deletedRuleData = $deletedRuleData;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'Notification.NotificationRule.Deleted';
    }
    
    /**
     * Chuyển đổi event thành array payload
     * 
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'deletedRuleData' => $this->deletedRuleData,
        ]);
    }
}