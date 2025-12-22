<?php declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\Http\Requests\BaseApiRequest;

/**
 * Form Request for storing a new contract payment
 * 
 * Round 36: Contract Payment Schedule Backend
 * Round 37: Payment Hardening - Unified validation error envelope
 * Round 38: Validation Envelope Documentation
 * 
 * Validation errors are returned in details.validation.<field> format.
 * Domain-specific errors (e.g., PAYMENT_TOTAL_EXCEEDED) also attach to details.validation.amount
 * but use specific error codes instead of generic VALIDATION_ERROR.
 */
class StoreContractPaymentRequest extends BaseApiRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:deposit,milestone,progress,retention,final'],
            'due_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['nullable', 'string', 'size:3'],
            'status' => ['nullable', 'string', 'in:planned,due,paid,overdue,cancelled'],
            'paid_at' => ['nullable', 'date', 'date_format:Y-m-d H:i:s'],
            'notes' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Get tenant ID from request context
     */
    protected function getTenantId(): string
    {
        $request = $this;
        
        // Priority 1: Check request attribute (set by EnsureTenantPermission middleware)
        $activeTenantId = $request->attributes->get('active_tenant_id');
        if ($activeTenantId) {
            return (string) $activeTenantId;
        }
        
        // Priority 2: Use TenancyService to resolve active tenant
        $tenancyService = app(\App\Services\TenancyService::class);
        $user = $this->user();
        if ($user) {
            $resolvedTenantId = $tenancyService->resolveActiveTenantId($user, $request);
            if ($resolvedTenantId) {
                return (string) $resolvedTenantId;
            }
        }
        
        // Priority 3: Fallback to legacy user->tenant_id
        if ($user && $user->tenant_id) {
            return (string) $user->tenant_id;
        }
        
        throw new \RuntimeException('Tenant ID not found for user');
    }
}
