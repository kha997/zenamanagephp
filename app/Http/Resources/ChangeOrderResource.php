<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ChangeOrderResource
 * 
 * Round 220: Change Orders for Contracts
 * Round 241: Cost Dual-Approval Workflow (Phase 2)
 */
class ChangeOrderResource extends JsonResource
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
            'tenant_id' => $this->tenant_id,
            'project_id' => $this->project_id,
            'contract_id' => $this->contract_id,
            'code' => $this->code,
            'title' => $this->title,
            'reason' => $this->reason,
            'status' => $this->status,
            'amount_delta' => (float) $this->amount_delta,
            'effective_date' => $this->effective_date?->format('Y-m-d'),
            'metadata' => $this->metadata,
            // Round 241: Dual approval fields
            'first_approved_by' => $this->first_approved_by,
            'first_approved_at' => $this->first_approved_at?->toISOString(),
            'second_approved_by' => $this->second_approved_by,
            'second_approved_at' => $this->second_approved_at?->toISOString(),
            'requires_dual_approval' => (bool) $this->requires_dual_approval,
            'lines' => ChangeOrderLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
