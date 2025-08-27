<?php declare(strict_types=1);

namespace Src\Notification\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi một Notification được tạo mới
 */
class NotificationCreated extends BaseEvent
{
    /**
     * Dữ liệu của Notification được tạo
     * 
     * @var array
     */
    public array $notificationData;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của Notification
     * @param string $projectId ID của Project (có thể null cho system notifications)
     * @param string $actorId ID của người tạo
     * @param array $notificationData Dữ liệu của Notification
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        array $notificationData,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->notificationData = $notificationData;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'Notification.Notification.Created';
    }
    
    /**
     * Chuyển đổi event thành array payload
     * 
     * @return array
     */
    public function toArray(): array
    {
        return array_merge(parent::toArray(), [
            'notificationData' => $this->notificationData,
        ]);
    }
}