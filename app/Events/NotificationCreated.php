<?php declare(strict_types=1);

namespace App\Events;

use App\Models\Notification;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * NotificationCreated Event - Round 256: Realtime Notifications
 * 
 * Broadcast event fired when a notification is successfully created via NotificationService.
 * 
 * Channel name in backend: tenant.{tenantId}.user.{userId}.notifications
 * Frontend subscribes with: Echo.private('tenant.{tenantId}.user.{userId}.notifications')
 * Event name: notification.created
 */
class NotificationCreated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The notification instance
     */
    public Notification $notification;

    /**
     * Create a new event instance
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Get the channels the event should broadcast on
     * 
     * Channel name in backend: tenant.{tenantId}.user.{userId}.notifications
     * Frontend subscribes with: Echo.private('tenant.{tenantId}.user.{userId}.notifications')
     * 
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("tenant.{$this->notification->tenant_id}.user.{$this->notification->user_id}.notifications"),
        ];
    }

    /**
     * The event's broadcast name
     * 
     * @return string
     */
    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    /**
     * Get the data to broadcast
     * 
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'id' => (string) $this->notification->id,
            'tenant_id' => (string) $this->notification->tenant_id,
            'user_id' => (string) $this->notification->user_id,
            'module' => $this->notification->module,
            'type' => $this->notification->type,
            'title' => $this->notification->title,
            'message' => $this->notification->message,
            'entity_type' => $this->notification->entity_type,
            'entity_id' => $this->notification->entity_id,
            'metadata' => $this->notification->metadata ?? [],
            'is_read' => (bool) $this->notification->is_read,
            'created_at' => $this->notification->created_at?->toIso8601String(),
        ];
    }
}
