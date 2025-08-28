<?php declare(strict_types=1);

namespace Src\Notification\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event để broadcast notification liên quan đến project
 * Sử dụng kênh private-project.{project_id}
 */
class ProjectNotificationBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * ID của project
     */
    public int $projectId;

    /**
     * Dữ liệu notification
     */
    public array $notificationData;

    /**
     * Danh sách user IDs nhận notification
     */
    public array $userIds;

    /**
     * Tạo instance mới của event
     *
     * @param int $projectId ID của project
     * @param array $userIds Danh sách user IDs
     * @param array $notificationData Dữ liệu notification
     */
    public function __construct(int $projectId, array $userIds, array $notificationData)
    {
        $this->projectId = $projectId;
        $this->userIds = $userIds;
        $this->notificationData = $notificationData;
    }

    /**
     * Định nghĩa kênh broadcast
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // Broadcast đến project channel
        $channels[] = new PrivateChannel('project.' . $this->projectId);
        
        // Broadcast đến từng user channel
        foreach ($this->userIds as $userId) {
            $channels[] = new PrivateChannel('user.' . $userId);
        }
        
        return $channels;
    }

    /**
     * Tên event được broadcast
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'project.notification';
    }

    /**
     * Dữ liệu được broadcast
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'project_id' => $this->projectId,
            'user_ids' => $this->userIds,
            'notification' => $this->notificationData,
            'timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Điều kiện để broadcast event
     *
     * @return bool
     */
    public function broadcastWhen(): bool
    {
        // Chỉ broadcast khi có dữ liệu notification và user IDs
        return !empty($this->notificationData) && !empty($this->userIds);
    }
}