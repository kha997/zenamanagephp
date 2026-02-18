<?php declare(strict_types=1);

namespace Src\Notification\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event được dispatch khi notification được trigger
 * Xử lý gửi notifications qua các channels khác nhau
 */
class NotificationTriggered
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly string $userId,
        public readonly string $tenantId,
        public readonly string $priority,
        public readonly string $title,
        public readonly string $body,
        public readonly ?string $linkUrl,
        public readonly array $channels,
        public readonly string $sourceEvent,
        public readonly array $sourcePayload,
        ?\DateTime $timestamp = null
    ) {
        $this->timestamp = $timestamp ?? new \DateTime();
    }

    public function getEventName(): string
    {
        return 'Notification.Notification.Triggered';
    }

    public function getPayload(): array
    {
        return [
            'user_id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'priority' => $this->priority,
            'title' => $this->title,
            'channels' => $this->channels,
            'source_event' => $this->sourceEvent,
            'timestamp' => $this->timestamp->format('Y-m-d H:i:s')
        ];
    }
}
