<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * User Bulk Create Request
 * 
 * Validates bulk user creation with consistent rules
 */
class UserBulkCreateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && auth()->user()->hasPermission('users.create');
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return ValidationRules::userBulkCreate();
    }

    /**
     * Get custom validation messages.
     */
    public function messages(): array
    {
        return ValidationRules::messages();
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'users.*.name' => 'user name',
            'users.*.email' => 'user email',
            'users.*.password' => 'user password',
            'users.*.phone' => 'user phone',
            'users.*.first_name' => 'user first name',
            'users.*.last_name' => 'user last name',
            'users.*.department' => 'user department',
            'users.*.job_title' => 'user job title',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default password for users without one
        if ($this->has('users')) {
            $users = $this->input('users', []);
            foreach ($users as $index => $user) {
                if (empty($user['password'])) {
                    $users[$index]['password'] = 'DefaultPassword123!';
                }
            }
            $this->merge(['users' => $users]);
        }

        // Set tenant_id from authenticated user if not provided
        if (!$this->has('tenant_id') && auth()->check()) {
            $this->merge(['tenant_id' => auth()->user()->tenant_id]);
        }
    }
}
