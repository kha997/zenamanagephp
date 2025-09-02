<?php declare(strict_types=1);

namespace Src\Notification\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * Notification Rule Collection Resource
 * Transform collection of NotificationRule models for API responses
 * 
 * @package Src\Notification\Resources
 */
class NotificationRuleCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = NotificationRuleResource::class;

    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'data' => $this->collection,
            'summary' => [
                'total' => $this->collection->count(),
                'enabled_count' => $this->collection->where('is_enabled', true)->count(),
                'disabled_count' => $this->collection->where('is_enabled', false)->count(),
                'global_rules' => $this->collection->whereNull('project_id')->count(),
                'project_specific_rules' => $this->collection->whereNotNull('project_id')->count(),
                'by_priority' => [
                    'critical' => $this->collection->where('min_priority', 'critical')->count(),
                    'normal' => $this->collection->where('min_priority', 'normal')->count(),
                    'low' => $this->collection->where('min_priority', 'low')->count(),
                ],
                'by_event_domain' => $this->getEventDomainStats(),
            ],
        ];
    }

    /**
     * Get statistics by event domain
     *
     * @return array
     */
    private function getEventDomainStats(): array
    {
        $stats = [];
        
        foreach ($this->collection as $rule) {
            $parts = explode('.', $rule->event_key);
            $domain = $parts[0] ?? 'unknown';
            
            if (!isset($stats[$domain])) {
                $stats[$domain] = 0;
            }
            $stats[$domain]++;
        }
        
        return $stats;
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
                'collection_type' => 'notification_rules',
            ],
        ];
    }
}