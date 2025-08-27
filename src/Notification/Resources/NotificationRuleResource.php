<?php declare(strict_types=1);

namespace Src\Notification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Src\RBAC\Resources\UserResource;
use Src\CoreProject\Resources\ProjectResource;

/**
 * Notification Rule API Resource
 * Transform NotificationRule model data for API responses
 * 
 * @package Src\Notification\Resources
 */
class NotificationRuleResource extends JsonResource
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
            'project_id' => $this->project_id,
            'event_key' => $this->event_key,
            'event_key_parts' => $this->getEventKeyParts(),
            'min_priority' => $this->min_priority,
            'min_priority_label' => $this->getMinPriorityLabel(),
            'channels' => $this->channels,
            'channels_labels' => $this->getChannelsLabels(),
            'conditions' => $this->conditions,
            'conditions_summary' => $this->getConditionsSummary(),
            'is_enabled' => $this->is_enabled,
            'status' => $this->is_enabled ? 'active' : 'inactive',
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Relationships (loaded when available)
            'user' => new UserResource($this->whenLoaded('user')),
            'project' => new ProjectResource($this->whenLoaded('project')),
            
            // Computed properties
            'scope' => $this->getScope(),
            'is_global' => $this->isGlobal(),
            'is_project_specific' => $this->isProjectSpecific(),
            'rule_description' => $this->getRuleDescription(),
            'channels_count' => count($this->channels ?? []),
            'conditions_count' => count($this->conditions ?? []),
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
                'available_channels' => ['inapp', 'email', 'webhook'],
                'available_priorities' => ['critical', 'normal', 'low'],
                'available_operators' => ['=', '!=', '>', '<', '>=', '<=', 'contains', 'not_contains', 'in', 'not_in'],
            ],
        ];
    }
}