<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProjectCostHealthResource
 * 
 * Round 226: Project Cost Health Status + Alert Indicators
 * 
 * Formats cost health response for API
 */
class ProjectCostHealthResource extends JsonResource
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
            'cost_health_status' => $this->resource['cost_health_status'],
            'stats' => [
                'budget_total' => (float) ($this->resource['stats']['budget_total'] ?? 0.0),
                'forecast_final_cost' => (float) ($this->resource['stats']['forecast_final_cost'] ?? 0.0),
                'variance_vs_budget' => (float) ($this->resource['stats']['variance_vs_budget'] ?? 0.0),
                'pending_change_orders_total' => (float) ($this->resource['stats']['pending_change_orders_total'] ?? 0.0),
            ],
        ];
    }
}
