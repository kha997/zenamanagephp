<?php declare(strict_types=1);

namespace Src\Notification\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Notification Statistics API Resource
 * Transform notification statistics data for API responses
 * 
 * @package Src\Notification\Resources
 */
class NotificationStatsResource extends JsonResource
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
            'total_notifications' => $this->resource['total_notifications'] ?? 0,
            'unread_notifications' => $this->resource['unread_notifications'] ?? 0,
            'read_notifications' => $this->resource['read_notifications'] ?? 0,
            'unread_percentage' => $this->calculateUnreadPercentage(),
            
            'by_priority' => [
                'critical' => $this->resource['critical_count'] ?? 0,
                'normal' => $this->resource['normal_count'] ?? 0,
                'low' => $this->resource['low_count'] ?? 0,
            ],
            
            'by_channel' => [
                'inapp' => $this->resource['inapp_count'] ?? 0,
                'email' => $this->resource['email_count'] ?? 0,
                'webhook' => $this->resource['webhook_count'] ?? 0,
            ],
            
            'by_read_status' => [
                'read' => $this->resource['read_notifications'] ?? 0,
                'unread' => $this->resource['unread_notifications'] ?? 0,
            ],
            
            'recent_activity' => [
                'today' => $this->resource['today_count'] ?? 0,
                'this_week' => $this->resource['week_count'] ?? 0,
                'this_month' => $this->resource['month_count'] ?? 0,
            ],
            
            'trends' => [
                'daily_average' => $this->resource['daily_average'] ?? 0,
                'weekly_average' => $this->resource['weekly_average'] ?? 0,
                'growth_rate' => $this->resource['growth_rate'] ?? 0,
            ],
            
            'performance' => [
                'read_rate' => $this->calculateReadRate(),
                'response_time' => $this->resource['avg_response_time'] ?? 0,
                'engagement_score' => $this->calculateEngagementScore(),
            ],
        ];
    }

    /**
     * Calculate unread percentage
     *
     * @return float
     */
    private function calculateUnreadPercentage(): float
    {
        $total = $this->resource['total_notifications'] ?? 0;
        $unread = $this->resource['unread_notifications'] ?? 0;
        
        if ($total === 0) {
            return 0.0;
        }
        
        return round(($unread / $total) * 100, 2);
    }

    /**
     * Calculate read rate
     *
     * @return float
     */
    private function calculateReadRate(): float
    {
        $total = $this->resource['total_notifications'] ?? 0;
        $read = $this->resource['read_notifications'] ?? 0;
        
        if ($total === 0) {
            return 0.0;
        }
        
        return round(($read / $total) * 100, 2);
    }

    /**
     * Calculate engagement score
     *
     * @return float
     */
    private function calculateEngagementScore(): float
    {
        $readRate = $this->calculateReadRate();
        $responseTime = $this->resource['avg_response_time'] ?? 0;
        
        // Simple engagement score calculation
        // Higher read rate and lower response time = higher engagement
        $score = $readRate;
        
        if ($responseTime > 0) {
            // Penalize slow response times
            $timePenalty = min($responseTime / 3600, 50); // Max 50% penalty for response time > 1 hour
            $score = max(0, $score - $timePenalty);
        }
        
        return round($score, 2);
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
                'resource_type' => 'notification_statistics',
                'calculation_notes' => [
                    'unread_percentage' => 'Percentage of unread notifications out of total',
                    'read_rate' => 'Percentage of read notifications out of total',
                    'engagement_score' => 'Combined metric based on read rate and response time',
                    'response_time' => 'Average time in seconds from notification creation to first read',
                ],
            ],
        ];
    }
}