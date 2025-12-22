<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ContractPaymentResource
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 * Round 241: Cost Dual-Approval Workflow (Phase 2)
 */
class ContractPaymentResource extends JsonResource
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
            'certificate_id' => $this->certificate_id,
            'paid_date' => $this->paid_date?->format('Y-m-d'),
            'amount_paid' => $this->amount_paid ? (float) $this->amount_paid : null,
            'currency' => $this->currency,
            'payment_method' => $this->payment_method,
            'reference_no' => $this->reference_no,
            'metadata' => $this->metadata,
            // Round 241: Dual approval fields
            'first_approved_by' => $this->first_approved_by,
            'first_approved_at' => $this->first_approved_at?->toISOString(),
            'second_approved_by' => $this->second_approved_by,
            'second_approved_at' => $this->second_approved_at?->toISOString(),
            'requires_dual_approval' => (bool) $this->requires_dual_approval,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
