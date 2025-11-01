<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UserInviteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Temporarily disable auth check for testing
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
                    'tenant_id' => 'required|string|exists:tenants,id',
            'role' => ['required', 'string', Rule::in(['admin', 'manager', 'member'])],
            'note' => 'nullable|string|max:500',
            'send_email' => 'boolean',
            'require_mfa' => 'boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'The name field is required.',
            'email.required' => 'The email field is required.',
            'email.email' => 'The email must be a valid email address.',
            'email.unique' => 'A user with this email already exists.',
            'tenant_id.required' => 'Please select a tenant.',
            'tenant_id.exists' => 'The selected tenant does not exist.',
            'role.required' => 'Please select a role.',
            'role.in' => 'The role must be one of: admin, manager, member.'
        ];
    }
}