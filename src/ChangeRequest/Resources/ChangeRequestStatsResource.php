<?php declare(strict_types=1);

namespace Src\ChangeRequest\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Change Request Stats Resource
 * Transform Change Request statistics for API responses
 */
class ChangeRequestStatsResource extends JsonResource
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
            'total_count' => $this->resource['total_count'] ?? 0,
            'status_breakdown' => [
                'draft' => $this->resource['draft_count'] ?? 0,
                'awaiting_approval' => $this->resource['awaiting_approval_count'] ?? 0,
                'approved' => $this->resource['approved_count'] ?? 0,
                'rejected' => $this->resource['rejected_count'] ?? 0,
            ],
            'priority_breakdown' => [
                'low' => $this->resource['low_priority_count'] ?? 0,
                'medium' => $this->resource['medium_priority_count'] ?? 0,
                'high' => $this->resource['high_priority_count'] ?? 0,
                'critical' => $this->resource['critical_priority_count'] ?? 0,
            ],
            'impact_summary' => [
                'total_cost_impact' => (float) ($this->resource['total_cost_impact'] ?? 0),
                'total_days_impact' => (int) ($this->resource['total_days_impact'] ?? 0),
                'average_cost_impact' => (float) ($this->resource['average_cost_impact'] ?? 0),
                'average_days_impact' => (float) ($this->resource['average_days_impact'] ?? 0),
            ],
            'approval_metrics' => [
                'approval_rate' => (float) ($this->resource['approval_rate'] ?? 0),
                'rejection_rate' => (float) ($this->resource['rejection_rate'] ?? 0),
                'pending_rate' => (float) ($this->resource['pending_rate'] ?? 0),
                'average_decision_time_days' => (float) ($this->resource['average_decision_time_days'] ?? 0),
            ],
            'recent_activity' => [
                'created_this_month' => $this->resource['created_this_month'] ?? 0,
                'decided_this_month' => $this->resource['decided_this_month'] ?? 0,
                'approved_this_month' => $this->resource['approved_this_month'] ?? 0,
                'rejected_this_month' => $this->resource['rejected_this_month'] ?? 0,
            ],
        ];
    }
}