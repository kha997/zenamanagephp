<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProjectCostDashboardResource
 * 
 * Round 223: Project Cost Dashboard API (Variance + Timeline + Forecast)
 * 
 * Transforms project cost dashboard data for API response
 */
class ProjectCostDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'project_id' => $this->resource['project_id'],
            'currency' => $this->resource['currency'] ?? 'VND',
            'summary' => [
                'budget_total' => (float) ($this->resource['summary']['budget_total'] ?? 0.0),
                'contract_base_total' => (float) ($this->resource['summary']['contract_base_total'] ?? 0.0),
                'contract_current_total' => (float) ($this->resource['summary']['contract_current_total'] ?? 0.0),
                'total_certified_amount' => (float) ($this->resource['summary']['total_certified_amount'] ?? 0.0),
                'total_paid_amount' => (float) ($this->resource['summary']['total_paid_amount'] ?? 0.0),
                'outstanding_amount' => (float) ($this->resource['summary']['outstanding_amount'] ?? 0.0),
            ],
            'variance' => [
                'pending_change_orders_total' => (float) ($this->resource['variance']['pending_change_orders_total'] ?? 0.0),
                'rejected_change_orders_total' => (float) ($this->resource['variance']['rejected_change_orders_total'] ?? 0.0),
                'forecast_final_cost' => (float) ($this->resource['variance']['forecast_final_cost'] ?? 0.0),
                'variance_vs_budget' => (float) ($this->resource['variance']['variance_vs_budget'] ?? 0.0),
                'variance_vs_contract_current' => (float) ($this->resource['variance']['variance_vs_contract_current'] ?? 0.0),
            ],
            'contracts' => [
                'contract_base_total' => (float) ($this->resource['contracts']['contract_base_total'] ?? 0.0),
                'change_orders_approved_total' => (float) ($this->resource['contracts']['change_orders_approved_total'] ?? 0.0),
                'change_orders_pending_total' => (float) ($this->resource['contracts']['change_orders_pending_total'] ?? 0.0),
                'change_orders_rejected_total' => (float) ($this->resource['contracts']['change_orders_rejected_total'] ?? 0.0),
                'contract_current_total' => (float) ($this->resource['contracts']['contract_current_total'] ?? 0.0),
            ],
            'time_series' => [
                'certificates_per_month' => array_map(function ($item) {
                    return [
                        'year' => (int) $item['year'],
                        'month' => (int) $item['month'],
                        'amount_payable_approved' => (float) ($item['amount_payable_approved'] ?? 0.0),
                    ];
                }, $this->resource['time_series']['certificates_per_month'] ?? []),
                'payments_per_month' => array_map(function ($item) {
                    return [
                        'year' => (int) $item['year'],
                        'month' => (int) $item['month'],
                        'amount_paid' => (float) ($item['amount_paid'] ?? 0.0),
                    ];
                }, $this->resource['time_series']['payments_per_month'] ?? []),
            ],
        ];
    }
}
