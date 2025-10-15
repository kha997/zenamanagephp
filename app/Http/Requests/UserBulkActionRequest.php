<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UserBulkActionRequest extends FormRequest
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
            'action' => ['required', 'string', Rule::in(['suspend', 'resume', 'change_role', 'force_logout', 'export'])],
            'user_ids' => 'required|array|min:1',
                    'user_ids.*' => 'string|exists:users,id',
            'role' => 'required_if:action,change_role|string|in:admin,manager,member',
            'reason' => 'nullable|string|max:500'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'action.required' => 'Please select an action.',
            'action.in' => 'The action must be one of: suspend, resume, change_role, force_logout, export.',
            'user_ids.required' => 'Please select at least one user.',
            'user_ids.array' => 'User IDs must be an array.',
            'user_ids.min' => 'Please select at least one user.',
            'user_ids.*.exists' => 'One or more selected users do not exist.',
            'role.required_if' => 'Role is required when changing user roles.',
            'role.in' => 'The role must be one of: admin, manager, member.'
        ];
    }
}