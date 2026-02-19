<?php declare(strict_types=1);

namespace Src\Notification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Src\Notification\Models\Notification;
use Src\RBAC\Resources\UserResource;

/**
 * Notification API Resource
 * Transform Notification model data for API responses
 * 
 * @package Src\Notification\Resources
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'priority' => $this->priority,
            'priority_label' => $this->getPriorityLabel(),
            'title' => $this->title,
            'body' => $this->body,
            'link_url' => $this->link_url,
            'channel' => $this->channel,
            'channel_label' => $this->getChannelLabel(),
            'is_read' => $this->isRead(),
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships (loaded when available)
            'user' => new UserResource($this->whenLoaded('user')),
            
            // Computed properties
            'time_ago' => $this->getTimeAgo(),
            'is_urgent' => $this->isUrgent(),
            'display_priority' => $this->getDisplayPriority(),
        ];
    }

    /**
     * Get additional data that should be returned with the resource array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function with($request): array
    {
        return [
            'meta' => [
                'version' => '1.0',
                'timestamp' => now()->toISOString(),
            ],
        ];
    }

    private function getPriorityLabel(): string
    {
        return match ($this->priority) {
            Notification::PRIORITY_CRITICAL => 'Critical',
            Notification::PRIORITY_NORMAL => 'Normal',
            Notification::PRIORITY_LOW => 'Low',
            default => ucfirst($this->priority ?? ''),
        };
    }

    private function getChannelLabel(): string
    {
        return match ($this->channel) {
            Notification::CHANNEL_INAPP => 'In-App',
            Notification::CHANNEL_EMAIL => 'Email',
            Notification::CHANNEL_WEBHOOK => 'Webhook',
            default => ucfirst($this->channel ?? ''),
        };
    }

    private function getTimeAgo(): ?string
    {
        if (!$this->created_at) {
            return null;
        }

        return $this->created_at->diffForHumans();
    }

    private function isUrgent(): bool
    {
        return $this->priority === Notification::PRIORITY_CRITICAL;
    }

    private function getDisplayPriority(): string
    {
        return match ($this->priority) {
            Notification::PRIORITY_CRITICAL => 'high',
            Notification::PRIORITY_NORMAL => 'medium',
            Notification::PRIORITY_LOW => 'low',
            default => $this->priority ?? 'unknown',
        };
    }
}
