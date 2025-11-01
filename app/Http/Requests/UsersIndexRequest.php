<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UsersIndexRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Temporarily disable auth check
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'q' => 'nullable|string|max:255',
            'tenant_id' => 'nullable|string|exists:tenants,id',
            'tenant' => 'nullable|string|exists:tenants,slug',
            'role' => 'nullable|string', // Will be validated in controller
            'status' => 'nullable|string', // Will be validated in controller
            'range' => 'nullable|in:7d,30d,90d,all,this_month,last_month',
            'last_login' => 'nullable|in:7d,30d,never',
            'mfa' => 'nullable|in:on,off',
            'from' => 'nullable|date|before_or_equal:to',
            'to' => 'nullable|date|after_or_equal:from',
            'sort' => 'nullable|string',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tenant_id.exists' => 'The selected tenant does not exist.',
            'tenant.exists' => 'The selected tenant slug does not exist.',
            'range.in' => 'The range must be one of: 7d, 30d, 90d, all, this_month, last_month.',
            'last_login.in' => 'The last login filter must be one of: 7d, 30d, never.',
            'mfa.in' => 'The MFA filter must be either on or off.',
            'per_page.max' => 'The per page value may not be greater than 100.'
        ];
    }
}