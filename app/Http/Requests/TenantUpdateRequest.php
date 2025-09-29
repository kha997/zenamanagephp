<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $tenantId = $this->route('id');
        
        return [
            'name' => 'sometimes|string|max:255',
            'domain' => [
                'sometimes',
                'string',
                'max:255',
                Rule::unique('tenants', 'domain')->ignore($tenantId)
            ],
            'status' => 'sometimes|in:trial,active,inactive,suspended',
            'settings' => 'sometimes|array',
            'settings.ownerName' => 'sometimes|string|max:255',
            'settings.ownerEmail' => 'sometimes|email|max:255',
            'settings.plan' => 'sometimes|in:Basic,Professional,Enterprise'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'domain.unique' => 'This domain is already taken',
            'status.in' => 'Status must be one of: trial, active, inactive, suspended',
            'settings.ownerEmail.email' => 'Owner email must be a valid email address',
            'settings.plan.in' => 'Plan must be one of: Basic, Professional, Enterprise'
        ];
    }
}
