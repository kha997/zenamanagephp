<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Notification API Resource
 * 
 * Transforms Notification model for JSON responses according to JSend specification
 * 
 * @property \App\Models\Notification $resource
 */
class NotificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'priority' => $this->priority,
            'title' => $this->title,
            'body' => $this->body,
            'link_url' => $this->link_url,
            'channel' => $this->channel,
            'read_at' => $this->read_at?->toISOString(),
            
            // Computed fields
            'is_read' => $this->isRead(),
            'is_unread' => $this->isUnread(),
            'is_expired' => $this->is_expired,
            'is_critical' => $this->isCritical(),
            'age_in_hours' => $this->getAgeInHours(),
            
            // Relationships - conditional loading
            'user' => new UserResource($this->whenLoaded('user')),
            
            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
