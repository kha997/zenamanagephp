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
 * Event để broadcast notification đến một user cụ thể
 * Sử dụng kênh private-user.{user_id}
 */
class NotificationBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * ID của user nhận notification
     */
    public int $userId;

    /**
     * Dữ liệu notification
     */
    public array $notificationData;

    /**
     * Tạo instance mới của event
     *
     * @param int|string $userId ID của user (sẽ được cast thành int)
     * @param array $notificationData Dữ liệu notification
     */
    public function __construct(int|string $userId, array $notificationData)
    {
        // Cast userId thành int để tránh lỗi type
        $this->userId = (int) $userId;
        $this->notificationData = $notificationData;
    }

    /**
     * Định nghĩa kênh broadcast
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user.' . $this->userId),
        ];
    }

    /**
     * Tên event được broadcast
     *
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'user.notification';
    }

    /**
     * Dữ liệu được broadcast
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->userId,
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
        // Chỉ broadcast khi có dữ liệu notification
        return !empty($this->notificationData);
    }
}