<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProjectCostAlertsResource
 * 
 * Round 227: Cost Alerts System (Nagging & Attention Flags)
 * 
 * Formats cost alerts response for API
 */
class ProjectCostAlertsResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        return [
            'project_id' => $this->resource['project_id'],
            'alerts' => $this->resource['alerts'],
            'details' => [
                'pending_co_count' => (int) ($this->resource['details']['pending_co_count'] ?? 0),
                'overdue_co_count' => (int) ($this->resource['details']['overdue_co_count'] ?? 0),
                'unpaid_certificates_count' => (int) ($this->resource['details']['unpaid_certificates_count'] ?? 0),
                'cost_health_status' => $this->resource['details']['cost_health_status'] ?? 'ON_BUDGET',
                'pending_change_orders_total' => number_format((float) ($this->resource['details']['pending_change_orders_total'] ?? 0.0), 2, '.', ''),
                'budget_total' => number_format((float) ($this->resource['details']['budget_total'] ?? 0.0), 2, '.', ''),
                'threshold_days' => (int) ($this->resource['details']['threshold_days'] ?? 14),
            ],
        ];
    }
}
