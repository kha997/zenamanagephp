<?php declare(strict_types=1);

namespace Src\Notification\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi một Notification được đánh dấu là đã đọc
 */
class NotificationRead extends BaseEvent
{
    /**
     * Dữ liệu của Notification được đọc
     * 
     * @var array
     */
    public array $notificationData;
    
    /**
     * Thời gian đọc
     * 
     * @var string
     */
    public string $readAt;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của Notification
     * @param string $projectId ID của Project
     * @param string $actorId ID của người đọc
     * @param array $notificationData Dữ liệu của Notification
     * @param string $readAt Thời gian đọc
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        array $notificationData,
        string $readAt,
        array $changedFields = ['read_at']
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->notificationData = $notificationData;
        $this->readAt = $readAt;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'Notification.Notification.Read';
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
            'readAt' => $this->readAt,
        ]);
    }
}