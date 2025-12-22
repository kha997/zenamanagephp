<?php declare(strict_types=1);

namespace App\Http\Requests\Contracts;

use App\Http\Requests\BaseApiRequest;
use App\Models\Contract;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Form Request for updating a contract
 */
class UpdateContractRequest extends BaseApiRequest
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
        $tenantId = $this->getTenantId();
        $contractId = $this->route('id');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('contracts')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                })->ignore($contractId)
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => ['sometimes', 'string', Rule::in(Contract::VALID_STATUSES)],
            'client_id' => [
                'nullable',
                'integer',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if ($value && !Client::where('tenant_id', $tenantId)->where('id', $value)->exists()) {
                        $fail('The selected client does not belong to your tenant.');
                    }
                }
            ],
            'project_id' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) use ($tenantId) {
                    if ($value && !Project::where('tenant_id', $tenantId)->where('id', $value)->exists()) {
                        $fail('The selected project does not belong to your tenant.');
                    }
                }
            ],
            'signed_at' => ['nullable', 'date', 'date_format:Y-m-d H:i:s'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'currency' => ['sometimes', 'string', 'size:3'],
            'total_value' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Handle a failed validation attempt.
     * Override to throw ValidationException for Laravel test compatibility
     */
    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator): void
    {
        throw new ValidationException($validator);
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

