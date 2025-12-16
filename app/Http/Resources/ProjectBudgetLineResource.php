<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ProjectBudgetLineResource
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 */
class ProjectBudgetLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'cost_category' => $this->cost_category,
            'cost_code' => $this->cost_code,
            'description' => $this->description,
            'unit' => $this->unit,
            'quantity' => $this->quantity ? (float) $this->quantity : null,
            'unit_price_budget' => $this->unit_price_budget ? (float) $this->unit_price_budget : null,
            'amount_budget' => (float) $this->amount_budget,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
