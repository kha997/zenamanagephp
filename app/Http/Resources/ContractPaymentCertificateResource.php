<?php declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * ContractPaymentCertificateResource
 * 
 * Round 221: Payment Certificates & Payments (Actual Cost)
 * Round 241: Cost Dual-Approval Workflow (Phase 2)
 */
class ContractPaymentCertificateResource extends JsonResource
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
            'status' => $this->status,
            'period_start' => $this->period_start?->format('Y-m-d'),
            'period_end' => $this->period_end?->format('Y-m-d'),
            'amount_before_retention' => $this->amount_before_retention ? (float) $this->amount_before_retention : null,
            'retention_percent_override' => $this->retention_percent_override ? (float) $this->retention_percent_override : null,
            'retention_amount' => $this->retention_amount ? (float) $this->retention_amount : null,
            'amount_payable' => $this->amount_payable ? (float) $this->amount_payable : null,
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
