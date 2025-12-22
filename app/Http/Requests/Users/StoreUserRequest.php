<?php declare(strict_types=1);

namespace App\Http\Requests\Users;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Store User Request
 * 
 * Validates user creation form data.
 */
class StoreUserRequest extends FormRequest
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
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                'min:8',
                'max:128',
                Password::min(8)
                    ->letters()
                    ->mixedCase()
                    ->numbers()
                    ->symbols()
                    ->uncompromised(),
            ],
            'password_confirmation' => [
                'required',
                'string',
                'min:8',
                'max:128',
            ],
            'role' => [
                'required',
                'string',
                'in:admin,pm,member,client',
            ],
            'phone' => [
                'nullable',
                'string',
                'max:20',
                'regex:/^[\+]?[0-9\s\-\(\)]+$/',
            ],
            'status' => [
                'nullable',
                'string',
                'in:active,inactive,suspended',
            ],
            'tenant_id' => [
                'sometimes',
                'required',
                'string',
                'exists:tenants,id',
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Full name is required.',
            'name.string' => 'Full name must be a string.',
            'name.max' => 'Full name must not exceed 255 characters.',
            'name.min' => 'Full name must be at least 2 characters.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Email address must not exceed 255 characters.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a string.',
            'password.confirmed' => 'Password confirmation does not match.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.max' => 'Password must not exceed 128 characters.',
            'password_confirmation.required' => 'Password confirmation is required.',
            'password_confirmation.string' => 'Password confirmation must be a string.',
            'password_confirmation.min' => 'Password confirmation must be at least 8 characters.',
            'password_confirmation.max' => 'Password confirmation must not exceed 128 characters.',
            'role.required' => 'User role is required.',
            'role.string' => 'User role must be a string.',
            'role.in' => 'User role must be one of: admin, pm, member, client.',
            'phone.nullable' => 'Phone number is optional.',
            'phone.string' => 'Phone number must be a string.',
            'phone.max' => 'Phone number must not exceed 20 characters.',
            'phone.regex' => 'Please enter a valid phone number.',
            'status.nullable' => 'Status is optional.',
            'status.string' => 'Status must be a string.',
            'status.in' => 'Status must be one of: active, inactive, suspended.',
            'tenant_id.sometimes' => 'Tenant ID is required when creating users.',
            'tenant_id.required' => 'Tenant ID is required.',
            'tenant_id.string' => 'Tenant ID must be a string.',
            'tenant_id.exists' => 'Selected tenant does not exist.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'name' => 'full name',
            'email' => 'email address',
            'password' => 'password',
            'password_confirmation' => 'password confirmation',
            'role' => 'user role',
            'phone' => 'phone number',
            'status' => 'status',
            'tenant_id' => 'tenant',
        ];
    }
}
