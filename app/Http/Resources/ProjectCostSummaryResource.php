<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProjectCostSummaryResource
 * 
 * Round 222: Project Cost Summary API (Budget vs Contract vs Actual)
 * 
 * Transforms project cost summary data for API response
 */
class ProjectCostSummaryResource extends JsonResource
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
            'totals' => [
                'budget_total' => (float) ($this->resource['totals']['budget_total'] ?? 0.0),
                'contract_base_total' => (float) ($this->resource['totals']['contract_base_total'] ?? 0.0),
                'contract_current_total' => (float) ($this->resource['totals']['contract_current_total'] ?? 0.0),
                'total_certified_amount' => (float) ($this->resource['totals']['total_certified_amount'] ?? 0.0),
                'total_paid_amount' => (float) ($this->resource['totals']['total_paid_amount'] ?? 0.0),
                'outstanding_amount' => (float) ($this->resource['totals']['outstanding_amount'] ?? 0.0),
            ],
            'categories' => array_map(function ($category) {
                return [
                    'cost_category' => $category['cost_category'],
                    'budget_total' => (float) ($category['budget_total'] ?? 0.0),
                    'contract_base_total' => (float) ($category['contract_base_total'] ?? 0.0),
                ];
            }, $this->resource['categories'] ?? []),
        ];
    }
}
