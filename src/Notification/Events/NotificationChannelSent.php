<?php declare(strict_types=1);

namespace Src\Notification\Events;

use Src\Foundation\Events\BaseEvent;

/**
 * Event được dispatch khi một Notification được gửi qua kênh cụ thể
 */
class NotificationChannelSent extends BaseEvent
{
    /**
     * Dữ liệu của Notification được gửi
     * 
     * @var array
     */
    public array $notificationData;
    
    /**
     * Kênh gửi thông báo
     * 
     * @var string
     */
    public string $channel;
    
    /**
     * Trạng thái gửi
     * 
     * @var bool
     */
    public bool $success;
    
    /**
     * Thông tin lỗi nếu có
     * 
     * @var string|null
     */
    public ?string $errorMessage;
    
    /**
     * Constructor
     * 
     * @param string $entityId ID của Notification
     * @param string $projectId ID của Project
     * @param string $actorId ID của người gửi (system)
     * @param array $notificationData Dữ liệu của Notification
     * @param string $channel Kênh gửi
     * @param bool $success Trạng thái gửi
     * @param string|null $errorMessage Thông tin lỗi
     * @param array $changedFields Các trường đã thay đổi
     */
    public function __construct(
        string $entityId,
        string $projectId,
        string $actorId,
        array $notificationData,
        string $channel,
        bool $success,
        ?string $errorMessage = null,
        array $changedFields = []
    ) {
        parent::__construct($entityId, $projectId, $actorId, $changedFields);
        $this->notificationData = $notificationData;
        $this->channel = $channel;
        $this->success = $success;
        $this->errorMessage = $errorMessage;
    }
    
    /**
     * Lấy tên sự kiện theo format Domain.Entity.Action
     * 
     * @return string
     */
    public function getEventName(): string
    {
        return 'Notification.Notification.ChannelSent';
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
            'channel' => $this->channel,
            'success' => $this->success,
            'errorMessage' => $this->errorMessage,
        ]);
    }
}