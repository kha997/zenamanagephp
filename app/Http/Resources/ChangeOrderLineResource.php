<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ChangeOrderLineResource
 * 
 * Round 220: Change Orders for Contracts
 */
class ChangeOrderLineResource extends JsonResource
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
            'change_order_id' => $this->change_order_id,
            'contract_id' => $this->contract_id,
            'project_id' => $this->project_id,
            'contract_line_id' => $this->contract_line_id,
            'budget_line_id' => $this->budget_line_id,
            'item_code' => $this->item_code,
            'description' => $this->description,
            'unit' => $this->unit,
            'quantity_delta' => $this->quantity_delta ? (float) $this->quantity_delta : null,
            'unit_price_delta' => $this->unit_price_delta ? (float) $this->unit_price_delta : null,
            'amount_delta' => (float) $this->amount_delta,
            'metadata' => $this->metadata,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
