<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ContractResource
 * 
 * Round 219: Core Contracts & Budget (Backend-first)
 */
class ContractResource extends JsonResource
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
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'party_name' => $this->party_name,
            'currency' => $this->currency ?? 'VND',
            'base_amount' => $this->base_amount ? (float) $this->base_amount : null,
            'current_amount' => $this->current_amount, // Round 220: Includes approved change orders
            'total_certified_amount' => $this->total_certified_amount, // Round 221: Sum of approved certificates
            'total_paid_amount' => $this->total_paid_amount, // Round 221: Sum of actual payments
            'outstanding_amount' => $this->outstanding_amount, // Round 221: current_amount - total_paid_amount
            'vat_percent' => $this->vat_percent ? (float) $this->vat_percent : null,
            'total_amount_with_vat' => $this->total_amount_with_vat ? (float) $this->total_amount_with_vat : null,
            'retention_percent' => $this->retention_percent ? (float) $this->retention_percent : null,
            'status' => $this->status,
            'start_date' => $this->start_date?->format('Y-m-d'),
            'end_date' => $this->end_date?->format('Y-m-d'),
            'signed_at' => $this->signed_at?->toISOString(),
            'metadata' => $this->metadata,
            'notes' => $this->notes,
            'lines' => ContractLineResource::collection($this->whenLoaded('lines')),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
