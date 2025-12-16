<?php declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\Http\Requests\BaseApiRequest;

/**
 * Form Request for updating a contract expense
 * 
 * Round 44: Contract Expenses (Actual Costs) - Backend Only
 * 
 * Validation errors are returned in details.validation.<field> format.
 */
class UpdateContractExpenseRequest extends BaseApiRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'string', 'max:64'],
            'vendor_name' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'numeric', 'min:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'incurred_at' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'in:planned,recorded,approved,paid,cancelled'],
            'notes' => ['nullable', 'string'],
            'sort_order' => ['nullable', 'integer'],
            'budget_line_id' => ['nullable', 'string'], // ulid; tồn tại sẽ check ở service
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

