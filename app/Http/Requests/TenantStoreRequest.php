<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantStoreRequest extends FormRequest
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
        return [
            'name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:tenants,domain',
            'ownerName' => 'required|string|max:255',
            'ownerEmail' => 'required|email|max:255',
            'plan' => 'required|in:Basic,Professional,Enterprise'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Tenant name is required',
            'domain.required' => 'Domain is required',
            'domain.unique' => 'This domain is already taken',
            'ownerName.required' => 'Owner name is required',
            'ownerEmail.required' => 'Owner email is required',
            'ownerEmail.email' => 'Owner email must be a valid email address',
            'plan.required' => 'Plan is required',
            'plan.in' => 'Plan must be one of: Basic, Professional, Enterprise'
        ];
    }
}
