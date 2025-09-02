<?php declare(strict_types=1);

namespace Src\Notification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
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
}