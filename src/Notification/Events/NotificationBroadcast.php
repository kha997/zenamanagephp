<?php declare(strict_types=1);

namespace Src\Notification\Events;

use App\Services\WebSocketClient;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NotificationBroadcast implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int|string $userId;
    public ?int $projectId;
    public array $notification;

    /**
     * Create a new event instance.
     */
    public function __construct(int|string $userId, array $notification, ?int $projectId = null)
    {
        $this->userId = is_string($userId) ? (int) $userId : $userId;
        $this->projectId = $projectId;
        $this->notification = $notification;
        
        // Gửi đến WebSocket server
        $this->sendToWebSocket();
    }

    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        $channels = [];
        
        // User-specific channel
        $channels[] = new PrivateChannel('user.' . $this->userId);
        
        // Project-specific channel if projectId is provided
        if ($this->projectId) {
            $channels[] = new PrivateChannel('project.' . $this->projectId);
        }
        
        return $channels;
    }
    
    /**
     * Get the data to broadcast.
     */
    public function broadcastWith(): array
    {
        return [
            'notification' => $this->notification,
            'timestamp' => now()->toISOString()
        ];
    }
    
    /**
     * Gửi notification đến WebSocket server
     */
    private function sendToWebSocket(): void
    {
        try {
            $wsClient = new WebSocketClient();
            
            // Gửi đến user cụ thể
            $wsClient->sendToUser($this->userId, $this->notification);
            
            // Gửi đến project nếu có
            if ($this->projectId) {
                $wsClient->sendToProject($this->projectId, $this->notification);
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send notification to WebSocket server', [
                'error' => $e->getMessage(),
                'userId' => $this->userId,
                'projectId' => $this->projectId,
                'notification' => $this->notification
            ]);
        }
    }
}